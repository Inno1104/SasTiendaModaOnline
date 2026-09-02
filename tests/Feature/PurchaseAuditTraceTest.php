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
use App\Services\AuditLogger;
use App\Services\PurchaseTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PurchaseAuditTraceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reception_records_responsible_changes_and_traceability(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 4);

        $audit = Auditoria::query()->sole();
        $this->assertSame('compra.recibida', $audit->accion);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($purchase->id, $audit->auditable_id);
        $this->assertSame(4, $audit->metadata['unidades']);
        $this->assertSame('40.00', $audit->metadata['total']);

        $trace = app(PurchaseTraceService::class)->trace($purchase->load('detalles.producto', 'detalles.variante'));
        $this->assertTrue($trace['conciliado']);
        $this->assertSame(4, $trace['esperado_recibido']);
        $this->assertSame(4, $trace['registrado_recibido']);
        $this->assertSame(4, $trace['neto_registrado']);
        $this->assertSame($admin->id, $trace['movimientos']->sole()->user_id);
    }

    public function test_idempotent_repetition_does_not_duplicate_audit_or_kardex(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $operationId = (string) Str::uuid();
        $payload = $this->payload($supplier, $variant, 2, $operationId);

        $this->actingAs($admin)->post(route('compras.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('compras.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_cancellation_is_audited_and_remains_conciliated(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 3);
        $reason = 'MercaderÃ­a devuelta completamente al proveedor';

        $this->post(route('compras.cancel', $purchase), ['motivo' => $reason])
            ->assertSessionHasNoErrors();

        $audits = Auditoria::query()->orderBy('id')->get();
        $this->assertSame(['compra.recibida', 'compra.anulada'], $audits->pluck('accion')->all());
        $this->assertSame($admin->id, $audits->last()->user_id);
        $this->assertSame('recibido', $audits->last()->metadata['estado_anterior']);
        $this->assertSame('anulado', $audits->last()->metadata['estado_nuevo']);
        $this->assertSame($reason, $audits->last()->metadata['motivo']);

        $trace = app(PurchaseTraceService::class)->trace($purchase->fresh()->load('detalles.producto', 'detalles.variante'));
        $this->assertTrue($trace['conciliado']);
        $this->assertSame(3, $trace['registrado_revertido']);
        $this->assertSame(0, $trace['neto_registrado']);
    }

    public function test_trace_detects_a_kardex_difference(): void
    {
        [$admin, $store, $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 2);
        $currentStock = (int) $variant->fresh()->stock;
        StockMovimiento::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'user_id' => $admin->id,
            'tipo' => StockMovimiento::TIPO_COMPRA,
            'direccion' => 'entrada',
            'stock_anterior' => $currentStock,
            'cantidad' => 1,
            'stock_resultante' => $currentStock + 1,
            'referencia_type' => Compra::class,
            'referencia_id' => $purchase->id,
            'motivo' => 'Movimiento inconsistente simulado',
        ]);

        $trace = app(PurchaseTraceService::class)->trace($purchase->load('detalles.producto', 'detalles.variante'));

        $this->assertFalse($trace['conciliado']);
        $this->assertSame(2, $trace['esperado_recibido']);
        $this->assertSame(3, $trace['registrado_recibido']);
    }

    public function test_reception_rolls_back_purchase_stock_and_kardex_when_audit_fails(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('compras.store'), $this->payload($supplier, $variant, 2));
            $this->fail('La falla de auditorÃ­a debiÃ³ interrumpir la recepciÃ³n.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('detalle_compras', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_cancellation_rolls_back_state_stock_and_kardex_when_audit_fails(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 2);
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);

        $this->withoutExceptionHandling();
        try {
            $this->post(route('compras.cancel', $purchase), [
                'motivo' => 'DevoluciÃ³n total validada con el proveedor',
            ]);
            $this->fail('La falla de auditorÃ­a debiÃ³ interrumpir la anulaciÃ³n.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertSame('recibido', $purchase->fresh()->estado);
        $this->assertNull($purchase->fresh()->anulada_at);
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_purchase_detail_displays_reconciliation_movements_and_audit(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 2);

        $this->get(route('compras.show', $purchase))
            ->assertOk()
            ->assertSee('Conciliaci')
            ->assertSee('Historial de inventario')
            ->assertSee('Auditor')
            ->assertSee($admin->name)
            ->assertSee('compra.recibida');
    }

    /** @return array{User, Tienda, Proveedor, ProductoVariante} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $admin = User::create([
            'rol_id' => $role->id, 'tienda_id' => $store->id, 'name' => 'Admin '.$suffix,
            'email' => $suffix.'@purchase-audit.test', 'password' => 'password', 'activo' => true,
        ]);
        $supplier = Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'nombre' => 'Proveedor '.$suffix, 'activo' => true,
        ]);
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'nombre' => 'CategorÃ­a '.$suffix, 'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex', 'activo' => true,
        ]);
        $product = Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'categoria_id' => $category->id, 'codigo' => 'P-'.Str::upper($suffix),
            'nombre' => 'Producto '.$suffix, 'genero' => 'unisex', 'tipo' => 'casual',
            'precio_compra' => 10, 'precio_venta' => 20, 'activo' => true,
        ]);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'producto_id' => $product->id, 'talla' => 'M', 'color' => 'Azul',
            'stock' => 5, 'stock_minimo' => 1,
        ]);

        return [$admin, $store, $supplier, $variant];
    }

    private function receive(User $user, Proveedor $supplier, ProductoVariante $variant, int $quantity): Compra
    {
        $this->actingAs($user)->post(route('compras.store'), $this->payload($supplier, $variant, $quantity))
            ->assertSessionHasNoErrors();

        return Compra::query()->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(Proveedor $supplier, ProductoVariante $variant, int $quantity, ?string $operationId = null): array
    {
        return [
            'operacion_id' => $operationId ?? (string) Str::uuid(),
            'proveedor_id' => $supplier->id,
            'fecha' => now()->format('Y-m-d'),
            'items' => [['variante_id' => $variant->id, 'cantidad' => $quantity, 'precio' => '10.00']],
        ];
    }
}
