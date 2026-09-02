<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreSecurityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_store_and_settings(): void
    {
        $this->get(route('tienda.edit'))->assertRedirect(route('login'));
        $this->post(route('tienda.update'))->assertRedirect(route('login'));
        $this->get(route('configuracion.index'))->assertRedirect(route('login'));
        $this->post(route('configuracion.update'))->assertRedirect(route('login'));
    }

    public function test_active_store_members_can_view_but_only_administrator_can_modify(): void
    {
        [$administrator, $store] = $this->context();
        $seller = $this->user($store, 'Vendedor');
        $warehouse = $this->user($store, 'Almacén');

        foreach ([$seller, $warehouse] as $restrictedUser) {
            $this->actingAs($restrictedUser)->get(route('tienda.edit'))->assertOk();
            $this->get(route('configuracion.index'))->assertOk();
            $this->post(route('tienda.update'), $this->identityPayload('Manipulada'))->assertForbidden();
            $this->post(route('configuracion.update'), $this->settingsPayload('EUR'))->assertForbidden();
        }

        $this->assertSame($administrator->tienda_id, $store->id);
        $this->assertNotSame('Manipulada', $store->fresh()->nombre);
        $this->assertSame('USD', $store->fresh()->moneda);
    }

    public function test_administrator_updates_only_whitelisted_identity_fields(): void
    {
        [$administrator, $store, $plan] = $this->context();
        $otherPlan = $this->plan('otro-plan');
        $originalExpiration = $store->fecha_vencimiento;

        $this->actingAs($administrator)->post(route('tienda.update'), $this->identityPayload('Tienda actualizada') + [
            'tienda_id' => 999999,
            'plan_id' => $otherPlan->id,
            'estado' => 'suspendida',
            'fecha_vencimiento' => today()->subYear()->toDateString(),
            'max_usuarios' => 999999,
            'max_productos' => 999999,
        ])->assertSessionHas('success');

        $store->refresh();
        $this->assertSame('Tienda actualizada', $store->nombre);
        $this->assertSame($plan->id, $store->plan_id);
        $this->assertSame('activa', $store->estado);
        $this->assertTrue($store->fecha_vencimiento->equalTo($originalExpiration));
        $this->assertNotSame(999999, $plan->fresh()->max_usuarios);
        $this->assertNotSame(999999, $plan->fresh()->max_productos);
    }

    public function test_administrator_updates_only_whitelisted_settings_fields(): void
    {
        [$administrator, $store, $plan] = $this->context();
        $otherPlan = $this->plan('settings-plan');

        $this->actingAs($administrator)->post(route('configuracion.update'), $this->settingsPayload('EUR') + [
            'plan_id' => $otherPlan->id,
            'estado' => 'suspendida',
            'fecha_vencimiento' => today()->subDay()->toDateString(),
            'nombre' => 'Nombre inyectado',
        ])->assertSessionHas('success');

        $store->refresh();
        $this->assertSame('EUR', $store->moneda);
        $this->assertSame('€', $store->simbolo_moneda);
        $this->assertSame($plan->id, $store->plan_id);
        $this->assertSame('activa', $store->estado);
        $this->assertNotSame('Nombre inyectado', $store->nombre);
    }

    public function test_each_administrator_can_modify_only_their_authenticated_store(): void
    {
        [$administrator, $store] = $this->context();
        [, $foreignStore] = $this->context();
        $foreignName = $foreignStore->nombre;

        $this->actingAs($administrator)->post(route('tienda.update'), $this->identityPayload('Tienda propia'))
            ->assertSessionHas('success');

        $this->assertSame('Tienda propia', $store->fresh()->nombre);
        $this->assertSame($foreignName, $foreignStore->fresh()->nombre);
    }

    public function test_inactive_user_is_logged_out_and_superadministrator_is_redirected_to_its_panel(): void
    {
        [, $store] = $this->context();
        $inactiveAdministrator = $this->user($store, Rol::ADMINISTRATOR, false);

        $this->actingAs($inactiveAdministrator->fresh())->get(route('tienda.edit'))->assertForbidden();
        $this->assertGuest();

        $superAdministrator = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => null,
            'name' => 'Super administrador',
            'email' => Str::lower(Str::random(8)).'@super-store.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => true,
        ]);

        $this->actingAs($superAdministrator)->get(route('tienda.edit'))->assertRedirect(route('admin.index'));
    }

    /** @return array{User, Tienda, Plan} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $plan = $this->plan('plan-'.$suffix);
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'plan_id' => $plan->id,
            'estado' => 'activa',
            'fecha_vencimiento' => today()->addYear(),
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
        ]);

        return [$this->user($store, Rol::ADMINISTRATOR), $store, $plan];
    }

    private function user(Tienda $store, string $role, bool $active = true): User
    {
        $suffix = Str::lower(Str::random(8));

        return User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => $role])->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@store-security.test',
            'password' => 'password1',
            'activo' => $active,
            'es_super_admin' => false,
        ]);
    }

    private function plan(string $slug): Plan
    {
        return Plan::create([
            'nombre' => 'Plan '.$slug,
            'slug' => $slug,
            'precio' => 10,
            'max_productos' => 100,
            'max_usuarios' => 10,
            'max_ventas_mes' => 1000,
            'activo' => true,
        ]);
    }

    /** @return array<string, string|null> */
    private function identityPayload(string $name): array
    {
        return [
            'nombre' => $name,
            'ruc' => '1791234567001',
            'email' => 'contacto@example.test',
            'telefono' => '+593991234567',
            'direccion' => 'Av. Principal 123',
        ];
    }

    /** @return array<string, mixed> */
    private function settingsPayload(string $currency): array
    {
        return [
            'igv' => '15.00',
            'precios_incluyen_igv' => true,
            'moneda' => $currency,
            'serie_boleta' => 'B002',
            'serie_factura' => 'F002',
            'serie_ticket' => 'T002',
            'configuracion_version' => 0,
            'motivo_cambio' => 'Actualización fiscal autorizada',
            'confirmar_cambio' => true,
        ];
    }
}
