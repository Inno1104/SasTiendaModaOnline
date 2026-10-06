<?php

namespace Tests\Feature;

use App\Models\PedidoOnline;
use App\Models\Tienda;
use App\Support\OnlineOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicOrderLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_finds_only_matching_store_order_with_number_and_dni(): void
    {
        $store = Tienda::create(['nombre'=>'Tienda pública','slug'=>'tienda-publica','estado'=>'activa','publicada'=>true]);
        $other = Tienda::create(['nombre'=>'Otra tienda','slug'=>'otra-tienda','estado'=>'activa','publicada'=>true]);
        $order = $this->order($store, 'WEB-1-2026-000007', '51692817');
        $this->order($other, 'WEB-1-2026-000007', '51692817');

        $this->get(route('storefront.orders.lookup', $store->slug))
            ->assertOk()->assertSee('Buscar mi pedido');

        $response = $this->post(route('storefront.orders.lookup.store', $store->slug), [
            'numero_pedido' => strtolower($order->numero),
            'cedula' => $order->cliente_dni,
        ]);
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))
            ->assertOk()->assertSee($order->numero);

        $this->from(route('storefront.orders.lookup', $store->slug))
            ->post(route('storefront.orders.lookup.store', $store->slug), [
                'numero_pedido' => $order->numero,
                'cedula' => '9999999999',
            ])->assertRedirect()->assertSessionHasErrors('pedido');
    }

    private function order(Tienda $store, string $number, string $dni): PedidoOnline
    {
        return PedidoOnline::withoutGlobalScopes()->create([
            'tienda_id'=>$store->id,'operacion_id'=>(string)Str::uuid(),'operacion_hash'=>hash('sha256',Str::random()),
            'numero'=>$number,'estado'=>OnlineOrderStatus::PAID,'cliente_nombre'=>'Freddy','cliente_apellido'=>'Quilca',
            'cliente_dni'=>$dni,'cliente_email'=>'cliente@example.com','cliente_telefono'=>'0999999999','requiere_factura'=>false,
            'subtotal'=>55,'descuento'=>0,'envio'=>0,'total'=>55,'moneda'=>'USD','simbolo_moneda'=>'$','pagado_at'=>now(),
        ]);
    }
}
