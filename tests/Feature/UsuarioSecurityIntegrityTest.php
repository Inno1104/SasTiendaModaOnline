<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsuarioSecurityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_user_management(): void
    {
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));
        $this->post(route('usuarios.store'))->assertRedirect(route('login'));
    }

    public function test_seller_and_warehouse_cannot_manage_users_or_escalate_privileges(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $seller = $this->user($store, 'Vendedor');
        $warehouse = $this->user($store, 'Almacén');
        $administratorRole = Rol::where('nombre', 'Administrador')->sole();

        foreach ([$seller, $warehouse] as $restrictedUser) {
            $this->actingAs($restrictedUser)->get(route('usuarios.index'))->assertForbidden();
            $this->post(route('usuarios.store'), [
                'name' => 'Administrador ilegítimo',
                'email' => Str::random(8).'@forbidden.test',
                'rol_id' => $administratorRole->id,
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])->assertForbidden();
            $this->post(route('usuarios.update', $administrator), [])->assertForbidden();
            $this->post(route('usuarios.toggle', $administrator))->assertForbidden();
        }

        $this->assertDatabaseCount('users', 3);
        $this->assertTrue($administrator->fresh()->activo);
    }

    public function test_administrator_can_manage_a_user_but_cannot_change_own_status(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        $warehouseRole = Rol::firstOrCreate(['nombre' => 'Almacén']);

        $this->actingAs($administrator)->get(route('usuarios.index'))->assertOk();
        $this->post(route('usuarios.store'), [
            'name' => 'Usuario autorizado',
            'email' => 'autorizado@users.test',
            'rol_id' => $sellerRole->id,
            'telefono' => '0999999999',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertRedirect(route('usuarios.index'));
        $managedUser = User::where('email', 'autorizado@users.test')->sole();
        $this->assertSame($store->id, $managedUser->tienda_id);

        $this->post(route('usuarios.update', $managedUser), [
            'name' => 'Usuario actualizado',
            'email' => 'autorizado@users.test',
            'rol_id' => $warehouseRole->id,
            'telefono' => null,
            'password' => null,
            'password_confirmation' => null,
        ])->assertRedirect(route('usuarios.index'));
        $this->assertSame($warehouseRole->id, $managedUser->fresh()->rol_id);

        $this->post(route('usuarios.toggle', $managedUser), [
            'activo' => false,
            'motivo' => 'Fin de relación laboral',
        ])->assertSessionHas('success');
        $this->assertFalse($managedUser->fresh()->activo);
        $this->post(route('usuarios.toggle', $administrator))->assertForbidden();
        $this->assertTrue($administrator->fresh()->activo);
    }

    public function test_users_from_another_store_are_not_disclosed_or_modified(): void
    {
        [$administrator] = $this->context('Administrador');
        [$foreignAdministrator, $foreignStore] = $this->context('Administrador');
        $foreignSeller = $this->user($foreignStore, 'Vendedor');
        $this->actingAs($administrator);

        $this->get(route('usuarios.index', ['edit' => $foreignSeller->id]))->assertNotFound();
        $this->post(route('usuarios.update', $foreignSeller), [
            'name' => 'Manipulado',
            'email' => $foreignSeller->email,
            'rol_id' => $foreignSeller->rol_id,
        ])->assertNotFound();
        $this->post(route('usuarios.toggle', $foreignSeller))->assertNotFound();

        $this->assertSame($foreignStore->id, $foreignSeller->fresh()->tienda_id);
        $this->assertTrue($foreignSeller->fresh()->activo);
        $this->assertTrue($foreignAdministrator->fresh()->activo);
    }

    public function test_malformed_edit_identifier_is_rejected_without_server_error(): void
    {
        [$administrator] = $this->context('Administrador');

        $this->actingAs($administrator)
            ->get(route('usuarios.index', ['edit' => ['invalid']]))
            ->assertNotFound();
    }

    public function test_deactivated_authenticated_user_is_logged_out_on_next_store_request(): void
    {
        [$administrator] = $this->context('Administrador');
        $administrator->update(['activo' => false]);

        $this->actingAs($administrator)->get(route('dashboard'))->assertForbidden();

        $this->assertGuest();
    }

    /** @return array{User, Tienda} */
    private function context(string $role): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);

        return [$this->user($store, $role), $store];
    }

    private function user(Tienda $store, string $role): User
    {
        $suffix = Str::lower(Str::random(8));

        return User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => $role])->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@user-security.test',
            'password' => 'password',
            'activo' => true,
            'es_super_admin' => false,
        ]);
    }
}
