<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryProductionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_category_lifecycle_preserves_product_integrity(): void
    {
        [$administrator, $store] = $this->context();
        $this->actingAs($administrator);

        $this->post(route('categorias.store'), $this->categoryPayload('Temporada'))
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('success');
        $category = Categoria::where('nombre_normalizado', 'temporada')->firstOrFail();

        $this->post(route('categorias.store'), $this->categoryPayload('Archivo'))
            ->assertSessionHas('success');
        $destination = Categoria::where('nombre_normalizado', 'archivo')->firstOrFail();

        $this->post(route('productos.store'), $this->productPayload($category))
            ->assertSessionHas('success');
        $product = Producto::where('categoria_id', $category->id)->firstOrFail();

        $this->post(route('categorias.update', $category), $this->categoryPayload('Temporada', false))
            ->assertSessionHasErrors('activo');
        $this->post(route('categorias.destroy', $category))
            ->assertSessionHasErrors('categoria');
        $this->assertTrue($category->fresh()->activo);
        $this->assertTrue($product->fresh()->activo);

        $this->delete(route('productos.destroy', $product))->assertSessionHas('success');
        $this->put(route('productos.update', $product), $this->productPayload($destination, false))
            ->assertSessionHas('success');

        $this->post(route('categorias.update', $category), $this->categoryPayload('Temporada', false))
            ->assertSessionHas('success');
        $this->post(route('categorias.destroy', $category))->assertSessionHas('success');

        $this->assertDatabaseMissing('categorias', ['id' => $category->id]);
        $this->assertDatabaseHas('categorias', ['id' => $destination->id, 'tienda_id' => $store->id]);
        $this->assertDatabaseHas('productos', [
            'id' => $product->id,
            'categoria_id' => $destination->id,
            'activo' => false,
        ]);
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
            'email' => $suffix.'@category-production.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function categoryPayload(string $name, bool $active = true): array
    {
        return [
            'nombre' => $name,
            'genero' => 'unisex',
            'descripcion' => 'Categoría para integración',
            'activo' => $active ? 1 : 0,
        ];
    }

    private function productPayload(Categoria $category, bool $active = true): array
    {
        return [
            'nombre' => 'Producto integrado',
            'categoria_id' => $category->id,
            'marca_id' => '',
            'proveedor_id' => '',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'descripcion' => '',
            'precio_compra' => '5.00',
            'precio_venta' => '10.00',
            'precio_oferta' => '',
            'activo' => $active ? 1 : 0,
            'destacado' => 0,
            'variantes' => [],
        ];
    }
}
