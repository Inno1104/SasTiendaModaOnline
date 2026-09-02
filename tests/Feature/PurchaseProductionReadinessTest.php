<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use App\Services\PurchaseTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_supplier_purchase_stock_audit_reconciliation_and_cancellation_flow(): void
    {
        [$admin, $store, $supplier, $firstVariant] = $this->context(5);
        $secondVariant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'producto_id' => $firstVariant->producto_id,
            'talla' => 'L', 'color' => 'Rojo', 'codigo_barra' => 'FULL-FLOW-002',
            'stock' => 8, 'stock_minimo' => 2,
        ]);
        $payload = $this->payload($supplier, [[$firstVariant, 4, '5.25'], [$secondVariant, 3, '7.10']]);

        $this->actingAs($admin)->post(route('compras.store'), $payload)
            ->assertRedirect(route('compras.index'))->assertSessionHas('success');
        $purchase = Compra::query()->sole();

        $this->assertSame('42.30', $purchase->total);
        $this->assertSame(9, $firstVariant->fresh()->stock);
        $this->assertSame(11, $secondVariant->fresh()->stock);
        $this->assertSame([4, 3], StockMovimiento::query()->orderBy('id')->pluck('cantidad')->all());
        $this->assertSame('compra.recibida', Auditoria::query()->sole()->accion);
        $this->assertTrue(app(PurchaseTraceService::class)->trace(
            $purchase->load('detalles.producto', 'detalles.variante')
        )['conciliado']);
        $this->get(route('compras.show', $purchase))->assertOk()->assertSee('Conciliada');

        $this->post(route('compras.cancel', $purchase), [
            'motivo' => 'Devolución integral confirmada con el proveedor',
        ])->assertSessionHas('success');

        $this->assertSame('anulado', $purchase->fresh()->estado);
        $this->assertSame(5, $firstVariant->fresh()->stock);
        $this->assertSame(8, $secondVariant->fresh()->stock);
        $this->assertSame([4, 3, -4, -3], StockMovimiento::query()->orderBy('id')->pluck('cantidad')->all());
        $this->assertSame(['compra.recibida', 'compra.anulada'], Auditoria::query()->orderBy('id')->pluck('accion')->all());
        $this->assertTrue(app(PurchaseTraceService::class)->trace(
            $purchase->fresh()->load('detalles.producto', 'detalles.variante')
        )['conciliado']);
    }

    public function test_competing_retries_and_cancellations_do_not_duplicate_stock_kardex_or_audit(): void
    {
        [$firstAdmin, $store, $supplier, $variant] = $this->context(10);
        $secondAdmin = $this->user($store, 'Administrador');
        $payload = $this->payload($supplier, [[$variant, 5, '4.00']]);

        $this->actingAs($firstAdmin)->post(route('compras.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($secondAdmin)->post(route('compras.store'), $payload)->assertSessionHasNoErrors();
        $purchase = Compra::withoutGlobalScopes()->where('tienda_id', $store->id)->sole();

        $this->assertSame(15, $variant->fresh()->stock);
        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('detalle_compras', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertDatabaseCount('auditorias', 1);

        $reason = ['motivo' => 'Anulación concurrente validada por administración'];
        $this->actingAs($firstAdmin)->post(route('compras.cancel', $purchase), $reason)->assertSessionHas('success');
        $this->actingAs($secondAdmin)->post(route('compras.cancel', $purchase), $reason)->assertSessionHasErrors('compra');

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movimientos', 2);
        $this->assertDatabaseCount('auditorias', 2);
        $this->assertSame(1, Auditoria::withoutGlobalScopes()->where('accion', 'compra.anulada')->count());
    }

    public function test_independent_competing_operations_keep_sequential_numbers_and_all_stock_updates(): void
    {
        [$firstAdmin, $store, $supplier, $variant] = $this->context(0);
        $secondAdmin = $this->user($store, 'Administrador');

        $this->actingAs($firstAdmin)->post(route('compras.store'), $this->payload($supplier, [[$variant, 2, '3.00']]))
            ->assertSessionHasNoErrors();
        $this->actingAs($secondAdmin)->post(route('compras.store'), $this->payload($supplier, [[$variant, 3, '3.00']]))
            ->assertSessionHasNoErrors();

        $purchases = Compra::withoutGlobalScopes()->where('tienda_id', $store->id)->orderBy('id')->get();
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertCount(2, $purchases);
        $this->assertSame([
            "C-{$store->id}-".today()->year.'-00001',
            "C-{$store->id}-".today()->year.'-00002',
        ], $purchases->pluck('numero_compra')->all());
        $this->assertSame([2, 3], StockMovimiento::withoutGlobalScopes()->where('tienda_id', $store->id)->orderBy('id')->pluck('cantidad')->all());
        $this->assertSame(2, Auditoria::withoutGlobalScopes()->where('tienda_id', $store->id)->where('accion', 'compra.recibida')->count());
    }

    public function test_all_purchase_surfaces_and_mutations_are_isolated_by_store(): void
    {
        [$ownAdmin, , $ownSupplier, $ownVariant] = $this->context(4);
        [$foreignAdmin, $foreignStore, $foreignSupplier, $foreignVariant] = $this->context(7);
        $this->actingAs($foreignAdmin)->post(route('compras.store'), $this->payload($foreignSupplier, [[$foreignVariant, 2, '6.00']]))
            ->assertSessionHasNoErrors();
        $foreignPurchase = Compra::withoutGlobalScopes()->where('tienda_id', $foreignStore->id)->sole();

        $this->actingAs($ownAdmin)->get(route('compras.index'))->assertOk()->assertDontSee($foreignPurchase->numero_compra);
        $this->get(route('compras.show', $foreignPurchase))->assertNotFound();
        $this->post(route('compras.cancel', $foreignPurchase), ['motivo' => 'Intento ajeno suficientemente descriptivo'])->assertNotFound();
        $catalog = $this->getJson(route('compras.catalogo'))->assertOk()->json('data');
        $this->assertNotContains($foreignVariant->id, collect($catalog)->pluck('id')->all());

        $this->post(route('compras.store'), $this->payload($foreignSupplier, [[$ownVariant, 1, '2.00']]))
            ->assertSessionHasErrors('proveedor_id');
        $this->post(route('compras.store'), $this->payload($ownSupplier, [[$foreignVariant, 1, '2.00']]))
            ->assertSessionHasErrors('items.0.variante_id');
        $this->assertSame(4, $ownVariant->fresh()->stock);
        $this->assertSame(9, $foreignVariant->fresh()->stock);
    }

    public function test_purchase_schema_migrations_constraints_and_indexes_are_production_ready(): void
    {
        $purchaseIndexes = collect(Schema::getIndexes('compras'))->keyBy('name');
        $counterIndexes = collect(Schema::getIndexes('compra_contadores'))->keyBy('name');
        $detailIndexes = collect(Schema::getIndexes('detalle_compras'))->keyBy('name');
        $movementIndexes = collect(Schema::getIndexes('stock_movimientos'))->keyBy('name');

        $this->assertTrue(Schema::hasColumns('compras', [
            'tienda_id', 'operacion_id', 'operacion_hash', 'numero_compra', 'proveedor_id',
            'user_id', 'fecha', 'total', 'estado', 'anulada_por', 'anulada_at', 'motivo_anulacion',
        ]));
        $this->assertTrue(Schema::hasColumns('detalle_compras', [
            'tienda_id', 'compra_id', 'producto_id', 'variante_id', 'cantidad', 'precio_unitario', 'subtotal',
        ]));
        $this->assertTrue((bool) $purchaseIndexes->get('compras_tienda_operacion_unique')['unique']);
        $this->assertTrue($purchaseIndexes->has('compras_tienda_proveedor'));
        $this->assertTrue($purchaseIndexes->has('compras_tienda_estado_fecha'));
        $this->assertTrue($purchaseIndexes->has('compras_tienda_fecha_orden'));
        $this->assertTrue((bool) $counterIndexes->get('compra_contador_tienda_anio_unico')['unique']);
        $this->assertSame(['tienda_id', 'compra_id', 'id'], $detailIndexes->get('detalle_compra_tienda_compra')['columns']);
        $this->assertSame(
            ['tienda_id', 'referencia_type', 'referencia_id', 'created_at', 'id'],
            $movementIndexes->get('stock_mov_tienda_referencia_fecha')['columns']
        );

        foreach ([
            '2026_08_25_000031_create_purchase_counters.php',
            '2026_08_25_000032_add_purchase_cancellation_fields.php',
            '2026_08_25_000033_optimize_purchase_queries.php',
            '2026_08_25_000034_optimize_purchase_trace_queries.php',
        ] as $migration) {
            $this->assertFileExists(database_path('migrations/'.$migration));
        }
    }

    /** @return array{User, Tienda, Proveedor, ProductoVariante} */
    private function context(int $stock): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $admin = $this->user($store, 'Administrador');
        $supplier = Proveedor::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'nombre' => 'Proveedor '.$suffix, 'activo' => true]);
        $category = Categoria::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'nombre' => 'Categoría '.$suffix, 'nombre_normalizado' => 'categoria '.$suffix, 'slug' => 'categoria-'.$suffix, 'genero' => 'unisex', 'activo' => true]);
        $product = Producto::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'categoria_id' => $category->id, 'codigo' => 'SKU-'.Str::upper($suffix), 'nombre' => 'Producto '.$suffix, 'genero' => 'unisex', 'tipo' => 'casual', 'precio_compra' => 5, 'precio_venta' => 10, 'activo' => true]);
        $variant = ProductoVariante::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'producto_id' => $product->id, 'talla' => 'M', 'color' => 'Negro', 'codigo_barra' => 'BAR-'.Str::upper($suffix), 'stock' => $stock, 'stock_minimo' => 2]);

        return [$admin, $store, $supplier, $variant];
    }

    private function user(Tienda $store, string $role): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create(['rol_id' => Rol::firstOrCreate(['nombre' => $role])->id, 'tienda_id' => $store->id, 'name' => $role.' '.$suffix, 'email' => $suffix.'@purchase-production.test', 'password' => 'password', 'activo' => true]);
    }

    /** @param array<int, array{ProductoVariante, int, string}> $lines */
    private function payload(Proveedor $supplier, array $lines): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'proveedor_id' => $supplier->id,
            'fecha' => today()->toDateString(),
            'items' => collect($lines)->map(fn (array $line) => [
                'variante_id' => $line[0]->id, 'cantidad' => $line[1], 'precio' => $line[2],
            ])->all(),
        ];
    }
}
