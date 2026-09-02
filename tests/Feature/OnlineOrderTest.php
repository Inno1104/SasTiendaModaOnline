<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\PedidoOnline;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Tienda;
use App\Services\GuestCheckoutManager;
use App\Services\OnlineOrderCreator;
use App\Services\PublicCartManager;
use App\Support\OnlineOrderStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnlineOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_creates_an_awaiting_payment_order_with_commercial_and_address_snapshots(): void
    {
        [$store, $variant, $product] = $this->context('completo', 8, 100, 75);
        $this->prepareCheckout($store, $variant, 2, true);
        $operationId = session('online_order_operation_store_'.$store->id);

        $this->post(route('storefront.orders.store', $store->slug), [
            'operacion_id' => $operationId,
            'confirma_pedido' => '1',
        ])->assertRedirect($store->publicUrl().'#pedido-confirmado')
            ->assertSessionHas('online_order');

        $order = PedidoOnline::withoutGlobalScopes()->with('detalles', 'direcciones')->sole();
        $this->assertSame(OnlineOrderStatus::AWAITING_PAYMENT, $order->estado);
        $this->assertMatchesRegularExpression('/^WEB-'.$store->id.'-'.now()->year.'-000001$/', $order->numero);
        $this->assertSame('150.00', $order->subtotal);
        $this->assertSame('150.00', $order->total);
        $this->assertSame('USD', $order->moneda);
        $this->assertSame('invitada@example.com', $order->cliente_email);
        $this->assertTrue($order->requiere_factura);

        $detail = $order->detalles->sole();
        $this->assertSame($product->id, $detail->producto_id);
        $this->assertSame($variant->id, $detail->variante_id);
        $this->assertSame('Producto completo', $detail->producto_nombre);
        $this->assertSame('M', $detail->talla);
        $this->assertSame('Negro', $detail->color);
        $this->assertSame(2, $detail->cantidad);
        $this->assertSame('75.00', $detail->precio_unitario);
        $this->assertSame('150.00', $detail->subtotal);

        $this->assertCount(2, $order->direcciones);
        $this->assertSame(['entrega', 'fiscal'], $order->direcciones->sortBy('tipo')->pluck('tipo')->values()->all());
        $this->assertSame('Av. Fiscal 10', $order->direcciones->firstWhere('tipo', 'fiscal')->direccion);
        $this->assertSame(8, $variant->fresh()->stock, 'Un pedido sin pago no debe descontar inventario.');
        $this->assertNull(session('public_cart_store_'.$store->id));
        $this->assertNull(session('guest_checkout_store_'.$store->id));
    }

    public function test_order_recalculates_current_price_and_rejects_insufficient_stock_atomically(): void
    {
        [$store, $variant, $product] = $this->context('cambios', 5, 50);
        $this->prepareCheckout($store, $variant, 4);
        $product->forceFill(['precio_oferta' => 40])->save();
        $variant->forceFill(['stock' => 3])->save();

        $this->from($store->publicUrl().'#checkout')
            ->post(route('storefront.orders.store', $store->slug), [
                'operacion_id' => session('online_order_operation_store_'.$store->id),
                'confirma_pedido' => '1',
            ])->assertSessionHasErrors('pedido');

        $this->assertDatabaseCount('pedidos_online', 0);
        $this->assertDatabaseCount('pedido_online_detalles', 0);
        $this->assertDatabaseCount('pedido_online_direcciones', 0);
        $this->assertDatabaseCount('pedido_online_contadores', 0);
        $this->assertNotNull(session('public_cart_store_'.$store->id));
    }

    public function test_order_creation_is_idempotent_for_the_same_store_operation(): void
    {
        [$store, $variant] = $this->context('idempotente');
        $this->prepareCheckout($store, $variant, 1);
        $cartManager = app(PublicCartManager::class);
        $checkoutManager = app(GuestCheckoutManager::class);
        $session = app('session')->driver();
        $operationId = $checkoutManager->orderOperationId($store, $session);
        $cart = $cartManager->snapshot($store, $session);
        $checkout = $checkoutManager->snapshot($store, $session);

        $first = app(OnlineOrderCreator::class)->create($store, $cart, $checkout, $operationId);
        $second = app(OnlineOrderCreator::class)->create($store, $cart, $checkout, $operationId);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('pedidos_online', 1);
        $this->assertDatabaseCount('pedido_online_detalles', 1);
        $this->assertDatabaseCount('pedido_online_direcciones', 1);
        $this->assertSame(1, DB::table('pedido_online_contadores')->value('ultimo_numero'));
    }

    public function test_order_numbers_are_sequential_and_isolated_by_store_and_year(): void
    {
        [$firstStore, $firstVariant] = $this->context('numeracion-uno');
        $this->prepareCheckout($firstStore, $firstVariant, 1);
        $first = $this->createUsingServices($firstStore);

        app(GuestCheckoutManager::class)->rotateOrderOperation($firstStore, app('session')->driver());
        $second = $this->createUsingServices($firstStore);

        [$otherStore, $otherVariant] = $this->context('numeracion-dos');
        $this->prepareCheckout($otherStore, $otherVariant, 1);
        $other = $this->createUsingServices($otherStore);

        $this->assertStringEndsWith('000001', $first->numero);
        $this->assertStringEndsWith('000002', $second->numero);
        $this->assertStringEndsWith('000001', $other->numero);
        $this->assertNotSame($first->numero, $other->numero);
    }

    public function test_operation_token_cannot_be_replaced_by_the_browser(): void
    {
        [$store, $variant] = $this->context('token');
        $this->prepareCheckout($store, $variant, 1);

        $this->post(route('storefront.orders.store', $store->slug), [
            'operacion_id' => (string) Str::uuid(),
            'confirma_pedido' => '1',
        ])->assertSessionHasErrors('operacion_id');

        $this->assertDatabaseCount('pedidos_online', 0);
    }

    public function test_online_order_status_machine_only_allows_defined_transitions(): void
    {
        $this->assertTrue(OnlineOrderStatus::canTransition('pendiente', 'esperando_pago'));
        $this->assertTrue(OnlineOrderStatus::canTransition('esperando_pago', 'pagado'));
        $this->assertTrue(OnlineOrderStatus::canTransition('pagado', 'preparando'));
        $this->assertTrue(OnlineOrderStatus::canTransition('preparando', 'enviado'));
        $this->assertTrue(OnlineOrderStatus::canTransition('enviado', 'entregado'));
        $this->assertFalse(OnlineOrderStatus::canTransition('entregado', 'preparando'));
        $this->assertFalse(OnlineOrderStatus::canTransition('cancelado', 'pagado'));
        $this->assertSame([
            'pendiente', 'esperando_pago', 'pagado', 'preparando', 'enviado', 'entregado', 'cancelado',
        ], OnlineOrderStatus::ALL);
    }

    public function test_database_rejects_invalid_order_state_and_amounts(): void
    {
        [$store] = $this->context('constraints');

        $this->expectException(QueryException::class);
        DB::table('pedidos_online')->insert([
            'tienda_id' => $store->id,
            'operacion_id' => (string) Str::uuid(),
            'operacion_hash' => hash('sha256', 'invalid'),
            'numero' => 'WEB-INVALIDO',
            'estado' => 'estado_inventado',
            'cliente_nombre' => 'Ana',
            'cliente_apellido' => 'Paredes',
            'cliente_email' => 'ana@example.com',
            'cliente_telefono' => '+593991234567',
            'requiere_factura' => false,
            'subtotal' => 10,
            'descuento' => 0,
            'envio' => 0,
            'total' => 10,
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_online_order_schema_contains_traceability_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('pedidos_online', [
            'tienda_id', 'operacion_id', 'operacion_hash', 'numero', 'estado',
            'cliente_email', 'subtotal', 'total', 'moneda', 'pagado_at', 'cancelado_at',
        ]));
        $this->assertTrue(Schema::hasColumns('pedido_online_detalles', [
            'pedido_online_id', 'producto_id', 'variante_id', 'producto_nombre',
            'talla', 'color', 'cantidad', 'precio_unitario', 'subtotal',
        ]));
        $this->assertTrue(Schema::hasColumns('pedido_online_direcciones', [
            'pedido_online_id', 'tipo', 'destinatario', 'pais_codigo', 'provincia', 'ciudad', 'direccion',
        ]));
    }

    /** @return array{Tienda,ProductoVariante,Producto} */
    private function context(string $suffix, int $stock = 10, float $price = 25, ?float $offer = null): array
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
            'codigo' => 'ONLINE-'.Str::upper(Str::random(8)),
            'nombre' => 'Producto '.$suffix,
            'precio_venta' => $price,
            'precio_oferta' => $offer,
            'activo' => true,
        ]);
        $variant = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => $stock,
            'stock_minimo' => 2,
        ]);

        return [$store, $variant, $product];
    }

    private function prepareCheckout(Tienda $store, ProductoVariante $variant, int $quantity, bool $invoice = false): void
    {
        $this->post(route('storefront.cart.store', $store->slug), [
            'variante_id' => $variant->id,
            'cantidad' => $quantity,
        ])->assertSessionHasNoErrors();

        $payload = [
            'cliente' => ['nombre' => 'Ana', 'apellido' => 'Paredes', 'email' => 'invitada@example.com', 'telefono' => '+593991234567'],
            'entrega' => ['pais_codigo' => 'EC', 'provincia' => 'Pichincha', 'ciudad' => 'Quito', 'codigo_postal' => '170101', 'direccion' => 'Av. Central 123', 'referencia' => 'Puerta azul'],
            'fiscal' => $invoice
                ? ['requiere_factura' => '1', 'tipo_identificacion' => 'ruc_nit', 'identificacion' => '1790012345001', 'razon_social' => 'Comercial Andina', 'direccion_fiscal' => 'Av. Fiscal 10']
                : ['requiere_factura' => '0', 'tipo_identificacion' => null, 'identificacion' => null, 'razon_social' => null, 'direccion_fiscal' => null],
            'acepta_privacidad' => '1',
        ];
        $this->post(route('storefront.checkout.data.store', $store->slug), $payload)
            ->assertSessionHasNoErrors();
        $this->get($store->publicUrl())->assertOk();
    }

    private function createUsingServices(Tienda $store): PedidoOnline
    {
        $checkout = app(GuestCheckoutManager::class);
        $session = app('session')->driver();

        return app(OnlineOrderCreator::class)->create(
            $store,
            app(PublicCartManager::class)->snapshot($store, $session),
            $checkout->snapshot($store, $session),
            $checkout->orderOperationId($store, $session),
        );
    }
}
