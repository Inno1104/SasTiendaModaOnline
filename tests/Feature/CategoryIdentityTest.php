<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_equivalent_names_are_rejected_inside_the_same_store(): void
    {
        [$user, $store] = $this->context();

        $this->actingAs($user)->post(route('categorias.store'), $this->payload('  Ropa   Deportiva  '))
            ->assertSessionHas('success');

        $this->post(route('categorias.store'), $this->payload('ropa deportiva'))
            ->assertSessionHasErrors('nombre');

        $this->assertSame(1, Categoria::withoutGlobalScopes()->where('tienda_id', $store->id)->count());
        $this->assertDatabaseHas('categorias', [
            'tienda_id' => $store->id,
            'nombre_normalizado' => 'ropa deportiva',
        ]);
    }

    public function test_two_stores_can_use_the_same_name_and_slug(): void
    {
        [$firstUser, $firstStore] = $this->context();
        [$secondUser, $secondStore] = $this->context();

        $this->actingAs($firstUser)->post(route('categorias.store'), $this->payload('Camisas'))
            ->assertSessionHas('success');
        $this->actingAs($secondUser)->post(route('categorias.store'), $this->payload('Camisas'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categorias', ['tienda_id' => $firstStore->id, 'slug' => 'camisas']);
        $this->assertDatabaseHas('categorias', ['tienda_id' => $secondStore->id, 'slug' => 'camisas']);
    }

    public function test_slug_collisions_are_resolved_only_inside_the_current_store(): void
    {
        [$user, $store] = $this->context();

        $this->actingAs($user)->post(route('categorias.store'), $this->payload('Ropa Niño'));
        $this->post(route('categorias.store'), $this->payload('Ropa-Nino'));

        $this->assertDatabaseHas('categorias', ['tienda_id' => $store->id, 'slug' => 'ropa-nino']);
        $this->assertDatabaseHas('categorias', ['tienda_id' => $store->id, 'slug' => 'ropa-nino-2']);
    }

    public function test_update_keeps_its_identity_but_rejects_another_category_name(): void
    {
        [$user, $store] = $this->context();
        $first = $this->category($store, 'Vestidos', 'vestidos');
        $second = $this->category($store, 'Calzado', 'calzado');

        $this->actingAs($user)->post(route('categorias.update', $first), $this->payload('Vestidos'))
            ->assertSessionHas('success');
        $this->post(route('categorias.update', $first), $this->payload('  CALZADO '))
            ->assertSessionHasErrors('nombre');

        $this->assertSame('Vestidos', $first->fresh()->nombre);
        $this->assertSame('calzado', $second->fresh()->slug);
    }

    public function test_database_prevents_duplicate_normalized_names_in_one_store(): void
    {
        [, $store] = $this->context();
        $this->category($store, 'Accesorios', 'accesorios');

        $this->expectException(QueryException::class);
        $this->category($store, 'ACCESORIOS', 'otros-accesorios', 'accesorios');
    }

    public function test_database_prevents_duplicate_slugs_in_one_store(): void
    {
        [, $store] = $this->context();
        $this->category($store, 'Ropa', 'ropa');

        $this->expectException(QueryException::class);
        $this->category($store, 'Prendas', 'ropa');
    }

    private function payload(string $name): array
    {
        return [
            'nombre' => $name,
            'genero' => 'unisex',
            'descripcion' => 'Categoría de prueba',
            'activo' => 1,
        ];
    }

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
            'email' => $suffix.'@category-identity.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function category(
        Tienda $store,
        string $name,
        string $slug,
        ?string $normalizedName = null
    ): Categoria {
        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => $normalizedName ?? Str::lower(Str::squish($name)),
            'slug' => $slug,
            'genero' => 'unisex',
            'activo' => true,
        ]);
    }
}
