<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsuarioIdentificationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_normalizes_identity_and_securely_hashes_password(): void
    {
        [$administrator, $store] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);

        $this->actingAs($administrator)->post(route('usuarios.store'), [
            'name' => '  María    Pérez  ',
            'email' => '  MARIA.PEREZ@EXAMPLE.COM  ',
            'rol_id' => $sellerRole->id,
            'telefono' => '+593 (99) 123-4567',
            'password' => 'segura123',
            'password_confirmation' => 'segura123',
        ])->assertRedirect(route('usuarios.index'))->assertSessionHasNoErrors();

        $user = User::where('email', 'maria.perez@example.com')->sole();
        $this->assertSame($store->id, $user->tienda_id);
        $this->assertSame('María Pérez', $user->name);
        $this->assertSame('+593991234567', $user->telefono);
        $this->assertTrue(Hash::check('segura123', $user->password));
        $this->assertNotSame('segura123', $user->password);
    }

    public function test_invalid_role_phone_and_weak_password_are_rejected_without_partial_user(): void
    {
        [$administrator] = $this->context();
        $unassignableRole = Rol::create(['nombre' => 'Auditor externo']);

        $this->actingAs($administrator)->post(route('usuarios.store'), [
            'name' => 'Usuario inválido',
            'email' => 'invalid@example.com',
            'rol_id' => $unassignableRole->id,
            'telefono' => 'teléfono secreto',
            'password' => 'corta',
            'password_confirmation' => 'diferente',
        ])->assertSessionHasErrors(['rol_id', 'telefono', 'password']);

        $this->assertDatabaseMissing('users', ['email' => 'invalid@example.com']);
    }

    public function test_normalized_email_is_unique_and_update_without_password_keeps_existing_hash(): void
    {
        [$administrator, $store] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        $seller = $this->user($store, $sellerRole, 'existing@example.com');
        $passwordHash = $seller->password;

        $this->actingAs($administrator)->post(route('usuarios.store'), [
            'name' => 'Correo repetido',
            'email' => '  EXISTING@EXAMPLE.COM ',
            'rol_id' => $sellerRole->id,
            'password' => 'segura123',
            'password_confirmation' => 'segura123',
        ])->assertSessionHasErrors('email');

        $this->post(route('usuarios.update', $seller), [
            'name' => '  Usuario    Actualizado ',
            'email' => ' EXISTING@EXAMPLE.COM ',
            'rol_id' => $sellerRole->id,
            'telefono' => '099 111 2233',
            'password' => null,
            'password_confirmation' => null,
        ])->assertRedirect(route('usuarios.index'))->assertSessionHasNoErrors();

        $seller->refresh();
        $this->assertSame('Usuario Actualizado', $seller->name);
        $this->assertSame('existing@example.com', $seller->email);
        $this->assertSame('0991112233', $seller->telefono);
        $this->assertSame($passwordHash, $seller->password);
        $this->assertSame(2, User::where('tienda_id', $store->id)->count());
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);

        return [$this->user($store, $role, $suffix.'@administrator.test'), $store];
    }

    private function user(Tienda $store, Rol $role, string $email): User
    {
        return User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => $role->nombre.' '.Str::random(5),
            'email' => $email,
            'password' => 'original123',
            'activo' => true,
            'es_super_admin' => false,
        ]);
    }
}
