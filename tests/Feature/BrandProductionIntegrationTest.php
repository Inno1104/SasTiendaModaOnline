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

class BrandProductionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_brand_lifecycle_preserves_product_integrity(): void
    {
        [$administrator, $store] = $this->context();
        $category = $this->category($store);
        $this->actingAs($administrator);

        $this->post(route('marcas.store'), ['nombre' => '  Marca Integral  ', 'activo' => 1])
            ->assertRedirect(route('marcas.index'))
            ->assertSessionHas('success');
        $brand = Marca::where('nombre_normalizado', 'marca integral')->firstOrFail();

        $this->post(route('productos.store'), $this->productPayload($category, $brand->id))
            ->assertSessionHas('success');
        $product = Producto::where('marca_id', $brand->id)->firstOrFail();

        $this->post(route('marcas.update', $brand), ['nombre' => 'Marca Integral', 'activo' => 0])
            ->assertSessionHas('success');
        $this->assertFalse($brand->fresh()->activo);
        $this->assertSame($brand->id, $product->fresh()->marca_id);

        $this->post(route('marcas.destroy', $brand))
            ->assertSessionHasErrors('marca');
        $this->assertDatabaseHas('marcas', ['id' => $brand->id]);

        $this->put(route('productos.update', $product), $this->productPayload($category, null))
            ->assertSessionHas('success');
        $this->assertNull($product->fresh()->marca_id);

        $this->post(route('marcas.destroy', $brand))->assertSessionHas('success');
        $this->assertDatabaseMissing('marcas', ['id' => $brand->id]);
        $this->assertDatabaseHas('productos', [
            'id' => $product->id,
            'tienda_id' => $store->id,
            'marca_id' => null,
        ]);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $user = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => 'Administrador'])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@brand-production.test',
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

    private function productPayload(Categoria $category, ?int $brandId): array
    {
        return [
            'nombre' => 'Producto integrado',
            'categoria_id' => $category->id,
            'marca_id' => $brandId ?? '',
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
        ];
    }
}
