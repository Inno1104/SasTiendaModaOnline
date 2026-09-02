<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsuarioRolesAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_detection_does_not_depend_on_role_id_one(): void
    {
        Rol::create(['nombre' => 'Rol previo']);
        $administratorRole = Rol::create(['nombre' => Rol::ADMINISTRATOR]);
        [$administrator] = $this->context($administratorRole);

        $this->assertNotSame(1, $administratorRole->id);
        $this->assertTrue($administrator->isAdmin());
        $this->actingAs($administrator)->get(route('usuarios.index'))->assertOk();
    }

    public function test_last_active_administrator_cannot_demote_self(): void
    {
        $administratorRole = Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR]);
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        [$administrator] = $this->context($administratorRole);

        $this->actingAs($administrator)->post(route('usuarios.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'rol_id' => $sellerRole->id,
            'telefono' => $administrator->telefono,
            'password' => null,
            'password_confirmation' => null,
        ])->assertSessionHasErrors('rol_id');

        $this->assertSame($administratorRole->id, $administrator->fresh()->rol_id);
        $this->assertTrue($administrator->fresh()->activo);
    }

    public function test_administrator_can_be_demoted_when_another_active_administrator_remains(): void
    {
        $administratorRole = Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR]);
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        [$administrator, $store] = $this->context($administratorRole);
        $secondAdministrator = $this->user($store, $administratorRole);

        $this->actingAs($administrator)->post(route('usuarios.update', $secondAdministrator), [
            'name' => $secondAdministrator->name,
            'email' => $secondAdministrator->email,
            'rol_id' => $sellerRole->id,
            'telefono' => null,
            'password' => null,
            'password_confirmation' => null,
        ])->assertRedirect(route('usuarios.index'))->assertSessionHasNoErrors();

        $this->assertSame($sellerRole->id, $secondAdministrator->fresh()->rol_id);
        $this->assertTrue($administrator->fresh()->isAdmin());
    }

    public function test_inactive_administrator_does_not_satisfy_last_administrator_rule(): void
    {
        $administratorRole = Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR]);
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        [$administrator, $store] = $this->context($administratorRole);
        $inactiveAdministrator = $this->user($store, $administratorRole, false);

        $this->actingAs($administrator)->post(route('usuarios.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'rol_id' => $sellerRole->id,
            'telefono' => null,
            'password' => null,
            'password_confirmation' => null,
        ])->assertSessionHasErrors('rol_id');

        $this->assertFalse($inactiveAdministrator->fresh()->activo);
        $this->assertSame($administratorRole->id, $administrator->fresh()->rol_id);
    }

    /** @return array{User, Tienda} */
    private function context(Rol $role): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);

        return [$this->user($store, $role), $store];
    }

    private function user(Tienda $store, Rol $role, bool $active = true): User
    {
        $suffix = Str::lower(Str::random(8));

        return User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => $role->nombre.' '.$suffix,
            'email' => $suffix.'@roles.test',
            'password' => 'password1',
            'activo' => $active,
            'es_super_admin' => false,
        ]);
    }
}
