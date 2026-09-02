<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\PagoVenta;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_requires_physically_counted_cash(): void
    {
        [$administrator] = $this->userWithStore();
        $register = $this->register($administrator, 100);

        $this->actingAs($administrator)->post(route('caja.close', $register), [])
            ->assertSessionHasErrors('efectivo_contado');

        $this->assertSame('abierta', $register->fresh()->estado);
        $this->assertNull($register->fresh()->cerrada_por);
    }

    public function test_exact_closing_persists_expected_counted_and_zero_difference(): void
    {
        [$administrator] = $this->userWithStore();
        $register = $this->register($administrator, 100);
        $this->saleWithPayments($administrator, $register, 90, 10, ['efectivo' => 60, 'tarjeta' => 40]);
        $this->movement($administrator, $register, 'ingreso', 20);
        $this->movement($administrator, $register, 'egreso', 5);

        // 100 inicial + 60 efectivo + 20 ingreso - 10 vuelto - 5 egreso = 165.
        $this->actingAs($administrator)->post(route('caja.close', $register), [
            'efectivo_contado' => 165,
        ])->assertSessionHas('success');

        $register->refresh();
        $this->assertEquals(165, $register->efectivo_esperado);
        $this->assertEquals(165, $register->efectivo_contado);
        $this->assertEquals(0, $register->diferencia);
        $this->assertEquals(165, $register->monto_final);
        $this->assertSame('exacto', $register->estado_conciliacion);
        $this->assertNull($register->observaciones_cierre);
    }

    public function test_surplus_requires_justification_and_failed_attempt_keeps_register_open(): void
    {
        [$administrator] = $this->userWithStore();
        $register = $this->register($administrator, 100);

        $this->actingAs($administrator)->post(route('caja.close', $register), [
            'efectivo_contado' => 110,
        ])->assertSessionHasErrors('observaciones_cierre');

        $register->refresh();
        $this->assertSame('abierta', $register->estado);
        $this->assertNull($register->efectivo_contado);
        $this->assertDatabaseMissing('auditorias', ['accion' => 'caja.cerrada', 'auditable_id' => $register->id]);
    }

    public function test_surplus_is_classified_and_audited_with_justification(): void
    {
        [$administrator] = $this->userWithStore();
        $register = $this->register($administrator, 100);

        $this->actingAs($administrator)->post(route('caja.close', $register), [
            'efectivo_contado' => 110,
            'observaciones_cierre' => 'Dinero adicional encontrado durante el conteo',
        ])->assertSessionHas('success');

        $register->refresh();
        $this->assertEquals(10, $register->diferencia);
        $this->assertSame('sobrante', $register->estado_conciliacion);
        $this->assertSame('Dinero adicional encontrado durante el conteo', $register->observaciones_cierre);
        $audit = Auditoria::where('accion', 'caja.cerrada')->where('auditable_id', $register->id)->firstOrFail();
        $this->assertSame('sobrante', $audit->metadata['estado_conciliacion']);
        $this->assertSame(10.0, (float) $audit->metadata['diferencia']);
    }

    public function test_shortage_is_classified_with_negative_difference(): void
    {
        [$administrator] = $this->userWithStore();
        $register = $this->register($administrator, 100);

        $this->actingAs($administrator)->post(route('caja.close', $register), [
            'efectivo_contado' => 92.50,
            'observaciones_cierre' => 'Faltante detectado al finalizar el turno',
        ])->assertSessionHas('success');

        $register->refresh();
        $this->assertEquals(-7.50, $register->diferencia);
        $this->assertSame('faltante', $register->estado_conciliacion);
        $this->assertEquals(92.50, $register->monto_final);
    }

    public function test_open_register_displays_real_payment_breakdown_and_inline_close_dialog(): void
    {
        [$administrator] = $this->userWithStore();
        $register = $this->register($administrator, 50);
        $this->saleWithPayments($administrator, $register, 80, 5, [
            'efectivo' => 35,
            'tarjeta' => 20,
            'transferencia' => 30,
        ]);

        $this->actingAs($administrator)->get(route('caja.index'))
            ->assertOk()
            ->assertSee('Efectivo esperado')
            ->assertSee('Efectivo:')
            ->assertSee('$ 35.00')
            ->assertSee('Tarjeta:')
            ->assertSee('$ 20.00')
            ->assertSee('Transferencia:')
            ->assertSee('$ 30.00')
            ->assertSee('id="cashCloseDialog"', false)
            ->assertSee('Conciliar y cerrar turno')
            ->assertSee('js/caja.js');
    }

    public function test_cancelled_sales_are_excluded_from_reconciliation(): void
    {
        [$administrator] = $this->userWithStore();
        $register = $this->register($administrator, 25);
        $sale = $this->saleWithPayments($administrator, $register, 100, 0, ['efectivo' => 100]);
        $sale->update(['estado' => 'anulada']);

        $this->actingAs($administrator)->post(route('caja.close', $register), [
            'efectivo_contado' => 25,
        ])->assertSessionHas('success');

        $register->refresh();
        $this->assertEquals(25, $register->efectivo_esperado);
        $this->assertEquals(0, $register->total_ventas);
    }

    private function userWithStore(): array
    {
        $suffix = uniqid();
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@conciliacion.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function register(User $user, float $initial): Caja
    {
        return Caja::create([
            'tienda_id' => $user->tienda_id,
            'user_id' => $user->id,
            'fecha' => today(),
            'monto_inicial' => $initial,
            'estado' => 'abierta',
            'apertura' => now(),
        ]);
    }

    private function saleWithPayments(User $user, Caja $register, float $total, float $change, array $payments): Venta
    {
        $sale = Venta::create([
            'tienda_id' => $user->tienda_id,
            'numero_venta' => 'T001-'.str_pad((string) (Venta::count() + 1), 8, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'caja_id' => $register->id,
            'fecha' => today(),
            'tipo_comprobante' => 'ticket',
            'serie' => 'T001',
            'correlativo' => str_pad((string) (Venta::count() + 1), 8, '0', STR_PAD_LEFT),
            'subtotal' => $total,
            'igv' => 0,
            'total' => $total,
            'metodo_pago' => array_key_first($payments),
            'monto_pagado' => array_sum($payments),
            'vuelto' => $change,
            'estado' => 'completada',
        ]);

        foreach ($payments as $method => $amount) {
            PagoVenta::create(['tienda_id' => $user->tienda_id, 'venta_id' => $sale->id, 'metodo' => $method, 'monto' => $amount]);
        }

        return $sale;
    }

    private function movement(User $user, Caja $register, string $type, float $amount): MovimientoCaja
    {
        return MovimientoCaja::create([
            'tienda_id' => $user->tienda_id,
            'caja_id' => $register->id,
            'user_id' => $user->id,
            'tipo' => $type,
            'concepto' => 'Movimiento de conciliación',
            'monto' => $amount,
        ]);
    }
}
