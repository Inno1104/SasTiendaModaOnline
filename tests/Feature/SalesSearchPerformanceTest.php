<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesSearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_sales_report_is_paginated_and_has_a_bounded_query_budget(): void
    {
        [$user, $store] = $this->context();
        $this->insertSales($store->id, $user->id, 1200);
        $this->warmUser($user, $store);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('reportes.ventas', [
            'desde' => '2026-01-01',
            'hasta' => '2026-12-31',
        ]));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $response->assertSee('css/sales-report.css', false)
            ->assertSee('js/vendor/chart.umd.min.js', false)
            ->assertSee('js/sales-report.js', false)
            ->assertSee('salesDailyChart', false)
            ->assertSee('salesPaymentChart', false);
        $this->assertSame(1200, $response->viewData('ventas')->total());
        $this->assertCount(100, $response->viewData('ventas')->items());
        $this->assertSame(1200, $response->viewData('totalVentas'));
        $this->assertSame(12000.0, $response->viewData('totalGeneral'));
        $this->assertLessThanOrEqual(12, $queries->count(), 'El reporte de ventas excedió su presupuesto de consultas.');
    }

    public function test_report_rejects_invalid_or_excessive_date_ranges(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)->get(route('reportes.ventas', [
            'desde' => '2025-01-01',
            'hasta' => '2026-12-31',
        ]))->assertSessionHasErrors('hasta');

        $this->get(route('reportes.ventas', [
            'desde' => '2026-08-20',
            'hasta' => '2026-08-10',
        ]))->assertSessionHasErrors('hasta');
    }

    public function test_real_sales_range_query_uses_the_analytics_index(): void
    {
        [$user, $store] = $this->context();
        $this->insertSales($store->id, $user->id, 20);

        $plan = DB::select(
            'EXPLAIN QUERY PLAN SELECT id FROM ventas WHERE tienda_id = ? AND estado = ? AND fecha BETWEEN ? AND ? ORDER BY fecha DESC, id DESC LIMIT 100',
            [$store->id, 'completada', '2026-01-01', '2026-12-31']
        );
        $description = collect($plan)->pluck('detail')->implode(' ');

        $this->assertStringContainsString('ventas_analitica_estado_fecha_id', $description);
    }

    public function test_dashboard_query_budget_does_not_grow_with_sales_volume(): void
    {
        [$user, $store] = $this->context();
        $this->insertSales($store->id, $user->id, 1000);
        $this->warmUser($user, $store);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('dashboard', ['periodo' => '30_dias']));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertLessThanOrEqual(18, $queries->count(), 'El Dashboard excedió su presupuesto de consultas.');
    }

    private function context(): array
    {
        $suffix = uniqid();
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $store = Tienda::create([
            'nombre' => 'Tienda rendimiento',
            'slug' => 'tienda-rendimiento-'.$suffix,
            'estado' => 'activa',
        ]);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => $suffix.'@sales-performance.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function insertSales(int $storeId, int $userId, int $quantity): void
    {
        foreach (array_chunk(range(1, $quantity), 250) as $numbers) {
            DB::table('ventas')->insert(array_map(fn (int $number) => [
                'tienda_id' => $storeId,
                'numero_venta' => sprintf('PERF-%08d', $number),
                'user_id' => $userId,
                'fecha' => sprintf('2026-%02d-%02d', (($number - 1) % 12) + 1, (($number - 1) % 28) + 1),
                'tipo_comprobante' => 'ticket',
                'serie' => 'PERF',
                'correlativo' => sprintf('%08d', $number),
                'subtotal' => 10,
                'descuento' => 0,
                'igv' => 0,
                'total' => 10,
                'metodo_pago' => 'efectivo',
                'monto_pagado' => 10,
                'vuelto' => 0,
                'estado' => 'completada',
                'created_at' => now(),
                'updated_at' => now(),
            ], $numbers));
        }
    }

    private function warmUser(User $user, Tienda $store): void
    {
        $user->setRelation('tienda', $store);
        $user->load('rol');
    }
}
