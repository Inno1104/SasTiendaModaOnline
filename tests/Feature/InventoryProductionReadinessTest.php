<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\StockConteo;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_purchase_sale_and_cancellation_preserve_stock_and_kardex(): void
    {
        [$user, $store, $product, $variant, $supplier] = $this->context(5);
        $purchasePayload = $this->purchasePayload($supplier, $variant, 4);
        $purchasePayload['operacion_id'] = (string) Str::uuid();

        $this->actingAs($user)->post(route('compras.store'), $purchasePayload)->assertSessionHasNoErrors();
        $this->post(route('compras.store'), $purchasePayload)->assertSessionHasNoErrors();
        $this->assertSame(9, $variant->fresh()->stock);
        $this->assertDatabaseCount('compras', 1);

        $salePayload = $this->salePayload($product, $variant, 3);
        $salePayload['operacion_id'] = (string) Str::uuid();
        $this->post(route('ventas.store'), $salePayload)->assertSessionHasNoErrors();
        $sale = Venta::query()->sole();
        $this->post(route('ventas.store'), $salePayload)->assertRedirect(route('ventas.show', $sale));
        $this->assertSame(6, $variant->fresh()->stock);
        $this->assertDatabaseCount('ventas', 1);

        $this->post(route('ventas.anular', $sale), ['motivo' => 'Devolución completa confirmada'])
            ->assertSessionHas('success');

        $variant->refresh();
        $this->assertSame(9, $variant->stock);
        $this->assertSame(3, $variant->lock_version);
        $this->assertSame('anulada', $sale->fresh()->estado);
        $this->assertSame([
            StockMovimiento::TIPO_COMPRA,
            StockMovimiento::TIPO_VENTA,
            StockMovimiento::TIPO_ANULACION,
        ], StockMovimiento::query()->orderBy('id')->pluck('tipo')->all());
        $this->assertSame([4, -3, 3], StockMovimiento::query()->orderBy('id')->pluck('cantidad')->all());
        $this->assertSame([9, 6, 9], StockMovimiento::query()->orderBy('id')->pluck('stock_resultante')->all());
    }

    public function test_physical_count_and_authorized_reconciliation_complete_the_inventory_cycle(): void
    {
        [$administrator, , , $variant] = $this->context(12);

        $this->actingAs($administrator)->post(route('stock.count', $variant), [
            'stock_fisico' => 10,
            'expected_version' => 0,
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Conteo físico previo al cierre',
        ])->assertSessionHas('success');

        $count = StockConteo::query()->sole();
        $this->assertSame(StockConteo::ESTADO_PENDIENTE, $count->estado);
        $this->assertSame(12, $variant->fresh()->stock);

        $this->post(route('stock.reconcile', $count), [
            'motivo' => 'Diferencia física verificada por administración',
        ])->assertSessionHas('success');

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(StockConteo::ESTADO_CONCILIADO, $count->fresh()->estado);
        $this->assertDatabaseHas('stock_movimientos', [
            'variante_id' => $variant->id,
            'tipo' => StockMovimiento::TIPO_CONCILIACION,
            'stock_anterior' => 12,
            'cantidad' => -2,
            'stock_resultante' => 10,
        ]);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'stock.conciliado',
            'auditable_id' => $count->id,
            'user_id' => $administrator->id,
        ]);
    }

    public function test_competing_sale_requests_cannot_create_negative_or_duplicate_stock(): void
    {
        [$user, , $product, $variant] = $this->context(2);
        $first = $this->salePayload($product, $variant, 2);
        $first['operacion_id'] = (string) Str::uuid();
        $second = $this->salePayload($product, $variant, 1);
        $second['operacion_id'] = (string) Str::uuid();

        $this->actingAs($user)->post(route('ventas.store'), $first)->assertSessionHasNoErrors();
        $this->post(route('ventas.store'), $second)->assertSessionHasErrors('items');
        $this->post(route('ventas.store'), $first)->assertSessionHasNoErrors();

        $this->assertSame(0, $variant->fresh()->stock);
        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseCount('detalle_ventas', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
    }

    public function test_purchase_sale_and_history_reject_resources_from_another_store(): void
    {
        [$user, , $product, $variant, $supplier] = $this->context(5);
        [, , $foreignProduct, $foreignVariant, $foreignSupplier] = $this->context(5);
        $this->actingAs($user);

        $this->post(route('compras.store'), $this->purchasePayload($foreignSupplier, $variant, 1))
            ->assertSessionHasErrors('proveedor_id');
        $this->post(route('compras.store'), $this->purchasePayload($supplier, $foreignVariant, 1))
            ->assertSessionHasErrors('items.0.variante_id');
        $this->post(route('ventas.store'), $this->salePayload($foreignProduct, $foreignVariant, 1))
            ->assertSessionHasErrors(['items.0.producto_id', 'items.0.variante_id']);
        $this->get(route('stock.history', $foreignVariant))->assertNotFound();

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_inventory_schema_contains_required_tables_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('producto_variantes', ['tienda_id', 'stock', 'stock_minimo', 'lock_version']));
        $this->assertTrue(Schema::hasColumns('stock_movimientos', ['tienda_id', 'variante_id', 'tipo', 'stock_anterior', 'cantidad', 'stock_resultante']));
        $this->assertTrue(Schema::hasColumns('stock_conteos', ['tienda_id', 'variante_id', 'stock_sistema', 'stock_fisico', 'diferencia', 'version_stock', 'estado']));

        $variantIndexes = collect(Schema::getIndexes('producto_variantes'))->pluck('name');
        $movementIndexes = collect(Schema::getIndexes('stock_movimientos'))->pluck('name');
        $countIndexes = collect(Schema::getIndexes('stock_conteos'))->pluck('name');
        $this->assertContains('variantes_stock_orden_estable', $variantIndexes);
        $this->assertContains('stock_mov_tienda_variante_fecha', $movementIndexes);
        $this->assertContains('stock_mov_tienda_tipo_fecha', $movementIndexes);
        $this->assertContains('conteos_tienda_variante_fecha', $countIndexes);
        $this->assertContains('conteos_tienda_estado_fecha', $countIndexes);
    }

    /** @return array{User, Tienda, Producto, ProductoVariante, Proveedor} */
    private function context(int $stock): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa', 'igv' => 0]);
        $user = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => 'Administrador'])->id,
            'tienda_id' => $store->id, 'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@inventory-production.test', 'password' => 'password', 'activo' => true,
        ]);
        Caja::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'user_id' => $user->id, 'fecha' => today(),
            'monto_inicial' => 0, 'estado' => 'abierta', 'apertura' => now(),
        ]);
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'nombre' => 'Categoría '.$suffix,
            'nombre_normalizado' => 'categoria '.$suffix, 'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex', 'activo' => true,
        ]);
        $product = Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'categoria_id' => $category->id,
            'codigo' => 'SKU-'.Str::upper($suffix), 'nombre' => 'Producto '.$suffix,
            'genero' => 'unisex', 'tipo' => 'casual', 'precio_compra' => 5,
            'precio_venta' => 10, 'activo' => true,
        ]);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'producto_id' => $product->id,
            'talla' => 'M', 'color' => 'Negro', 'stock' => $stock, 'stock_minimo' => 2,
        ]);
        $supplier = Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'nombre' => 'Proveedor '.$suffix, 'activo' => true,
        ]);

        return [$user, $store, $product, $variant, $supplier];
    }

    private function purchasePayload(Proveedor $supplier, ProductoVariante $variant, int $quantity): array
    {
        return ['proveedor_id' => $supplier->id, 'fecha' => today()->toDateString(), 'items' => [[
            'variante_id' => $variant->id, 'cantidad' => $quantity, 'precio' => 5,
        ]]];
    }

    private function salePayload(Producto $product, ProductoVariante $variant, int $quantity): array
    {
        return ['tipo_comprobante' => 'ticket', 'metodo_pago' => 'efectivo', 'monto_pagado' => 100, 'items' => [[
            'producto_id' => $product->id, 'variante_id' => $variant->id, 'cantidad' => $quantity,
        ]]];
    }
}
