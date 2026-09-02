<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\PedidoOnline;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\StockReserva;
use App\Models\Tienda;
use App\Services\GuestCheckoutManager;
use App\Services\OnlineOrderCreator;
use App\Services\PublicCartManager;
use App\Support\OnlineOrderStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_order_reserves_each_variant_without_changing_physical_stock(): void
    {
        [$store, $first, $product] = $this->context('reserva', 6);
        $second = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'L',
            'color' => 'Azul',
            'stock' => 4,
            'stock_minimo' => 1,
        ]);
        $this->prepareCheckout($store, [[$first, 2], [$second, 3]]);

        $order = $this->createUsingServices($store);

        $this->assertSame(OnlineOrderStatus::AWAITING_PAYMENT, $order->estado);
        $this->assertNotNull($order->reserva_expira_at);
        $this->assertGreaterThanOrEqual(14, now()->diffInMinutes($order->reserva_expira_at));
        $this->assertSame([2, 3], $order->reservas->sortBy('variante_id')->pluck('cantidad')->values()->all());
        $this->assertSame([StockReserva::ACTIVE, StockReserva::ACTIVE], $order->reservas->pluck('estado')->all());
        $this->assertSame(6, $first->fresh()->stock);
        $this->assertSame(4, $second->fresh()->stock);
        $this->assertSame(4, $first->fresh()->stockDisponibleVenta());
        $this->assertSame(1, $second->fresh()->stockDisponibleVenta());
    }

    public function test_catalog_and_cart_use_physical_stock_minus_current_reservations(): void
    {
        [$store, $variant] = $this->context('disponible', 5);
        $this->prepareCheckout($store, [[$variant, 3]]);
        $this->createUsingServices($store);

        app(PublicCartManager::class)->clear($store, app('session')->driver());
        $this->get($store->publicUrl())
            ->assertOk()
            ->assertSee('2 disponibles')
            ->assertSee('M / Negro · 2 disponibles');

        $this->post(route('storefront.cart.store', $store->slug), [
            'variante_id' => $variant->id,
            'cantidad' => 3,
        ])->assertSessionHasErrors('cantidad');
        $this->post(route('storefront.cart.store', $store->slug), [
            'variante_id' => $variant->id,
            'cantidad' => 2,
        ])->assertSessionHasNoErrors();
    }

    public function test_two_orders_cannot_reserve_the_same_remaining_units(): void
    {
        [$store, $variant] = $this->context('competencia', 3);
        $this->prepareCheckout($store, [[$variant, 2]]);
        $session = app('session')->driver();
        $cart = app(PublicCartManager::class)->snapshot($store, $session);
        $checkout = app(GuestCheckoutManager::class)->snapshot($store, $session);

        app(OnlineOrderCreator::class)->create($store, $cart, $checkout, (string) Str::uuid());

        try {
            app(OnlineOrderCreator::class)->create($store, $cart, $checkout, (string) Str::uuid());
            $this->fail('La segunda reserva debía ser rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pedido', $exception->errors());
        }

        $this->assertDatabaseCount('pedidos_online', 1);
        $this->assertDatabaseCount('stock_reservas', 1);
        $this->assertSame(2, StockReserva::withoutGlobalScopes()->sum('cantidad'));
        $this->assertSame(3, $variant->fresh()->stock);
    }

    public function test_expired_reservations_are_released_and_order_is_cancelled_idempotently(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        try {
            [$store, $variant] = $this->context('vencimiento', 4);
            $this->prepareCheckout($store, [[$variant, 3]]);
            $order = $this->createUsingServices($store);
            $this->assertSame(1, $variant->fresh()->stockDisponibleVenta());

            Carbon::setTestNow('2026-09-01 10:16:00');
            $this->artisan('reservas:liberar-vencidas')->expectsOutput('Reservas liberadas: 1')->assertSuccessful();

            $reservation = StockReserva::withoutGlobalScopes()->sole();
            $this->assertSame(StockReserva::EXPIRED, $reservation->estado);
            $this->assertNotNull($reservation->liberada_at);
            $this->assertSame(OnlineOrderStatus::CANCELLED, $order->fresh()->estado);
            $this->assertSame('Reserva de inventario vencida', $order->fresh()->motivo_cancelacion);
            $this->assertSame(4, $variant->fresh()->stockDisponibleVenta());

            $this->artisan('reservas:liberar-vencidas')->expectsOutput('Reservas liberadas: 0')->assertSuccessful();
            $this->assertDatabaseCount('stock_reservas', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_unexpired_reservation_is_not_released(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        try {
            [$store, $variant] = $this->context('vigente', 4);
            $this->prepareCheckout($store, [[$variant, 2]]);
            $order = $this->createUsingServices($store);

            Carbon::setTestNow('2026-09-01 10:14:59');
            $this->artisan('reservas:liberar-vencidas')->expectsOutput('Reservas liberadas: 0')->assertSuccessful();
            $this->assertSame(StockReserva::ACTIVE, StockReserva::withoutGlobalScopes()->sole()->estado);
            $this->assertSame(OnlineOrderStatus::AWAITING_PAYMENT, $order->fresh()->estado);
            $this->assertSame(2, $variant->fresh()->stockDisponibleVenta());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_database_rejects_inconsistent_reservations(): void
    {
        [$store, $variant] = $this->context('integridad');
        $this->prepareCheckout($store, [[$variant, 1]]);
        $order = $this->createUsingServices($store);

        $this->expectException(QueryException::class);
        DB::table('stock_reservas')->insert([
            'tienda_id' => $store->id,
            'pedido_online_id' => $order->id,
            'variante_id' => $variant->id,
            'cantidad' => 0,
            'estado' => 'estado_invalido',
            'expira_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_reservation_schema_contains_expiration_and_traceability_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('stock_reservas', [
            'tienda_id', 'pedido_online_id', 'variante_id', 'cantidad', 'estado',
            'expira_at', 'liberada_at', 'consumida_at', 'motivo_liberacion',
        ]));
        $this->assertTrue(Schema::hasColumn('pedidos_online', 'reserva_expira_at'));
    }

    /** @return array{Tienda,ProductoVariante,Producto} */
    private function context(string $suffix, int $stock = 10): array
    {
        $token = $suffix.'-'.Str::lower(Str::random(5));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$token,
            'estado' => 'activa',
            'publicada' => true,
            'pais_codigo' => 'EC',
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'color_primario' => '#d91468',
        ]);
        $category = Categoria::create([
            'tienda_id' => $store->id,
            'nombre' => 'Ropa '.$suffix,
            'nombre_normalizado' => 'ropa '.$suffix,
            'slug' => 'ropa-'.$token,
            'activo' => true,
        ]);
        $product = Producto::create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'RES-'.Str::upper(Str::random(8)),
            'nombre' => 'Producto '.$suffix,
            'precio_venta' => 20,
            'activo' => true,
        ]);
        $variant = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => $stock,
            'stock_minimo' => 1,
        ]);

        return [$store, $variant, $product];
    }

    /** @param list<array{ProductoVariante,int}> $lines */
    private function prepareCheckout(Tienda $store, array $lines): void
    {
        foreach ($lines as [$variant, $quantity]) {
            $this->post(route('storefront.cart.store', $store->slug), [
                'variante_id' => $variant->id,
                'cantidad' => $quantity,
            ])->assertSessionHasNoErrors();
        }
        $this->post(route('storefront.checkout.data.store', $store->slug), [
            'cliente' => ['nombre' => 'Ana', 'apellido' => 'Paredes', 'email' => 'ana@example.com', 'telefono' => '+593991234567'],
            'entrega' => ['pais_codigo' => 'EC', 'provincia' => 'Pichincha', 'ciudad' => 'Quito', 'codigo_postal' => null, 'direccion' => 'Av. Central 123', 'referencia' => null],
            'fiscal' => ['requiere_factura' => '0', 'tipo_identificacion' => null, 'identificacion' => null, 'razon_social' => null, 'direccion_fiscal' => null],
            'acepta_privacidad' => '1',
        ])->assertSessionHasNoErrors();
    }

    private function createUsingServices(Tienda $store): PedidoOnline
    {
        $session = app('session')->driver();
        $checkout = app(GuestCheckoutManager::class);

        return app(OnlineOrderCreator::class)->create(
            $store,
            app(PublicCartManager::class)->snapshot($store, $session),
            $checkout->snapshot($store, $session),
            $checkout->orderOperationId($store, $session),
        );
    }
}
