<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarcaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_routes_require_authentication(): void
    {
        [, $store] = $this->context('Administrador');
        $brand = $this->brand($store);

        $this->get(route('marcas.index'))->assertRedirect(route('login'));
        $this->post(route('marcas.store'), $this->payload())->assertRedirect(route('login'));
        $this->post(route('marcas.update', $brand), $this->payload())->assertRedirect(route('login'));
        $this->post(route('marcas.destroy', $brand))->assertRedirect(route('login'));
    }

    public function test_administrator_and_warehouse_can_manage_brands(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $warehouse = $this->user($store, 'Almacén');
        $brand = $this->brand($store);

        $this->actingAs($administrator)
            ->post(route('marcas.store'), $this->payload(['nombre' => 'Nike']))
            ->assertSessionHas('success');
        $this->actingAs($warehouse)
            ->post(route('marcas.update', $brand), $this->payload(['nombre' => 'Adidas']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('marcas', ['tienda_id' => $store->id, 'nombre' => 'Nike']);
        $this->assertSame('Adidas', $brand->fresh()->nombre);
    }

    public function test_seller_has_read_only_access_without_management_actions(): void
    {
        [$seller, $store] = $this->context('Vendedor');
        $brand = $this->brand($store);

        $this->actingAs($seller)->get(route('marcas.index'))
            ->assertOk()
            ->assertDontSee('Nueva marca')
            ->assertDontSee(route('marcas.update', $brand), false)
            ->assertDontSee(route('marcas.destroy', $brand), false);

        $this->post(route('marcas.store'), $this->payload())->assertForbidden();
        $this->post(route('marcas.update', $brand), $this->payload())->assertForbidden();
        $this->post(route('marcas.destroy', $brand))->assertForbidden();
        $this->get(route('marcas.index', ['edit' => $brand->id]))->assertForbidden();
    }

    public function test_inactive_user_cannot_access_or_modify_brands(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $brand = $this->brand($store);
        $administrator->update(['activo' => false]);

        $this->actingAs($administrator)->get(route('marcas.index'))->assertForbidden();
        $this->post(route('marcas.store'), $this->payload())->assertRedirect(route('login'));
        $this->post(route('marcas.update', $brand), $this->payload())->assertRedirect(route('login'));
        $this->post(route('marcas.destroy', $brand))->assertRedirect(route('login'));
    }

    public function test_creation_trims_name_ignores_protected_fields_and_forces_current_store(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        [, $foreignStore] = $this->context('Administrador');

        $this->actingAs($administrator)->post(route('marcas.store'), $this->payload([
            'nombre' => '  Puma  ',
            'tienda_id' => $foreignStore->id,
            'logo' => 'manipulado.svg',
        ]))->assertSessionHas('success');

        $brand = Marca::query()->sole();
        $this->assertSame($store->id, $brand->tienda_id);
        $this->assertSame('Puma', $brand->nombre);
        $this->assertNull($brand->logo);
        $this->assertTrue($brand->activo);
    }

    public function test_invalid_data_does_not_create_or_partially_update_brands(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $brand = $this->brand($store);

        $this->actingAs($administrator)
            ->post(route('marcas.store'), $this->payload(['nombre' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('nombre');
        $this->post(route('marcas.update', $brand), $this->payload(['nombre' => '']))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('marcas', 1);
        $this->assertSame('Marca original', $brand->fresh()->nombre);
    }

    public function test_unchecked_active_field_deactivates_brand(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $brand = $this->brand($store);

        $this->actingAs($administrator)->post(route('marcas.update', $brand), [
            'nombre' => 'Marca original',
        ])->assertSessionHas('success');

        $this->assertFalse($brand->fresh()->activo);
    }

    public function test_route_binding_and_edit_selection_are_isolated_by_store(): void
    {
        [$administrator] = $this->context('Administrador');
        [, $foreignStore] = $this->context('Administrador');
        $foreignBrand = $this->brand($foreignStore);

        $this->actingAs($administrator)
            ->post(route('marcas.update', $foreignBrand), $this->payload())
            ->assertNotFound();
        $this->post(route('marcas.destroy', $foreignBrand))->assertNotFound();
        $this->get(route('marcas.index', ['edit' => $foreignBrand->id]))
            ->assertOk()
            ->assertDontSee('Guardar cambios');
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Marca segura',
            'activo' => true,
        ], $overrides);
    }

    /** @return array{User, Tienda} */
    private function context(string $role): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);

        return [$this->user($store, $role), $store];
    }

    private function user(Tienda $store, string $role): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => $role])->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@brand-security.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function brand(Tienda $store): Marca
    {
        return Marca::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Marca original',
            'activo' => true,
        ]);
    }
}
