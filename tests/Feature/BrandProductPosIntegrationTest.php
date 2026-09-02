<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrandProductPosIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_brand_cannot_be_assigned_to_a_new_product(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $inactiveBrand = $this->brand($store, 'Marca inactiva', false);

        $this->actingAs($user)->post(route('productos.store'), $this->productPayload($category, $inactiveBrand))
            ->assertSessionHasErrors('marca_id');

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_product_can_keep_its_inactive_brand_and_edit_form_explains_its_state(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $inactiveBrand = $this->brand($store, 'Marca histórica', false);
        $product = $this->product($store, $category, $inactiveBrand);

        $this->actingAs($user)->get(route('productos.edit', $product))
            ->assertOk()
            ->assertSee('Marca histórica (Inactiva)');

        $this->put(route('productos.update', $product), $this->productPayload($category, $inactiveBrand, [
            'nombre' => 'Producto corregido',
        ]))->assertSessionHas('success');

        $this->assertSame($inactiveBrand->id, $product->fresh()->marca_id);
        $this->assertSame('Producto corregido', $product->fresh()->nombre);
    }

    public function test_product_cannot_switch_to_another_inactive_brand_but_can_use_an_active_one(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $currentBrand = $this->brand($store, 'Marca actual', true);
        $inactiveBrand = $this->brand($store, 'Marca retirada', false);
        $activeBrand = $this->brand($store, 'Marca disponible', true);
        $product = $this->product($store, $category, $currentBrand);

        $this->actingAs($user)
            ->put(route('productos.update', $product), $this->productPayload($category, $inactiveBrand))
            ->assertSessionHasErrors('marca_id');
        $this->assertSame($currentBrand->id, $product->fresh()->marca_id);

        $this->put(route('productos.update', $product), $this->productPayload($category, $activeBrand))
            ->assertSessionHas('success');
        $this->assertSame($activeBrand->id, $product->fresh()->marca_id);
    }

    public function test_pos_sells_existing_product_with_inactive_brand_but_does_not_offer_it_as_filter(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $inactiveBrand = $this->brand($store, 'Marca histórica', false);
        $product = $this->product($store, $category, $inactiveBrand);

        $this->actingAs($user)->getJson(route('ventas.catalogo'))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $product->id,
                'marca' => 'Marca histórica',
            ]);

        $this->getJson(route('ventas.catalogo', ['marca_id' => $inactiveBrand->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('marca_id');

        $this->get(route('ventas.create'))
            ->assertOk()
            ->assertDontSee('<option value="'.$inactiveBrand->id.'">Marca histórica</option>', false);
        $this->get(route('productos.create'))
            ->assertOk()
            ->assertDontSee('Marca histórica');
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@brand-product-pos.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
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

    private function brand(Tienda $store, string $name, bool $active): Marca
    {
        return Marca::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => Str::lower(Str::squish($name)),
            'activo' => $active,
        ]);
    }

    private function product(Tienda $store, Categoria $category, Marca $brand): Producto
    {
        $suffix = Str::upper(Str::random(8));

        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'marca_id' => $brand->id,
            'codigo' => 'PROD-'.$suffix,
            'nombre' => 'Producto '.$suffix,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => true,
            'destacado' => false,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function productPayload(Categoria $category, Marca $brand, array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Producto de prueba',
            'categoria_id' => $category->id,
            'marca_id' => $brand->id,
            'proveedor_id' => '',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'descripcion' => '',
            'precio_compra' => '5.00',
            'precio_venta' => '10.00',
            'precio_oferta' => '',
            'activo' => true,
            'destacado' => false,
            'variantes' => [],
        ], $overrides);
    }
}
