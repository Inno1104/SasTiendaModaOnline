<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\StockConteo;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class StockHistoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_paginated_filtered_and_isolated_by_store(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $variant = $this->variant($this->product($store, 'Producto propio'), 10, 3);
        $this->movement($variant, $administrator, StockMovimiento::TIPO_ENTRADA, 0, 10);
        $this->movement($variant, $administrator, StockMovimiento::TIPO_SALIDA, 12, 10);
        [$foreignUser, $foreignStore] = $this->context('Administrador');
        $foreignVariant = $this->variant($this->product($foreignStore, 'Producto ajeno'), 4, 2);

        $response = $this->actingAs($administrator)->get(route('stock.history', [
            'variante' => $variant,
            'tipo' => StockMovimiento::TIPO_ENTRADA,
        ]))->assertOk()->assertSee('Kardex');

        $this->assertSame(1, $response->viewData('movimientos')->total());
        $this->actingAs($foreignUser)->get(route('stock.history', $variant))->assertNotFound();
        $this->actingAs($administrator)->get(route('stock.history', $foreignVariant))->assertNotFound();
    }

    public function test_history_accepts_and_filters_return_movements(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $variant = $this->variant($this->product($store), 5, 2);
        $return = $this->movement($variant, $administrator, StockMovimiento::TIPO_DEVOLUCION, 4, 5);
        $this->movement($variant, $administrator, StockMovimiento::TIPO_VENTA, 6, 5);

        $response = $this->actingAs($administrator)->get(route('stock.history', [
            'variante' => $variant,
            'tipo' => StockMovimiento::TIPO_DEVOLUCION,
        ]))->assertOk()->assertSee('value="devolucion"', false);

        $movements = $response->viewData('movimientos');
        $this->assertSame(1, $movements->total());
        $this->assertSame($return->id, $movements->first()->id);
    }

    public function test_physical_count_records_difference_without_changing_stock(): void
    {
        [$warehouse, $store] = $this->context('Almacén');
        $variant = $this->variant($this->product($store), 10, 3);

        $this->actingAs($warehouse)->post(route('stock.count', $variant), [
            'stock_fisico' => 7,
            'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Conteo de cierre',
        ])->assertSessionHas('success');

        $count = StockConteo::query()->sole();
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(10, $count->stock_sistema);
        $this->assertSame(7, $count->stock_fisico);
        $this->assertSame(-3, $count->diferencia);
        $this->assertSame(StockConteo::ESTADO_PENDIENTE, $count->estado);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'stock.conteo_registrado',
            'auditable_type' => StockConteo::class,
            'auditable_id' => $count->id,
        ]);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_only_administrator_reconciles_and_writes_kardex_and_audit_atomically(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $warehouse = $this->user($store, 'Almacén');
        $variant = $this->variant($this->product($store), 10, 3);
        $this->actingAs($warehouse)->post(route('stock.count', $variant), [
            'stock_fisico' => 7,
            'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(),
        ])->assertSessionHas('success');
        $count = StockConteo::query()->sole();

        $this->post(route('stock.reconcile', $count), ['motivo' => 'Diferencia validada'])->assertForbidden();
        $this->assertSame(10, $variant->fresh()->stock);

        $this->actingAs($administrator)->post(route('stock.reconcile', $count), [
            'motivo' => 'Diferencia validada',
        ])->assertSessionHas('success');

        $count->refresh();
        $movement = StockMovimiento::query()->sole();
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame(StockConteo::ESTADO_CONCILIADO, $count->estado);
        $this->assertSame($administrator->id, $count->autorizado_por);
        $this->assertSame(StockMovimiento::TIPO_CONCILIACION, $movement->tipo);
        $this->assertSame(-3, $movement->cantidad);
        $this->assertSame($count->id, $movement->referencia_id);
        $this->assertSame(StockConteo::class, $movement->referencia_type);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'stock.conciliado',
            'user_id' => $administrator->id,
            'auditable_id' => $count->id,
        ]);

        $this->post(route('stock.reconcile', $count), ['motivo' => 'Reintento de conciliación'])
            ->assertSessionHas('success');
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertSame(1, Auditoria::where('accion', 'stock.conciliado')->count());
    }

    public function test_equal_physical_count_is_auto_approved_without_changing_stock(): void
    {
        [$warehouse, $store] = $this->context('Almacén');
        $variant = $this->variant($this->product($store), 10, 3);

        $this->actingAs($warehouse)->post(route('stock.count', $variant), [
            'stock_fisico' => 10,
            'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Conteo exacto',
        ])->assertSessionHas('success', 'Conteo físico sin diferencias autoaprobado.');

        $count = StockConteo::query()->sole();
        $this->assertSame(StockConteo::ESTADO_CONCILIADO, $count->estado);
        $this->assertSame($warehouse->id, $count->autorizado_por);
        $this->assertNotNull($count->conciliado_at);
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(0, $variant->fresh()->lock_version);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertDatabaseHas('auditorias', ['accion' => 'stock.conteo_autoaprobado']);
    }

    public function test_count_submission_is_idempotent_and_rejects_key_reuse_with_other_data(): void
    {
        [$warehouse, $store] = $this->context('Almacén');
        $variant = $this->variant($this->product($store), 10, 3);
        $operationId = (string) Str::uuid();
        $payload = [
            'stock_fisico' => 8,
            'expected_version' => 0,
            'operacion_id' => $operationId,
            'motivo' => 'Primer conteo',
        ];
        $this->actingAs($warehouse);

        $this->post(route('stock.count', $variant), $payload)->assertSessionHas('success');
        $this->post(route('stock.count', $variant), $payload)->assertSessionHas('success');
        $this->assertDatabaseCount('stock_conteos', 1);
        $this->assertSame(1, Auditoria::where('accion', 'stock.conteo_registrado')->count());

        $this->post(route('stock.count', $variant), array_replace($payload, ['stock_fisico' => 7]))
            ->assertSessionHasErrors('operacion_id');
        $this->assertDatabaseCount('stock_conteos', 1);
    }

    public function test_new_count_replaces_pending_count_with_complete_trace(): void
    {
        [$warehouse, $store] = $this->context('Almacén');
        $variant = $this->variant($this->product($store), 10, 3);
        $this->actingAs($warehouse)->post(route('stock.count', $variant), [
            'stock_fisico' => 8, 'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(), 'motivo' => 'Conteo inicial',
        ])->assertSessionHas('success');
        $first = StockConteo::query()->sole();

        $this->post(route('stock.count', $variant), [
            'stock_fisico' => 9, 'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(), 'motivo' => 'Segundo conteo',
        ])->assertSessionHas('success');

        $replacement = StockConteo::query()->whereKeyNot($first->id)->sole();
        $first->refresh();
        $this->assertSame(StockConteo::ESTADO_REEMPLAZADO, $first->estado);
        $this->assertSame($replacement->id, $first->reemplazado_por_conteo_id);
        $this->assertSame($warehouse->id, $first->reemplazado_por_usuario_id);
        $this->assertNotNull($first->reemplazado_at);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'stock.conteo_reemplazado', 'auditable_id' => $first->id,
        ]);
    }

    public function test_terminal_counts_are_immutable_in_eloquent_and_database(): void
    {
        [$warehouse, $store] = $this->context('Almacén');
        $variant = $this->variant($this->product($store), 10, 3);
        $this->actingAs($warehouse)->post(route('stock.count', $variant), [
            'stock_fisico' => 10, 'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(), 'motivo' => 'Conteo exacto',
        ])->assertSessionHas('success');
        $count = StockConteo::query()->sole();

        try {
            $count->update(['motivo' => 'Manipulado']);
            $this->fail('Eloquent permitió modificar un conteo conciliado.');
        } catch (LogicException) {
            $this->assertSame('Conteo exacto', $count->fresh()->motivo);
        }

        foreach (['update', 'delete'] as $operation) {
            try {
                $query = DB::table('stock_conteos')->where('id', $count->id);
                $operation === 'update' ? $query->update(['motivo' => 'SQL']) : $query->delete();
                $this->fail("La base de datos permitió {$operation} un conteo conciliado.");
            } catch (QueryException) {
                $this->assertDatabaseHas('stock_conteos', ['id' => $count->id, 'motivo' => 'Conteo exacto']);
            }
        }
    }

    public function test_database_rejects_an_invalid_pending_count_difference(): void
    {
        [$warehouse, $store] = $this->context('Almacén');
        $variant = $this->variant($this->product($store), 10, 3);
        $this->actingAs($warehouse)->post(route('stock.count', $variant), [
            'stock_fisico' => 8, 'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(), 'motivo' => 'Conteo inicial',
        ])->assertSessionHas('success');
        $count = StockConteo::query()->sole();

        $this->expectException(QueryException::class);
        DB::table('stock_conteos')->where('id', $count->id)->update(['diferencia' => 999]);
    }

    public function test_stale_count_cannot_be_reconciled_or_partially_written(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $variant = $this->variant($this->product($store), 10, 3);
        $this->actingAs($administrator)->post(route('stock.count', $variant), [
            'stock_fisico' => 8,
            'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(),
        ])->assertSessionHas('success');
        $count = StockConteo::query()->sole();
        DB::table('producto_variantes')->where('id', $variant->id)->update(['stock' => 9, 'lock_version' => 1]);

        $this->post(route('stock.reconcile', $count), ['motivo' => 'Intento obsoleto'])
            ->assertSessionHasErrors('conteo');

        $this->assertSame(9, $variant->fresh()->stock);
        $this->assertSame(StockConteo::ESTADO_PENDIENTE, $count->fresh()->estado);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertSame(0, Auditoria::where('accion', 'stock.conciliado')->count());
    }

    public function test_history_clearly_marks_pending_and_stale_counts_and_hides_sensitive_action(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $variant = $this->variant($this->product($store), 10, 3);
        $this->actingAs($administrator)->post(route('stock.count', $variant), [
            'stock_fisico' => 8,
            'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Conteo antes del movimiento',
        ])->assertSessionHas('success');
        $count = StockConteo::query()->sole();
        DB::table('producto_variantes')->where('id', $variant->id)->update([
            'stock' => 9,
            'lock_version' => 1,
        ]);

        $response = $this->get(route('stock.history', $variant))->assertOk();

        $response->assertViewHas('pendingCount', 1)
            ->assertViewHas('staleCount', 1)
            ->assertSee('<strong>1</strong> conteo pendiente', false)
            ->assertSee('<strong>1</strong> conteo obsoleto', false)
            ->assertSee('El stock cambió después del conteo')
            ->assertSee('Registra un conteo nuevo')
            ->assertDontSee(route('stock.reconcile', $count), false);
    }

    public function test_count_validation_errors_are_visible_and_accessible(): void
    {
        [$warehouse, $store] = $this->context('Almacén');
        $variant = $this->variant($this->product($store), 10, 3);

        $this->actingAs($warehouse)
            ->from(route('stock.history', $variant))
            ->post(route('stock.count', $variant), [
                'stock_fisico' => -1,
                'expected_version' => 0,
                'operacion_id' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('stock.history', $variant))
            ->assertSessionHasErrors('stock_fisico');

        $this->get(route('stock.history', $variant))
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('aria-live="assertive"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('stock_fisico_error', false);
    }

    public function test_reconciliation_rolls_back_stock_movement_and_state_when_audit_fails(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $variant = $this->variant($this->product($store), 10, 3);
        $this->actingAs($administrator)->post(route('stock.count', $variant), [
            'stock_fisico' => 6,
            'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(),
        ])->assertSessionHas('success');
        $count = StockConteo::query()->sole();
        Event::listen('eloquent.creating: '.Auditoria::class, function (): void {
            throw new RuntimeException('Auditoría no disponible.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->post(route('stock.reconcile', $count), ['motivo' => 'Diferencia confirmada']);
            $this->fail('La conciliación debía fallar junto con la auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría no disponible.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.Auditoria::class);
            $this->withExceptionHandling();
        }

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(StockConteo::ESTADO_PENDIENTE, $count->fresh()->estado);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertSame(0, Auditoria::where('accion', 'stock.conciliado')->count());
    }

    public function test_replenishment_export_contains_only_operational_low_or_exhausted_variants(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $active = $this->product($store, '=Producto operativo');
        $this->variant($active, 0, 3, 'S', 'Negro');
        $this->variant($active, 3, 3, 'M', 'Azul');
        $this->variant($active, 8, 3, 'L', 'Verde');
        $inactive = $this->product($store, 'Producto inactivo');
        $inactive->update(['activo' => false]);
        $this->variant($inactive, 0, 3, 'U', 'Rojo');

        $response = $this->actingAs($administrator)->get(route('stock.replenishment'))->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=Producto operativo", $content);
        $this->assertStringContainsString('Negro', $content);
        $this->assertStringContainsString('Azul', $content);
        $this->assertStringNotContainsString('Verde', $content);
        $this->assertStringNotContainsString('Producto inactivo', $content);
    }

    /** @return array{User, Tienda} */
    private function context(string $role): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);

        return [$this->user($store, $role), $store];
    }

    private function user(Tienda $store, string $role): User
    {
        $suffix = Str::lower(Str::random(8));

        return User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => $role])->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@stock-history.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function product(Tienda $store, string $name = 'Producto conciliable'): Producto
    {
        $suffix = Str::lower(Str::random(8));
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'nombre' => 'Categoría '.$suffix,
            'nombre_normalizado' => 'categoria '.$suffix, 'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex', 'activo' => true,
        ]);

        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'categoria_id' => $category->id,
            'codigo' => 'PROD-'.Str::upper(Str::random(8)), 'nombre' => $name,
            'genero' => 'unisex', 'tipo' => 'casual', 'precio_compra' => 1,
            'precio_venta' => 2, 'activo' => true,
        ]);
    }

    private function variant(Producto $product, int $stock, int $minimum, string $size = 'M', string $color = 'Negro'): ProductoVariante
    {
        return ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $product->tienda_id, 'producto_id' => $product->id,
            'talla' => $size, 'color' => $color, 'stock' => $stock, 'stock_minimo' => $minimum,
        ]);
    }

    private function movement(ProductoVariante $variant, User $user, string $type, int $previous, int $result): StockMovimiento
    {
        return StockMovimiento::create([
            'tienda_id' => $variant->tienda_id, 'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id, 'user_id' => $user->id, 'tipo' => $type,
            'direccion' => $result >= $previous ? 'entrada' : 'salida', 'stock_anterior' => $previous,
            'cantidad' => $result - $previous, 'stock_resultante' => $result, 'motivo' => 'Prueba kardex',
        ]);
    }
}
