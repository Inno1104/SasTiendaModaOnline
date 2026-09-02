<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_routes_require_authentication(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $customer = $this->customer($store, 'AUTH');

        $this->get(route('clientes.index'))->assertRedirect(route('login'));
        $this->get(route('clientes.show', $customer))->assertRedirect(route('login'));
        $this->post(route('clientes.store'), $this->validPayload())->assertRedirect(route('login'));
    }

    public function test_administrators_and_sellers_can_manage_customers(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $seller = $this->user($store, 'Vendedor');

        $this->actingAs($administrator)->get(route('clientes.index'))->assertOk();
        $this->actingAs($seller)->get(route('clientes.create'))->assertOk();
    }

    public function test_other_roles_and_inactive_users_cannot_manage_customers(): void
    {
        [$warehouseUser] = $this->userWithStore('Almacén');
        [$inactiveAdministrator] = $this->userWithStore('Administrador');
        $inactiveAdministrator->update(['activo' => false]);

        $this->actingAs($warehouseUser)->get(route('clientes.index'))->assertForbidden();
        $this->post(route('clientes.store'), $this->validPayload())->assertForbidden();

        $this->actingAs($inactiveAdministrator)->get(route('clientes.index'))->assertForbidden();
    }

    public function test_creation_only_persists_validated_fields_and_assigns_current_store(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        [, $foreignStore] = $this->userWithStore('Administrador');

        $response = $this->actingAs($administrator)->post(route('clientes.store'), $this->validPayload() + [
            'tienda_id' => $foreignStore->id,
            'codigo' => 'INJECTED',
            'puntos' => 9000,
            'activo' => false,
        ]);

        $response->assertRedirect(route('clientes.index'))->assertSessionHas('success');
        $customer = Cliente::withoutGlobalScopes()->where('email', 'ana@example.com')->firstOrFail();

        $this->assertSame($store->id, $customer->tienda_id);
        $this->assertNotSame('INJECTED', $customer->codigo);
        $this->assertEquals(0, $customer->puntos);
        $this->assertTrue((bool) $customer->activo);
    }

    public function test_creation_normalizes_customer_identity_data(): void
    {
        [$administrator] = $this->userWithStore('Administrador');

        $this->actingAs($administrator)->post(route('clientes.store'), $this->validPayload([
            'nombre' => '  Ana  ',
            'apellido' => '  Pérez ',
            'email' => '  ANA@EXAMPLE.COM ',
        ]))->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'email' => 'ana@example.com',
        ]);
    }

    public function test_creation_rejects_invalid_customer_data_without_partial_records(): void
    {
        [$administrator] = $this->userWithStore('Administrador');

        $this->actingAs($administrator)->post(route('clientes.store'), [
            'nombre' => '',
            'apellido' => '',
            'email' => 'invalid-email',
            'fecha_nacimiento' => now()->addDay()->toDateString(),
            'genero' => 'invalid',
        ])->assertSessionHasErrors(['nombre', 'apellido', 'email', 'fecha_nacimiento', 'genero']);

        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_update_ignores_protected_fields_and_validates_unique_document(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $customer = $this->customer($store, 'ONE', ['dni' => 'DOC-ONE']);
        $otherCustomer = $this->customer($store, 'TWO', ['dni' => 'DOC-TWO']);
        [, $foreignStore] = $this->userWithStore('Administrador');

        $this->actingAs($administrator)->put(route('clientes.update', $customer), $this->validPayload([
            'dni' => $otherCustomer->dni,
        ]))->assertSessionHasErrors('dni');

        $this->put(route('clientes.update', $customer), $this->validPayload([
            'nombre' => 'Actualizada',
            'dni' => $customer->dni,
        ]) + [
            'tienda_id' => $foreignStore->id,
            'codigo' => 'CHANGED',
            'puntos' => 500,
            'activo' => false,
        ])->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('Actualizada', $customer->nombre);
        $this->assertSame($store->id, $customer->tienda_id);
        $this->assertSame('CLI-ONE', $customer->codigo);
        $this->assertEquals(0, $customer->puntos);
        $this->assertTrue((bool) $customer->activo);
    }

    public function test_customer_queries_and_route_binding_are_isolated_by_store(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        [, $foreignStore] = $this->userWithStore('Administrador');
        $localCustomer = $this->customer($store, 'LOCAL');
        $foreignCustomer = $this->customer($foreignStore, 'FOREIGN');

        $this->actingAs($administrator)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee($localCustomer->nombre)
            ->assertDontSee($foreignCustomer->nombre);

        $this->get(route('clientes.show', $foreignCustomer))->assertNotFound();
        $this->put(route('clientes.update', $foreignCustomer), $this->validPayload())->assertNotFound();
        $this->patch(route('clientes.estado', $foreignCustomer), [
            'activo' => false,
            'motivo' => 'Intento desde otra tienda',
        ])->assertNotFound();
    }

    public function test_administrator_can_deactivate_customer_without_deleting_it(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $customer = $this->customer($store, 'DEACTIVATE');

        $this->actingAs($administrator)->patch(route('clientes.estado', $customer), [
            'activo' => false,
            'motivo' => 'Solicitud del titular',
        ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'id' => $customer->id,
            'tienda_id' => $store->id,
            'activo' => false,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'dni' => 'DOC-100',
            'email' => 'ana@example.com',
            'telefono' => '0999999999',
            'direccion' => 'Calle Principal 123',
            'ciudad' => 'Quito',
            'fecha_nacimiento' => '1995-05-10',
            'genero' => 'F',
        ], $overrides);
    }

    private function userWithStore(string $role): array
    {
        $suffix = uniqid();
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);

        return [$this->user($store, $role), $store];
    }

    private function user(Tienda $store, string $role): User
    {
        $roleModel = Rol::firstOrCreate(['nombre' => $role]);
        $suffix = uniqid();

        return User::create([
            'rol_id' => $roleModel->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@clientes.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function customer(Tienda $store, string $suffix, array $overrides = []): Cliente
    {
        return Cliente::withoutGlobalScopes()->create(array_replace([
            'tienda_id' => $store->id,
            'codigo' => 'CLI-'.$suffix,
            'nombre' => 'Cliente '.$suffix,
            'apellido' => 'Prueba',
            'email' => mb_strtolower($suffix).'@customer.test',
            'activo' => true,
        ], $overrides));
    }
}
