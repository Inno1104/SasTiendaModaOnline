<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProveedorStateDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivation_preserves_products_and_historical_purchases(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store);
        $product = $this->product($store, $supplier);
        $purchase = $this->purchase($store, $supplier, $admin);

        $this->actingAs($admin)->patch(route('proveedores.estado', $supplier), ['activo' => false])
            ->assertSessionHas('success');

        $this->assertFalse($supplier->fresh()->activo);
        $this->assertSame($supplier->id, $product->fresh()->proveedor_id);
        $this->assertSame($supplier->id, $purchase->fresh()->proveedor_id);
        $this->assertDatabaseHas('productos', ['id' => $product->id]);
        $this->assertDatabaseHas('compras', ['id' => $purchase->id]);
    }

    public function test_inactive_supplier_can_be_reactivated(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store, false);

        $this->actingAs($admin)->patch(route('proveedores.estado', $supplier), ['activo' => true])
            ->assertSessionHas('success');

        $this->assertTrue($supplier->fresh()->activo);
    }

    public function test_repeated_state_and_data_updates_do_not_touch_the_record(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store, false);
        $supplier->timestamps = false;
        $supplier->forceFill(['updated_at' => now()->subMinute()])->save();
        $originalUpdatedAt = $supplier->fresh()->updated_at;

        $this->actingAs($admin)->patch(route('proveedores.estado', $supplier), ['activo' => false])
            ->assertSessionHas('success');
        $this->put(route('proveedores.update', $supplier), $this->supplierPayload($supplier))
            ->assertSessionHas('success');

        $this->assertTrue($originalUpdatedAt->equalTo($supplier->fresh()->updated_at));
    }

    public function test_inactive_supplier_is_rejected_for_new_products_and_purchases(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store, false);
        $category = $this->category($store);

        $this->actingAs($admin)->post(route('productos.store'), $this->productPayload($category, $supplier))
            ->assertSessionHasErrors('proveedor_id');
        $this->post(route('compras.store'), [
            'proveedor_id' => $supplier->id,
            'fecha' => today()->toDateString(),
            'items' => [['variante_id' => null, 'cantidad' => 1, 'precio' => 10]],
        ])->assertSessionHasErrors('proveedor_id');

        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('compras', 0);
    }

    public function test_existing_product_can_keep_its_inactive_supplier(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store, false);
        $product = $this->product($store, $supplier);

        $this->actingAs($admin)->get(route('productos.edit', $product))
            ->assertOk()
            ->assertSee($supplier->nombre)
            ->assertSee('(Inactivo)');
        $this->put(route('productos.update', $product), $this->productPayload($product->categoria, $supplier, 'Producto actualizado'))
            ->assertSessionDoesntHaveErrors('proveedor_id');

        $this->assertSame($supplier->id, $product->fresh()->proveedor_id);
    }

    public function test_index_shows_dependencies_before_state_change(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store);
        $this->product($store, $supplier);
        $this->purchase($store, $supplier, $admin);

        $this->actingAs($admin)->get(route('proveedores.index'))
            ->assertOk()
            ->assertSeeText('1 productos')
            ->assertSeeText('1 compras')
            ->assertSee('data-products="1"', false)
            ->assertSee('data-purchases="1"', false);
    }

    public function test_state_change_requires_permission_and_store_ownership(): void
    {
        [$seller, $store] = $this->context('Vendedor');
        $supplier = $this->supplier($store);
        [$admin] = $this->context();

        $this->actingAs($seller)->patch(route('proveedores.estado', $supplier), ['activo' => false])
            ->assertForbidden();
        $this->actingAs($admin)->patch(route('proveedores.estado', $supplier), ['activo' => false])
            ->assertNotFound();

        $this->assertTrue($supplier->fresh()->activo);
    }

    /** @return array{User, Tienda} */
    private function context(string $roleName = 'Administrador'): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => $roleName]);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => $roleName.' '.$suffix,
            'email' => $suffix.'@supplier-state.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function supplier(Tienda $store, bool $active = true): Proveedor
    {
        return Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Proveedor principal',
            'ruc' => (string) random_int(10000000000, 99999999999),
            'email' => 'ventas@example.test',
            'telefono' => '0999999999',
            'direccion' => 'Calle principal 123',
            'ciudad' => 'Quito',
            'pais' => 'Ecuador',
            'contacto' => 'Contacto principal',
            'activo' => $active,
        ]);
    }

    /** @return array<string, mixed> */
    private function supplierPayload(Proveedor $supplier): array
    {
        return $supplier->only(['nombre', 'ruc', 'email', 'telefono', 'direccion', 'ciudad', 'pais', 'contacto']);
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

    private function product(Tienda $store, Proveedor $supplier): Producto
    {
        $category = $this->category($store);
        $suffix = Str::upper(Str::random(8));

        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'proveedor_id' => $supplier->id,
            'codigo' => 'PROV-'.$suffix,
            'nombre' => 'Producto '.$suffix,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => true,
            'destacado' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function productPayload(Categoria $category, Proveedor $supplier, string $name = 'Producto nuevo'): array
    {
        return [
            'nombre' => $name,
            'categoria_id' => $category->id,
            'marca_id' => '',
            'proveedor_id' => $supplier->id,
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

    private function purchase(Tienda $store, Proveedor $supplier, User $user): Compra
    {
        return Compra::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'numero_compra' => 'C-TEST-'.Str::upper(Str::random(8)),
            'proveedor_id' => $supplier->id,
            'user_id' => $user->id,
            'fecha' => today(),
            'total' => 10,
            'estado' => 'recibido',
        ]);
    }
}
