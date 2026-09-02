<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\CashRegisterSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashRegisterPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_summary_uses_a_constant_three_query_budget(): void
    {
        [$user, $store] = $this->context();
        $register = $this->register($user);
        $saleRows = [];
        foreach (range(1, 40) as $number) {
            $saleRows[] = $this->saleRow($store->id, $user->id, $register->id, $number);
        }
        DB::table('ventas')->insert($saleRows);
        $sales = DB::table('ventas')->where('caja_id', $register->id)->pluck('id');
        DB::table('pagos_venta')->insert($sales->map(fn ($saleId) => [
            'tienda_id' => $store->id,
            'venta_id' => $saleId,
            'metodo' => 'efectivo',
            'monto' => 10,
            'referencia' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
        DB::table('movimientos_caja')->insert([
            $this->movementRow($store->id, $user->id, $register->id, 'ingreso', 20),
            $this->movementRow($store->id, $user->id, $register->id, 'egreso', 5),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $summary = app(CashRegisterSummary::class)->calculate($register);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(3, $queries);
        $this->assertSame(40, $summary['ventas_cantidad']);
        $this->assertSame(400.0, $summary['total_ventas']);
        $this->assertSame(515.0, $summary['efectivo_esperado']);
    }

    public function test_history_query_count_stays_bounded_with_many_registers(): void
    {
        [$user, $store] = $this->context();
        DB::table('caja')->insert(collect(range(1, 80))->map(fn ($number) => $this->registerRow($store->id, $user->id, $number))->all());
        $this->warmUserRelations($user, $store);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('caja.index', ['estado' => 'cerrada']));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertSame(80, $response->viewData('historial')->total());
        $this->assertCount(15, $response->viewData('historial')->items());
        $this->assertLessThanOrEqual(12, $queries->count(), 'El historial de caja presenta un posible problema N+1.');
    }

    public function test_detail_query_count_stays_bounded_with_many_sales_and_movements(): void
    {
        [$user, $store] = $this->context();
        $register = $this->register($user);
        DB::table('ventas')->insert(collect(range(1, 45))->map(
            fn ($number) => $this->saleRow($store->id, $user->id, $register->id, $number)
        )->all());
        $sales = DB::table('ventas')->where('caja_id', $register->id)->pluck('id');
        DB::table('pagos_venta')->insert($sales->map(fn ($saleId) => [
            'tienda_id' => $store->id, 'venta_id' => $saleId, 'metodo' => 'tarjeta', 'monto' => 10,
            'referencia' => null, 'created_at' => now(), 'updated_at' => now(),
        ])->all());
        DB::table('movimientos_caja')->insert(collect(range(1, 50))->map(
            fn ($number) => $this->movementRow($store->id, $user->id, $register->id, $number % 2 ? 'ingreso' : 'egreso', 1)
        )->all());
        $this->warmUserRelations($user, $store);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('caja.show', $register));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertSame(45, $response->viewData('ventas')->total());
        $this->assertSame(50, $response->viewData('movimientos')->total());
        $this->assertLessThanOrEqual(18, $queries->count(), 'El detalle de caja presenta un posible problema N+1.');
    }

    private function context(): array
    {
        $suffix = uniqid();
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@caja-performance.test',
            'password' => 'password',
            'activo' => true,
        ]);
        $this->actingAs($user);

        return [$user, $store];
    }

    private function register(User $user): Caja
    {
        return Caja::create([
            'tienda_id' => $user->tienda_id,
            'user_id' => $user->id,
            'fecha' => today(),
            'monto_inicial' => 100,
            'estado' => 'abierta',
            'apertura' => now(),
        ]);
    }

    private function registerRow(int $storeId, int $userId, int $number): array
    {
        return [
            'tienda_id' => $storeId, 'user_id' => $userId, 'cerrada_por' => $userId,
            'fecha' => sprintf('2026-07-%02d', (($number - 1) % 28) + 1), 'monto_inicial' => 100,
            'monto_final' => 100, 'efectivo_esperado' => 100, 'efectivo_contado' => 100,
            'diferencia' => 0, 'estado_conciliacion' => 'exacto', 'total_ventas' => 0,
            'total_ingresos' => 0, 'total_egresos' => 0, 'estado' => 'cerrada',
            'apertura' => now()->subHours(8), 'cierre' => now(), 'observaciones' => null,
            'observaciones_cierre' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function saleRow(int $storeId, int $userId, int $registerId, int $number): array
    {
        return [
            'tienda_id' => $storeId, 'numero_venta' => sprintf('T001-%08d', $number),
            'cliente_id' => null, 'user_id' => $userId, 'caja_id' => $registerId, 'fecha' => today(),
            'tipo_comprobante' => 'ticket', 'serie' => 'T001', 'correlativo' => sprintf('%08d', $number),
            'subtotal' => 10, 'descuento' => 0, 'igv' => 0, 'total' => 10,
            'metodo_pago' => 'efectivo', 'monto_pagado' => 10, 'vuelto' => 0, 'estado' => 'completada',
            'anulada_por' => null, 'anulada_at' => null, 'motivo_anulacion' => null,
            'observaciones' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function movementRow(int $storeId, int $userId, int $registerId, string $type, float $amount): array
    {
        return [
            'tienda_id' => $storeId, 'caja_id' => $registerId, 'user_id' => $userId,
            'tipo' => $type, 'concepto' => 'Movimiento de rendimiento', 'monto' => $amount,
            'observaciones' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function warmUserRelations(User $user, Tienda $store): void
    {
        $user->setRelation('tienda', $store);
        $user->load('rol');
    }
}
