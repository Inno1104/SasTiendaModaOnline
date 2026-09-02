<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProveedorProductPurchaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_and_purchase_selectors_only_show_active_suppliers_from_current_store(): void
    {
        [$admin, $store] = $this->context();
        $active = $this->supplier($store, 'Proveedor disponible', true);
        $inactive = $this->supplier($store, 'Proveedor suspendido', false);
        [, $foreignStore] = $this->context();
        $foreign = $this->supplier($foreignStore, 'Proveedor extranjero', true);

        foreach ([route('productos.create'), route('compras.create')] as $route) {
            $this->actingAs($admin)->get($route)
                ->assertOk()
                ->assertSee($active->nombre)
                ->assertDontSee($inactive->nombre)
                ->assertDontSee($foreign->nombre);
        }
    }

    public function test_product_can_be_created_without_a_supplier(): void
    {
        [$admin, $store] = $this->context();
        $category = $this->category($store);

        $this->actingAs($admin)->post(
            route('productos.store'),
            $this->productPayload($category, null, 'Producto sin proveedor')
        )->assertRedirect(route('productos.index'));

        $product = Producto::query()->where('nombre', 'Producto sin proveedor')->firstOrFail();
        $this->assertNull($product->proveedor_id);
        $this->assertSame($store->id, $product->tienda_id);
    }

    public function test_active_supplier_purchase_updates_stock_and_keeps_history_after_deactivation(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store, 'Textiles históricos', true);
        [, $variant] = $this->stockedProduct($store, $supplier, 7);

        $this->actingAs($admin)->post(route('compras.store'), $this->purchasePayload($supplier, $variant, 4))
            ->assertRedirect();

        $purchase = Compra::query()->firstOrFail();
        $this->assertSame(11, $variant->fresh()->stock);
        $this->assertSame($supplier->id, $purchase->proveedor_id);
        $this->assertDatabaseHas('stock_movimientos', [
            'tienda_id' => $store->id,
            'variante_id' => $variant->id,
            'tipo' => StockMovimiento::TIPO_COMPRA,
            'cantidad' => 4,
            'stock_anterior' => 7,
            'stock_resultante' => 11,
        ]);

        $this->patch(route('proveedores.estado', $supplier), ['activo' => false])
            ->assertSessionHas('success');

        $this->get(route('compras.index'))->assertOk()->assertSee($supplier->nombre);
        $this->get(route('compras.show', $purchase))->assertOk()->assertSee($supplier->nombre);
        $this->assertSame($supplier->id, $purchase->fresh()->proveedor_id);
        $this->assertFalse($supplier->fresh()->activo);
    }

    public function test_inactive_supplier_is_rejected_without_partial_purchase_or_stock_change(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store, 'Proveedor inactivo', false);
        [, $variant] = $this->stockedProduct($store, null, 5);

        $this->actingAs($admin)->post(route('compras.store'), $this->purchasePayload($supplier, $variant, 3))
            ->assertSessionHasErrors('proveedor_id');

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('detalle_compras', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_foreign_supplier_is_rejected_for_products_and_purchases(): void
    {
        [$admin, $store] = $this->context();
        $category = $this->category($store);
        [, $variant] = $this->stockedProduct($store, null, 5);
        [, $foreignStore] = $this->context();
        $foreignSupplier = $this->supplier($foreignStore, 'Proveedor de otra tienda', true);

        $this->actingAs($admin)->post(
            route('productos.store'),
            $this->productPayload($category, $foreignSupplier, 'Producto inválido')
        )->assertSessionHasErrors('proveedor_id');
        $this->post(route('compras.store'), $this->purchasePayload($foreignSupplier, $variant, 2))
            ->assertSessionHasErrors('proveedor_id');

        $this->assertDatabaseMissing('productos', ['nombre' => 'Producto inválido']);
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $admin = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@supplier-integration.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$admin, $store];
    }

    private function supplier(Tienda $store, string $name, bool $active): Proveedor
    {
        return Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'ruc' => (string) random_int(10000000000, 99999999999),
            'activo' => $active,
        ]);
    }

    private function category(Tienda $store): Categoria
    {
        $suffix = Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'nombre_normalizado' => 'categoria '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);
    }

    /** @return array{Producto, ProductoVariante} */
    private function stockedProduct(Tienda $store, ?Proveedor $supplier, int $stock): array
    {
        $category = $this->category($store);
        $suffix = Str::upper(Str::random(8));
        $product = Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'proveedor_id' => $supplier?->id,
            'codigo' => 'PROV-'.$suffix,
            'nombre' => 'Producto '.$suffix,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => true,
            'destacado' => false,
        ]);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Azul',
            'stock' => $stock,
            'stock_minimo' => 2,
        ]);

        return [$product, $variant];
    }

    /** @return array<string, mixed> */
    private function productPayload(Categoria $category, ?Proveedor $supplier, string $name): array
    {
        return [
            'nombre' => $name,
            'categoria_id' => $category->id,
            'marca_id' => '',
            'proveedor_id' => $supplier?->id ?? '',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'descripcion' => '',
            'precio_compra' => '5.00',
            'precio_venta' => '10.00',
            'precio_oferta' => '',
            'activo' => 1,
            'destacado' => 0,
            'variantes' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function purchasePayload(Proveedor $supplier, ProductoVariante $variant, int $quantity): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'proveedor_id' => $supplier->id,
            'fecha' => today()->toDateString(),
            'items' => [[
                'variante_id' => $variant->id,
                'cantidad' => $quantity,
                'precio' => '5.00',
            ]],
        ];
    }
}
