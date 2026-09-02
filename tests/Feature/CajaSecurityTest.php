<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CajaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_register_routes_require_authentication(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        $register = $this->register($administrator);

        $this->get(route('caja.index'))->assertRedirect(route('login'));
        $this->post(route('caja.open'), ['monto_inicial' => 100])->assertRedirect(route('login'));
        $this->post(route('caja.close', $register))->assertRedirect(route('login'));
    }

    public function test_only_administrators_and_cashiers_can_open_a_register(): void
    {
        [$seller] = $this->userWithStore('Vendedor');

        $this->actingAs($seller)->post(route('caja.open'), ['monto_inicial' => 100])->assertForbidden();
        $this->assertDatabaseCount('caja', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_inactive_user_cannot_view_or_operate_registers(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        $administrator->update(['activo' => false]);

        $this->actingAs($administrator)->get(route('caja.index'))->assertForbidden();
        $this->post(route('caja.open'), ['monto_inicial' => 100])->assertRedirect(route('login'));
    }

    public function test_opening_validates_amount_and_does_not_create_partial_records(): void
    {
        [$administrator] = $this->userWithStore('Administrador');

        $this->actingAs($administrator)->post(route('caja.open'), ['monto_inicial' => -1])
            ->assertSessionHasErrors('monto_inicial');

        $this->assertDatabaseCount('caja', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_opening_is_audited_with_actor_store_and_amount(): void
    {
        [$cashier, $store] = $this->userWithStore('Cajero');

        $this->actingAs($cashier)->post(route('caja.open'), ['monto_inicial' => 125.50])
            ->assertSessionHas('success');

        $register = Caja::firstOrFail();
        $this->assertSame($cashier->id, $register->user_id);
        $this->assertDatabaseHas('auditorias', [
            'tienda_id' => $store->id,
            'user_id' => $cashier->id,
            'accion' => 'caja.abierta',
            'auditable_type' => Caja::class,
            'auditable_id' => $register->id,
        ]);
        $this->assertSame(125.5, (float) $register->auditorias()->firstOrFail()->metadata['monto_inicial']);
    }

    public function test_opening_stores_notes_and_displays_turn_responsible_and_duration(): void
    {
        [$cashier] = $this->userWithStore('Cajero');

        $this->actingAs($cashier)->post(route('caja.open'), [
            'monto_inicial' => 40,
            'observaciones' => 'Turno de mañana sin novedades',
        ])->assertSessionHas('success');

        $register = Caja::firstOrFail();
        $this->assertSame('Turno de mañana sin novedades', $register->observaciones);
        $this->get(route('caja.index'))
            ->assertOk()
            ->assertSee('Turno abierto')
            ->assertSee($cashier->name)
            ->assertSee('Duración')
            ->assertSee('Turno de mañana sin novedades');
    }

    public function test_opening_rejects_notes_longer_than_the_allowed_limit(): void
    {
        [$administrator] = $this->userWithStore('Administrador');

        $this->actingAs($administrator)->post(route('caja.open'), [
            'monto_inicial' => 10,
            'observaciones' => str_repeat('x', 501),
        ])->assertSessionHasErrors('observaciones');

        $this->assertDatabaseCount('caja', 0);
    }

    public function test_sale_requires_an_open_register_without_creating_partial_records(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $category = Categoria::create(['tienda_id' => $store->id, 'nombre' => 'Ropa', 'slug' => 'ropa-'.$store->id]);
        $product = Producto::create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'SKU-TURNO-'.$store->id,
            'nombre' => 'Producto de turno',
            'precio_venta' => 10,
            'activo' => true,
        ]);
        $variant = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => 5,
        ]);

        $this->actingAs($seller)->post(route('ventas.store'), [
            'tipo_comprobante' => 'ticket',
            'metodo_pago' => 'efectivo',
            'monto_pagado' => 20,
            'items' => [[
                'producto_id' => $product->id,
                'variante_id' => $variant->id,
                'cantidad' => 1,
            ]],
        ])->assertSessionHasErrors('caja');

        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('detalle_ventas', 0);
        $this->assertDatabaseCount('pagos_venta', 0);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_pos_warns_and_disables_confirmation_when_there_is_no_open_shift(): void
    {
        [$seller] = $this->userWithStore('Vendedor');

        $this->actingAs($seller)->get(route('ventas.create'))
            ->assertOk()
            ->assertSee('No hay un turno de caja abierto.')
            ->assertSee('Ir a Caja')
            ->assertSee('id="reviewSale" class="checkout-button" disabled', false)
            ->assertSee('Abre la caja para vender');
    }

    public function test_store_cannot_close_another_stores_register(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        [$foreignAdministrator] = $this->userWithStore('Administrador');
        $foreignRegister = $this->register($foreignAdministrator);

        $this->actingAs($administrator)->post(route('caja.close', $foreignRegister->id))->assertNotFound();

        $this->assertSame('abierta', $foreignRegister->fresh()->estado);
    }

    public function test_cashier_cannot_close_a_register_opened_by_another_user(): void
    {
        [$cashier, $store] = $this->userWithStore('Cajero');
        $otherCashier = $this->user($store, 'Cajero');
        $register = $this->register($otherCashier);

        $this->actingAs($cashier)->post(route('caja.close', $register))->assertForbidden();

        $this->assertSame('abierta', $register->fresh()->estado);
    }

    public function test_administrator_can_close_another_users_register_and_action_is_audited(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $cashier = $this->user($store, 'Cajero');
        $register = $this->register($cashier, 80);

        $this->actingAs($administrator)->post(route('caja.close', $register), ['efectivo_contado' => 80])->assertSessionHas('success');

        $register->refresh();
        $this->assertSame('cerrada', $register->estado);
        $this->assertSame($administrator->id, $register->cerrada_por);
        $this->assertEquals(80, $register->monto_final);
        $this->assertDatabaseHas('auditorias', [
            'tienda_id' => $store->id,
            'user_id' => $administrator->id,
            'accion' => 'caja.cerrada',
            'auditable_type' => Caja::class,
            'auditable_id' => $register->id,
        ]);
    }

    public function test_closed_register_cannot_be_closed_twice(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        $register = $this->register($administrator);

        $this->actingAs($administrator)->post(route('caja.close', $register), ['efectivo_contado' => 0])->assertSessionHas('success');
        $this->post(route('caja.close', $register), ['efectivo_contado' => 0])->assertSessionHasErrors('caja');

        $this->assertSame(1, $register->auditorias()->where('accion', 'caja.cerrada')->count());
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
            'email' => $suffix.'@caja.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function register(User $user, float $initialAmount = 0): Caja
    {
        return Caja::create([
            'tienda_id' => $user->tienda_id,
            'user_id' => $user->id,
            'fecha' => today(),
            'monto_inicial' => $initialAmount,
            'estado' => 'abierta',
            'apertura' => now(),
        ]);
    }
}
