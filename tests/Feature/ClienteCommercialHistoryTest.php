<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use App\Services\ClienteCommercialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteCommercialHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_uses_only_completed_sales(): void
    {
        [$user, $store] = $this->context();
        $customer = $this->customer($store);
        $this->sale($store, $user, $customer, 'COMPLETED-1', 40, 'completada', now()->subDays(3));
        $lastCompleted = $this->sale($store, $user, $customer, 'COMPLETED-2', 60, 'completada', now()->subDay());
        $this->sale($store, $user, $customer, 'CANCELLED', 900, 'anulada', now());
        $this->sale($store, $user, $customer, 'PENDING', 500, 'pendiente', now());

        $summary = app(ClienteCommercialSummary::class)->calculate($customer);

        $this->assertSame(2, $summary['compras']);
        $this->assertSame(100.0, $summary['total_gastado']);
        $this->assertSame(50.0, $summary['ticket_promedio']);
        $this->assertSame($lastCompleted->created_at->format('Y-m-d H:i:s'), $summary['ultima_compra']->format('Y-m-d H:i:s'));
    }

    public function test_customer_without_completed_sales_has_zero_metrics_and_no_last_purchase(): void
    {
        [$user, $store] = $this->context();
        $customer = $this->customer($store);
        $this->sale($store, $user, $customer, 'CANCELLED', 80, 'anulada');

        $summary = app(ClienteCommercialSummary::class)->calculate($customer);

        $this->assertSame(0, $summary['compras']);
        $this->assertSame(0.0, $summary['total_gastado']);
        $this->assertSame(0.0, $summary['ticket_promedio']);
        $this->assertNull($summary['ultima_compra']);
    }

    public function test_detail_displays_real_commercial_metrics(): void
    {
        [$user, $store] = $this->context();
        $customer = $this->customer($store);
        $this->sale($store, $user, $customer, 'SALE-1', 25, 'completada');
        $this->sale($store, $user, $customer, 'SALE-2', 75, 'completada');

        $this->actingAs($user)->get(route('clientes.show', $customer))
            ->assertOk()
            ->assertSee('Compras completadas')
            ->assertSee('Total gastado')
            ->assertSee('$ 100.00')
            ->assertSee('Ticket promedio')
            ->assertSee('$ 50.00')
            ->assertSee('Última compra');
    }

    public function test_purchase_history_is_ordered_and_paginated_ten_per_page(): void
    {
        [$user, $store] = $this->context();
        $customer = $this->customer($store);

        foreach (range(1, 12) as $number) {
            $this->sale($store, $user, $customer, 'SALE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), $number, 'completada');
        }

        $firstPage = $this->actingAs($user)->get(route('clientes.show', $customer));
        $firstPage->assertOk()->assertSee('SALE-12')->assertSee('SALE-03')->assertDontSee('SALE-02');

        $this->get(route('clientes.show', [$customer, 'page' => 2]))
            ->assertOk()
            ->assertSee('SALE-02')
            ->assertSee('SALE-01')
            ->assertDontSee('SALE-12');
    }

    public function test_summary_and_history_do_not_include_sales_from_other_customers_or_stores(): void
    {
        [$user, $store] = $this->context();
        [, $otherStore] = $this->context();
        $customer = $this->customer($store);
        $otherCustomer = $this->customer($store);
        $foreignCustomer = $this->customer($otherStore);
        $this->sale($store, $user, $customer, 'LOCAL', 30, 'completada');
        $this->sale($store, $user, $otherCustomer, 'OTHER-CUSTOMER', 300, 'completada');
        $this->sale($otherStore, $user, $foreignCustomer, 'FOREIGN', 900, 'completada');

        $response = $this->actingAs($user)->get(route('clientes.show', $customer));

        $response->assertOk()
            ->assertSee('$ 30.00')
            ->assertSee('LOCAL')
            ->assertDontSee('OTHER-CUSTOMER')
            ->assertDontSee('FOREIGN');
    }

    private function context(): array
    {
        $suffix = uniqid();
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@commercial-history.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function customer(Tienda $store): Cliente
    {
        $suffix = uniqid();

        return Cliente::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'codigo' => 'CLI-'.$suffix,
            'nombre' => 'Cliente',
            'apellido' => $suffix,
            'email' => $suffix.'@history.test',
            'activo' => true,
        ]);
    }

    private function sale(
        Tienda $store,
        User $seller,
        Cliente $customer,
        string $number,
        float $total,
        string $status,
        mixed $createdAt = null
    ): Venta {
        $sale = Venta::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'numero_venta' => $number.'-'.$store->id,
            'cliente_id' => $customer->id,
            'user_id' => $seller->id,
            'fecha' => today(),
            'subtotal' => $total,
            'total' => $total,
            'monto_pagado' => $total,
            'estado' => $status,
        ]);

        if ($createdAt !== null) {
            $sale->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        return $sale->refresh();
    }
}
