<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoriaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_routes_require_authentication(): void
    {
        [, $store] = $this->context('Administrador');
        $category = $this->category($store);

        $this->get(route('categorias.index'))->assertRedirect(route('login'));
        $this->post(route('categorias.store'), $this->payload())->assertRedirect(route('login'));
        $this->post(route('categorias.update', $category), $this->payload())->assertRedirect(route('login'));
        $this->post(route('categorias.destroy', $category))->assertRedirect(route('login'));
    }

    public function test_administrator_and_warehouse_can_manage_categories(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $warehouse = $this->user($store, 'Almacén');
        $category = $this->category($store);

        $this->actingAs($administrator)->post(route('categorias.store'), $this->payload(['nombre' => 'Camisas']))
            ->assertSessionHas('success');
        $this->actingAs($warehouse)->post(route('categorias.update', $category), $this->payload(['nombre' => 'Ropa']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categorias', ['tienda_id' => $store->id, 'nombre' => 'Camisas']);
        $this->assertSame('Ropa', $category->fresh()->nombre);
    }

    public function test_seller_has_read_only_access_and_does_not_see_management_actions(): void
    {
        [$seller, $store] = $this->context('Vendedor');
        $category = $this->category($store);

        $this->actingAs($seller)->get(route('categorias.index'))
            ->assertOk()
            ->assertDontSee('<form method="POST" action="'.route('categorias.store').'"', false)
            ->assertDontSee('<form method="POST" action="'.route('categorias.update', $category).'"', false);

        $this->post(route('categorias.store'), $this->payload())->assertForbidden();
        $this->post(route('categorias.update', $category), $this->payload())->assertForbidden();
        $this->post(route('categorias.destroy', $category))->assertForbidden();
        $this->get(route('categorias.index', ['edit' => $category->id]))->assertForbidden();
    }

    public function test_inactive_users_cannot_access_or_modify_categories(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $category = $this->category($store);
        $administrator->update(['activo' => false]);

        $this->actingAs($administrator)->get(route('categorias.index'))->assertForbidden();
        $this->post(route('categorias.store'), $this->payload())->assertRedirect(route('login'));
        $this->post(route('categorias.update', $category), $this->payload())->assertRedirect(route('login'));
        $this->post(route('categorias.destroy', $category))->assertRedirect(route('login'));
    }

    public function test_creation_normalizes_data_ignores_protected_fields_and_forces_current_store(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        [, $foreignStore] = $this->context('Administrador');

        $this->actingAs($administrator)->post(route('categorias.store'), $this->payload([
            'nombre' => '  Vestidos  ',
            'descripcion' => '  Colección formal  ',
            'tienda_id' => $foreignStore->id,
            'slug' => 'slug-manipulado',
            'imagen' => 'archivo-manipulado.jpg',
        ]))->assertSessionHas('success');

        $category = Categoria::firstOrFail();
        $this->assertSame($store->id, $category->tienda_id);
        $this->assertSame('Vestidos', $category->nombre);
        $this->assertSame('Colección formal', $category->descripcion);
        $this->assertSame('vestidos', $category->slug);
        $this->assertNull($category->imagen);
    }

    public function test_invalid_data_does_not_create_or_update_partial_records(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $category = $this->category($store);

        $this->actingAs($administrator)->post(route('categorias.store'), $this->payload([
            'nombre' => str_repeat('a', 101),
            'genero' => 'invalido',
            'descripcion' => str_repeat('b', 256),
        ]))->assertSessionHasErrors(['nombre', 'genero', 'descripcion']);

        $this->post(route('categorias.update', $category), $this->payload(['nombre' => '']))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('categorias', 1);
        $this->assertSame('Categoría original', $category->fresh()->nombre);
    }

    public function test_route_binding_and_edit_selection_are_isolated_by_store(): void
    {
        [$administrator] = $this->context('Administrador');
        [, $foreignStore] = $this->context('Administrador');
        $foreignCategory = $this->category($foreignStore);

        $this->actingAs($administrator)->post(route('categorias.update', $foreignCategory), $this->payload())
            ->assertNotFound();
        $this->post(route('categorias.destroy', $foreignCategory))->assertNotFound();
        $this->get(route('categorias.index', ['edit' => $foreignCategory->id]))
            ->assertOk()
            ->assertDontSee('Guardar cambios');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Categoría segura',
            'genero' => 'unisex',
            'descripcion' => 'Descripción',
            'activo' => 1,
        ], $overrides);
    }

    private function context(string $role): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);

        return [$this->user($store, $role), $store];
    }

    private function user(Tienda $store, string $role): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => $role])->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@category-security.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function category(Tienda $store): Categoria
    {
        $suffix = Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría original',
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);
    }
}
