<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Subcategoria;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryStateDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_with_active_product_cannot_be_deactivated(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $this->product($store, $category, true);

        $this->actingAs($user)->post(route('categorias.update', $category), $this->categoryPayload(['activo' => 0]))
            ->assertSessionHasErrors('activo');

        $this->assertTrue($category->fresh()->activo);
    }

    public function test_category_with_active_subcategory_cannot_be_deactivated(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $this->subcategory($store, $category, true);

        $this->actingAs($user)->post(route('categorias.update', $category), $this->categoryPayload(['activo' => 0]))
            ->assertSessionHasErrors('activo');

        $this->assertTrue($category->fresh()->activo);
    }

    public function test_category_can_be_deactivated_after_dependencies_are_inactive_and_reactivated(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $this->product($store, $category, false);
        $this->subcategory($store, $category, false);

        $this->actingAs($user)->post(route('categorias.update', $category), $this->categoryPayload(['activo' => 0]))
            ->assertSessionHas('success');
        $this->assertFalse($category->fresh()->activo);

        $this->post(route('categorias.update', $category), $this->categoryPayload(['activo' => 1]))
            ->assertSessionHas('success');
        $this->assertTrue($category->fresh()->activo);
    }

    public function test_category_with_any_product_cannot_be_deleted(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $product = $this->product($store, $category, false);

        $this->actingAs($user)->post(route('categorias.destroy', $category))
            ->assertSessionHasErrors('categoria');

        $this->assertDatabaseHas('categorias', ['id' => $category->id]);
        $this->assertDatabaseHas('productos', ['id' => $product->id]);
    }

    public function test_category_with_any_subcategory_cannot_be_deleted(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $subcategory = $this->subcategory($store, $category, false);

        $this->actingAs($user)->post(route('categorias.destroy', $category))
            ->assertSessionHasErrors('categoria');

        $this->assertDatabaseHas('categorias', ['id' => $category->id]);
        $this->assertDatabaseHas('subcategorias', ['id' => $subcategory->id]);
    }

    public function test_empty_category_can_be_deleted_without_affecting_another_store(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        [, $foreignStore] = $this->context();
        $foreignCategory = $this->category($foreignStore);

        $this->actingAs($user)->post(route('categorias.destroy', $category))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('categorias', ['id' => $category->id]);
        $this->assertDatabaseHas('categorias', ['id' => $foreignCategory->id]);
    }

    public function test_inactive_category_cannot_receive_active_product_and_is_hidden_from_pos(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store, false);
        $legacyProduct = $this->product($store, $category, true);

        $this->actingAs($user)->post(route('productos.store'), $this->productPayload($category))
            ->assertSessionHasErrors('categoria_id');

        $response = $this->getJson(route('ventas.catalogo'))->assertOk();
        $this->assertNotContains($legacyProduct->id, collect($response->json('data'))->pluck('id')->all());
        $this->assertDatabaseCount('productos', 1);
    }

    public function test_inactive_product_can_keep_its_inactive_category_while_being_edited(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store, false);
        $product = $this->product($store, $category, false);

        $this->actingAs($user)->put(route('productos.update', $product), $this->productPayload($category, [
            'nombre' => 'Producto corregido',
            'activo' => 0,
        ]))->assertSessionHas('success');

        $this->assertSame('Producto corregido', $product->fresh()->nombre);
        $this->assertFalse($product->fresh()->activo);
    }

    private function categoryPayload(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Categoría principal',
            'genero' => 'unisex',
            'descripcion' => '',
            'activo' => 1,
        ], $overrides);
    }

    private function productPayload(Categoria $category, array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Producto nuevo',
            'categoria_id' => $category->id,
            'marca_id' => '',
            'proveedor_id' => '',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'descripcion' => '',
            'precio_compra' => '5.00',
            'precio_venta' => '10.00',
            'precio_oferta' => '',
            'activo' => 1,
            'destacado' => 0,
            'variantes' => [],
        ], $overrides);
    }

    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@category-state.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function category(Tienda $store, bool $active = true): Categoria
    {
        $suffix = Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'nombre_normalizado' => 'categoría '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => $active,
        ]);
    }

    private function product(Tienda $store, Categoria $category, bool $active): Producto
    {
        $suffix = Str::upper(Str::random(8));

        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'PROD-'.$suffix,
            'nombre' => 'Producto '.$suffix,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => $active,
            'destacado' => false,
        ]);
    }

    private function subcategory(Tienda $store, Categoria $category, bool $active): Subcategoria
    {
        $suffix = Str::lower(Str::random(8));

        return Subcategoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'nombre' => 'Subcategoría '.$suffix,
            'slug' => 'subcategoria-'.$suffix,
            'activo' => $active,
        ]);
    }
}
