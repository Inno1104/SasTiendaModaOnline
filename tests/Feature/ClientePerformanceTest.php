<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_segmented_list_query_count_stays_bounded_with_many_customers_and_sales(): void
    {
        [$user, $store] = $this->context();
        $customers = $this->insertCustomers($store, 80);
        $this->insertSales($store, $user, $customers, 2);
        $this->warmUserRelations($user, $store);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('clientes.index', [
            'estado' => 'activo', 'ciudad' => 'Quito', 'actividad' => 'con_compras', 'orden' => 'gasto_desc',
        ]));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertSame(80, $response->viewData('clientes')->total());
        $this->assertCount(15, $response->viewData('clientes')->items());
        $this->assertLessThanOrEqual(10, $queries->count(), 'El listado de clientes presenta un posible problema N+1.');
    }

    public function test_customer_detail_query_count_stays_bounded_with_many_sales(): void
    {
        [$user, $store] = $this->context();
        $customerIds = $this->insertCustomers($store, 1);
        $this->insertSales($store, $user, $customerIds, 75);
        $customer = Cliente::findOrFail($customerIds[0]);
        $this->warmUserRelations($user, $store);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('clientes.show', $customer));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertSame(75, $response->viewData('ventas')->total());
        $this->assertCount(10, $response->viewData('ventas')->items());
        $this->assertLessThanOrEqual(10, $queries->count(), 'El detalle del cliente presenta un posible problema N+1.');
    }

    public function test_filtered_csv_export_streams_large_results_with_bounded_queries(): void
    {
        [$user, $store] = $this->context();
        $customerIds = $this->insertCustomers($store, 120);
        $this->insertSales($store, $user, $customerIds, 1);
        $this->warmUserRelations($user, $store);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('clientes.export', ['ciudad' => 'Quito']));
        $content = $response->streamedContent();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()->assertDownload();
        $this->assertSame(121, substr_count(trim($content), "\n") + 1);
        $this->assertLessThanOrEqual(6, $queries->count(), 'La exportación de clientes ejecuta consultas adicionales por registro.');
    }

    private function context(): array
    {
        $suffix = uniqid();
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $user = User::create([
            'rol_id' => $role->id, 'tienda_id' => $store->id, 'name' => 'Administrador',
            'email' => $suffix.'@customer-performance.test', 'password' => 'password', 'activo' => true,
        ]);
        $this->actingAs($user);

        return [$user, $store];
    }

    /** @return list<int> */
    private function insertCustomers(Tienda $store, int $amount): array
    {
        $now = now();
        DB::table('clientes')->insert(collect(range(1, $amount))->map(fn (int $number) => [
            'tienda_id' => $store->id,
            'codigo' => sprintf('CLI-PERF-%04d', $number),
            'nombre' => 'Cliente '.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'apellido' => 'Rendimiento',
            'dni' => 'PERF-DOC-'.$number,
            'email' => 'customer-'.$number.'-'.$store->id.'@performance.test',
            'telefono' => null,
            'direccion' => null,
            'ciudad' => 'Quito',
            'fecha_nacimiento' => null,
            'genero' => null,
            'puntos' => 0,
            'activo' => true,
            'estado_cambiado_por' => null,
            'estado_cambiado_at' => null,
            'motivo_estado' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        return DB::table('clientes')->where('tienda_id', $store->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @param list<int> $customerIds */
    private function insertSales(Tienda $store, User $user, array $customerIds, int $salesPerCustomer): void
    {
        $rows = [];
        $sequence = 1;
        foreach ($customerIds as $customerId) {
            foreach (range(1, $salesPerCustomer) as $position) {
                $rows[] = [
                    'tienda_id' => $store->id, 'numero_venta' => sprintf('PERF-%d-%06d', $store->id, $sequence++),
                    'cliente_id' => $customerId, 'user_id' => $user->id, 'caja_id' => null, 'fecha' => today(),
                    'tipo_comprobante' => 'ticket', 'serie' => null, 'correlativo' => null,
                    'subtotal' => 10, 'descuento' => 0, 'igv' => 0, 'total' => 10,
                    'metodo_pago' => 'efectivo', 'monto_pagado' => 10, 'vuelto' => 0, 'estado' => 'completada',
                    'anulada_por' => null, 'anulada_at' => null, 'motivo_anulacion' => null,
                    'observaciones' => null, 'created_at' => now()->subMinutes($position), 'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('ventas')->insert($chunk);
        }
    }

    private function warmUserRelations(User $user, Tienda $store): void
    {
        $user->setRelation('tienda', $store);
        $user->load('rol');
    }
}
