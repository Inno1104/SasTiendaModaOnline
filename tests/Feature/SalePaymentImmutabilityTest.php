<?php

namespace Tests\Feature;

use App\Models\PagoVenta;
use App\Models\VentaReversion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SalePaymentImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_is_immutable_through_eloquent(): void
    {
        $payment = $this->payment();

        try {
            $payment->update(['monto' => 99]);
            $this->fail('El pago pudo ser actualizado.');
        } catch (LogicException) {
            $this->assertSame('10.00', $payment->fresh()->monto);
        }

        $this->expectException(LogicException::class);
        $payment->delete();
    }

    public function test_database_rejects_payment_update_and_delete(): void
    {
        $payment = $this->payment();

        try {
            \DB::table('pagos_venta')->where('id', $payment->id)->update(['monto' => 99]);
            $this->fail('La base permitió actualizar el pago.');
        } catch (QueryException) {
            $this->assertDatabaseHas('pagos_venta', ['id' => $payment->id, 'monto' => 10]);
        }

        $this->expectException(QueryException::class);
        \DB::table('pagos_venta')->where('id', $payment->id)->delete();
    }

    public function test_financial_reversal_is_immutable_in_application_and_database(): void
    {
        $payment = $this->payment();
        $reversal = VentaReversion::create([
            'tienda_id' => 1,
            'venta_id' => 1,
            'pago_venta_id' => $payment->id,
            'user_id' => 1,
            'tipo' => VentaReversion::TIPO_ANULACION,
            'metodo' => 'efectivo',
            'monto' => 10,
            'motivo' => 'Anulación de prueba',
        ]);

        try {
            $reversal->update(['monto' => 1]);
            $this->fail('La reversión pudo modificarse mediante Eloquent.');
        } catch (LogicException) {
            $this->assertSame('10.00', $reversal->fresh()->monto);
        }

        try {
            \DB::table('venta_reversiones')->where('id', $reversal->id)->delete();
            $this->fail('La reversión pudo eliminarse directamente en la base.');
        } catch (QueryException) {
            $this->assertDatabaseHas('venta_reversiones', ['id' => $reversal->id, 'monto' => 10]);
        }
    }

    private function payment(): PagoVenta
    {
        \DB::table('tiendas')->insert([
            'id' => 1, 'nombre' => 'Tienda', 'slug' => 'tienda', 'estado' => 'activa',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('roles')->insert(['id' => 1, 'nombre' => 'Administrador', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('users')->insert([
            'id' => 1, 'rol_id' => 1, 'tienda_id' => 1, 'name' => 'Admin', 'email' => 'admin@payment.test',
            'password' => bcrypt('password'), 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('ventas')->insert([
            'id' => 1, 'tienda_id' => 1, 'numero_venta' => 'T001-00000001', 'user_id' => 1,
            'fecha' => today(), 'tipo_comprobante' => 'ticket', 'serie' => 'T001', 'correlativo' => '00000001',
            'subtotal' => 10, 'descuento' => 0, 'igv' => 0, 'total' => 10, 'metodo_pago' => 'efectivo',
            'monto_pagado' => 10, 'vuelto' => 0, 'estado' => 'completada', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return PagoVenta::create(['tienda_id' => 1, 'venta_id' => 1, 'metodo' => 'efectivo', 'monto' => 10]);
    }
}
