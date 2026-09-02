<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Plan;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsuarioPlanConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_limited_plan_rejects_creation_without_partial_user_or_audit(): void
    {
        [$administrator, $store] = $this->context(1);
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);

        $this->actingAs($administrator)->post(route('usuarios.store'), $this->payload($sellerRole, 'blocked@example.com'))
            ->assertSessionHas('error');

        $this->assertSame(1, User::query()->where('tienda_id', $store->id)->count());
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_available_slot_is_created_and_audited_in_the_same_transaction(): void
    {
        [$administrator, $store] = $this->context(2);
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);

        $this->actingAs($administrator)->post(route('usuarios.store'), $this->payload($sellerRole, 'allowed@example.com'))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success');

        $created = User::query()->where('email', 'allowed@example.com')->sole();
        $this->assertSame($store->id, $created->tienda_id);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'usuario.creado',
            'auditable_id' => $created->id,
            'user_id' => $administrator->id,
        ]);
    }

    public function test_unlimited_plan_and_store_isolation_are_respected(): void
    {
        [$administrator, $store] = $this->context(-1);
        [, $otherStore] = $this->context(1);
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);

        foreach (range(1, 3) as $position) {
            $this->actingAs($administrator)
                ->post(route('usuarios.store'), $this->payload($sellerRole, "unlimited{$position}@example.com"))
                ->assertSessionHas('success');
        }

        $this->assertSame(4, User::query()->where('tienda_id', $store->id)->count());
        $this->assertSame(1, User::query()->where('tienda_id', $otherStore->id)->count());
        $this->assertSame(3, Auditoria::query()->where('tienda_id', $store->id)->count());
    }

    /** @return array{User, Tienda} */
    private function context(int $limit): array
    {
        $suffix = Str::lower(Str::random(8));
        $plan = Plan::create([
            'nombre' => 'Plan '.$suffix,
            'slug' => 'plan-'.$suffix,
            'precio' => 10,
            'max_productos' => -1,
            'max_usuarios' => $limit,
            'max_ventas_mes' => -1,
            'activo' => true,
        ]);
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'plan_id' => $plan->id,
            'estado' => 'activa',
        ]);
        $administrator = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@plan-admin.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$administrator, $store];
    }

    /** @return array<string, mixed> */
    private function payload(Rol $role, string $email): array
    {
        return [
            'name' => 'Usuario de plan',
            'email' => $email,
            'rol_id' => $role->id,
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ];
    }
}
