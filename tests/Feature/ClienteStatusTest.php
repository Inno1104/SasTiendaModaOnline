<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_requires_authentication_and_administrator_role(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $seller = $this->user($store, 'Vendedor');
        $customer = $this->customer($store);
        $payload = ['activo' => false, 'motivo' => 'Solicitud del cliente'];

        $this->patch(route('clientes.estado', $customer), $payload)->assertRedirect(route('login'));
        $this->actingAs($seller)->patch(route('clientes.estado', $customer), $payload)->assertForbidden();
        $this->assertTrue($customer->fresh()->activo);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_deactivation_requires_a_meaningful_reason_without_partial_changes(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $customer = $this->customer($store);

        $this->actingAs($administrator)->patch(route('clientes.estado', $customer), [
            'activo' => false,
            'motivo' => '  no ',
        ])->assertSessionHasErrors('motivo');

        $this->assertTrue($customer->fresh()->activo);
        $this->assertNull($customer->estado_cambiado_at);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_administrator_deactivates_customer_with_traceability_and_audit(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $customer = $this->customer($store);

        $this->actingAs($administrator)->patch(route('clientes.estado', $customer), [
            'activo' => false,
            'motivo' => '  Cuenta duplicada confirmada  ',
        ])->assertSessionHas('success');

        $customer->refresh();
        $this->assertFalse($customer->activo);
        $this->assertSame($administrator->id, $customer->estado_cambiado_por);
        $this->assertSame('Cuenta duplicada confirmada', $customer->motivo_estado);
        $this->assertNotNull($customer->estado_cambiado_at);

        $audit = Auditoria::where('accion', 'cliente.desactivado')->firstOrFail();
        $this->assertSame($store->id, $audit->tienda_id);
        $this->assertSame($administrator->id, $audit->user_id);
        $this->assertSame($customer->id, $audit->auditable_id);
        $this->assertSame('Cuenta duplicada confirmada', $audit->metadata['motivo']);
        $this->assertTrue($audit->metadata['estado_anterior']);
        $this->assertFalse($audit->metadata['estado_nuevo']);
    }

    public function test_administrator_can_reactivate_an_inactive_customer(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $customer = $this->customer($store, false);

        $this->actingAs($administrator)->patch(route('clientes.estado', $customer), [
            'activo' => true,
            'motivo' => 'Identidad del cliente verificada',
        ])->assertSessionHas('success');

        $this->assertTrue($customer->fresh()->activo);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'cliente.reactivado',
            'user_id' => $administrator->id,
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_repeating_the_same_state_is_rejected_and_not_audited(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $customer = $this->customer($store);

        $this->actingAs($administrator)->patch(route('clientes.estado', $customer), [
            'activo' => true,
            'motivo' => 'Intento de repetir el estado',
        ])->assertSessionHasErrors('activo');

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_customer_from_another_store_cannot_be_changed(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        [, $foreignStore] = $this->userWithStore('Administrador');
        $foreignCustomer = $this->customer($foreignStore);

        $this->actingAs($administrator)->patch(route('clientes.estado', $foreignCustomer), [
            'activo' => false,
            'motivo' => 'Intento desde otra tienda',
        ])->assertNotFound();

        $this->assertTrue($foreignCustomer->fresh()->activo);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_deactivation_preserves_customer_and_associated_sales(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $customer = $this->customer($store);
        $sale = Venta::create([
            'tienda_id' => $store->id,
            'numero_venta' => 'V-STATUS-001',
            'cliente_id' => $customer->id,
            'user_id' => $administrator->id,
            'fecha' => today(),
            'subtotal' => 25,
            'total' => 25,
            'monto_pagado' => 25,
            'estado' => 'completada',
        ]);

        $this->actingAs($administrator)->patch(route('clientes.estado', $customer), [
            'activo' => false,
            'motivo' => 'Cliente solicitó desactivar su cuenta',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', ['id' => $customer->id, 'activo' => false]);
        $this->assertDatabaseHas('ventas', ['id' => $sale->id, 'cliente_id' => $customer->id]);
    }

    public function test_index_exposes_confirmed_status_controls_only_to_administrator(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $seller = $this->user($store, 'Vendedor');
        $customer = $this->customer($store);

        $this->actingAs($administrator)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('data-customer-status', false)
            ->assertSee(route('clientes.estado', $customer), false)
            ->assertSee('/js/clientes.js', false);

        $this->actingAs($seller)->get(route('clientes.index'))
            ->assertOk()
            ->assertDontSee('data-customer-status', false);
    }

    private function userWithStore(string $role): array
    {
        $suffix = uniqid();
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);

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
            'email' => $suffix.'@customer-status.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function customer(Tienda $store, bool $active = true): Cliente
    {
        $suffix = uniqid();

        return Cliente::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'codigo' => 'CLI-'.$suffix,
            'nombre' => 'Cliente '.$suffix,
            'apellido' => 'Estado',
            'email' => $suffix.'@status.test',
            'activo' => $active,
        ]);
    }
}
