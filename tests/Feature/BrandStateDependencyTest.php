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

class BrandStateDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_with_active_products_can_be_deactivated_without_changing_products(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store);
        $product = $this->product($store, $brand, true);

        $this->actingAs($user)->post(route('marcas.update', $brand), $this->payload(false))
            ->assertSessionHas('success');

        $this->assertFalse($brand->fresh()->activo);
        $this->assertTrue($product->fresh()->activo);
        $this->assertSame($brand->id, $product->fresh()->marca_id);
    }

    public function test_inactive_brand_can_be_reactivated(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store, false);

        $this->actingAs($user)->post(route('marcas.update', $brand), $this->payload(true))
            ->assertSessionHas('success');

        $this->assertTrue($brand->fresh()->activo);
    }

    public function test_brand_with_active_product_cannot_be_deleted(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store);
        $product = $this->product($store, $brand, true);

        $this->actingAs($user)->post(route('marcas.destroy', $brand))
            ->assertSessionHasErrors('marca');

        $this->assertDatabaseHas('marcas', ['id' => $brand->id, 'activo' => true]);
        $this->assertDatabaseHas('productos', ['id' => $product->id, 'marca_id' => $brand->id]);
    }

    public function test_brand_with_inactive_product_cannot_be_deleted(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store, false);
        $product = $this->product($store, $brand, false);

        $this->actingAs($user)->post(route('marcas.destroy', $brand))
            ->assertSessionHasErrors('marca');

        $this->assertDatabaseHas('marcas', ['id' => $brand->id, 'activo' => false]);
        $this->assertDatabaseHas('productos', ['id' => $product->id]);
    }

    public function test_empty_brand_can_be_deleted_without_affecting_another_store(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store);
        [, $foreignStore] = $this->context();
        $foreignBrand = $this->brand($foreignStore);

        $this->actingAs($user)->post(route('marcas.destroy', $brand))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('marcas', ['id' => $brand->id]);
        $this->assertDatabaseHas('marcas', ['id' => $foreignBrand->id]);
    }

    public function test_repeated_state_submission_remains_idempotent(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store, false);
        $originalUpdatedAt = $brand->updated_at;

        $this->actingAs($user)->post(route('marcas.update', $brand), $this->payload(false))
            ->assertSessionHas('success');

        $freshBrand = $brand->fresh();
        $this->assertFalse($freshBrand->activo);
        $this->assertTrue($originalUpdatedAt->equalTo($freshBrand->updated_at));
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
            'email' => $suffix.'@brand-state.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function payload(bool $active): array
    {
        return [
            'nombre' => 'Marca principal',
            'activo' => $active,
        ];
    }

    private function brand(Tienda $store, bool $active = true): Marca
    {
        return Marca::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Marca principal',
            'nombre_normalizado' => 'marca principal',
            'activo' => $active,
        ]);
    }

    private function product(Tienda $store, Marca $brand, bool $active): Producto
    {
        $suffix = Str::upper(Str::random(8));
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'nombre_normalizado' => 'categoria '.Str::lower($suffix),
            'slug' => 'categoria-'.Str::lower($suffix),
            'genero' => 'unisex',
            'activo' => true,
        ]);

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
            'activo' => $active,
            'destacado' => false,
        ]);
    }
}
