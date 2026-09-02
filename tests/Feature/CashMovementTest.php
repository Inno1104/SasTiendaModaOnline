<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Auditoria;
use App\Models\MovimientoCaja;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_movement_route_requires_authentication(): void
    {
        [$cashier] = $this->userWithStore('Cajero');
        $register = $this->register($cashier);

        $this->post(route('caja.movimientos.store', $register), $this->payload())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_responsible_cashier_can_register_income_and_expense_with_audit(): void
    {
        [$cashier, $store] = $this->userWithStore('Cajero');
        $register = $this->register($cashier, 100);

        $this->actingAs($cashier)->post(route('caja.movimientos.store', $register), $this->payload([
            'tipo' => 'ingreso',
            'concepto' => 'Fondo adicional',
            'monto' => 25.50,
        ]))->assertSessionHas('success');
        $this->post(route('caja.movimientos.store', $register), $this->payload([
            'tipo' => 'egreso',
            'concepto' => 'Pago de transporte',
            'monto' => 7.25,
            'observaciones' => 'Entrega urgente',
        ]))->assertSessionHas('success');

        $register->refresh();
        $this->assertEquals(25.50, $register->total_ingresos);
        $this->assertEquals(7.25, $register->total_egresos);
        $this->assertDatabaseHas('movimientos_caja', [
            'tienda_id' => $store->id,
            'caja_id' => $register->id,
            'user_id' => $cashier->id,
            'tipo' => 'egreso',
            'concepto' => 'Pago de transporte',
            'monto' => 7.25,
            'observaciones' => 'Entrega urgente',
        ]);
        $this->assertSame(2, MovimientoCaja::count());
        $this->assertSame(2, Auditoria::query()
            ->where('tienda_id', $store->id)
            ->where('accion', 'caja.movimiento_creado')
            ->count());
    }

    public function test_movement_validation_rejects_invalid_type_amount_and_concept(): void
    {
        [$cashier] = $this->userWithStore('Cajero');
        $register = $this->register($cashier);

        $this->actingAs($cashier)->post(route('caja.movimientos.store', $register), [
            'tipo' => 'retiro_desconocido',
            'concepto' => 'x',
            'monto' => 0,
        ])->assertSessionHasErrors(['tipo', 'concepto', 'monto']);

        $this->assertDatabaseCount('movimientos_caja', 0);
        $this->assertEquals(0, $register->fresh()->total_ingresos);
        $this->assertEquals(0, $register->fresh()->total_egresos);
    }

    public function test_unrelated_cashier_cannot_register_movement(): void
    {
        [$cashier, $store] = $this->userWithStore('Cajero');
        $otherCashier = $this->user($store, 'Cajero');
        $register = $this->register($cashier);

        $this->actingAs($otherCashier)->post(route('caja.movimientos.store', $register), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_administrator_can_register_movement_in_cashiers_open_shift(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $cashier = $this->user($store, 'Cajero');
        $register = $this->register($cashier);

        $this->actingAs($administrator)->post(route('caja.movimientos.store', $register), $this->payload())
            ->assertSessionHas('success');

        $this->assertDatabaseHas('movimientos_caja', ['caja_id' => $register->id, 'user_id' => $administrator->id]);
    }

    public function test_cannot_register_movements_in_closed_or_foreign_register(): void
    {
        [$cashier] = $this->userWithStore('Cajero');
        $closed = $this->register($cashier);
        $closed->update(['estado' => 'cerrada', 'cierre' => now()]);

        $this->actingAs($cashier)->post(route('caja.movimientos.store', $closed), $this->payload())
            ->assertSessionHasErrors('caja');

        [$foreignCashier] = $this->userWithStore('Cajero');
        $foreignRegister = $this->register($foreignCashier);
        $this->actingAs($cashier)->post(route('caja.movimientos.store', $foreignRegister->id), $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_close_recalculates_movement_totals_and_expected_cash_from_source_records(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        $register = $this->register($administrator, 100);

        $this->actingAs($administrator)->post(route('caja.movimientos.store', $register), $this->payload([
            'tipo' => 'ingreso', 'concepto' => 'Fondo adicional', 'monto' => 30,
        ]));
        $this->post(route('caja.movimientos.store', $register), $this->payload([
            'tipo' => 'egreso', 'concepto' => 'Compra de materiales', 'monto' => 12,
        ]));
        $register->update(['total_ingresos' => 999, 'total_egresos' => 999]);

        $this->post(route('caja.close', $register), ['efectivo_contado' => 118])->assertSessionHas('success');

        $register->refresh();
        $this->assertEquals(30, $register->total_ingresos);
        $this->assertEquals(12, $register->total_egresos);
        $this->assertEquals(118, $register->monto_final);
        $this->assertSame('cerrada', $register->estado);
    }

    public function test_open_shift_page_displays_movement_form_and_records(): void
    {
        [$cashier] = $this->userWithStore('Cajero');
        $register = $this->register($cashier);
        MovimientoCaja::create([
            'tienda_id' => $cashier->tienda_id,
            'caja_id' => $register->id,
            'user_id' => $cashier->id,
            'tipo' => 'egreso',
            'concepto' => 'Compra de suministros',
            'monto' => 9.50,
        ]);

        $this->actingAs($cashier)->get(route('caja.index'))
            ->assertOk()
            ->assertSee('css/caja.css')
            ->assertSee('Registrar movimiento')
            ->assertSee('Movimientos del turno')
            ->assertSee('Compra de suministros')
            ->assertSee('$ 9.50');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tipo' => 'ingreso',
            'concepto' => 'Ingreso extraordinario',
            'monto' => 10,
        ], $overrides);
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
            'email' => $suffix.'@movimientos.test',
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
