<?php

namespace Tests\Feature;

use App\Models\PedidoOnline;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Support\OnlineOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOnlineOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_lists_filters_and_opens_only_its_store_orders(): void
    {
        [$user, $store] = $this->context('principal');
        [, $foreignStore] = $this->context('ajena');
        $own = $this->order($store, 'WEB-PROPIO', OnlineOrderStatus::AWAITING_PAYMENT, '1712345678');
        $delivered = $this->order($store, 'WEB-ENTREGADO', OnlineOrderStatus::DELIVERED, '1799999999');
        $abandoned = $this->order($store, 'WEB-SIN-COMPROBANTE', OnlineOrderStatus::CANCELLED, '1711111111', false);
        $foreign = $this->order($foreignStore, 'WEB-AJENO', OnlineOrderStatus::PAID, '1700000000');

        $this->actingAs($user)->get(route('pedidos-online.index'))
            ->assertOk()
            ->assertSee($own->numero)
            ->assertSee($delivered->numero)
            ->assertDontSee($abandoned->numero)
            ->assertDontSee($foreign->numero)
            ->assertSee('Pedidos Online');

        $this->actingAs($user)->get(route('pedidos-online.index', ['estado' => OnlineOrderStatus::DELIVERED]))
            ->assertOk()
            ->assertSee($delivered->numero)
            ->assertSee('1 resultados');

        $this->actingAs($user)->get(route('pedidos-online.show', $own->id))
            ->assertOk()
            ->assertSee('1712345678');
        $this->actingAs($user)->get(route('pedidos-online.show', $foreign->id))->assertNotFound();
        $this->actingAs($user)->get(route('pedidos-online.show', $abandoned->id))->assertNotFound();
    }

    public function test_administrator_advances_fulfillment_flow_and_customer_uses_signed_tracking(): void
    {
        [$user, $store] = $this->context('seguimiento');
        $order = $this->order($store, 'WEB-TRACKING', OnlineOrderStatus::PAID, '1712345678');
        $order->forceFill(['pagado_at' => now()])->save();

        $this->actingAs($user)->patch(route('pedidos-online.status', $order->id), [
            'estado' => OnlineOrderStatus::PREPARING,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(OnlineOrderStatus::PREPARING, $order->fresh()->estado);
        $this->assertNotNull($order->fresh()->preparando_at);

        $this->actingAs($user)->patch(route('pedidos-online.status', $order->id), [
            'estado' => OnlineOrderStatus::DELIVERED,
        ])->assertStatus(409);

        $this->actingAs($user)->patch(route('pedidos-online.status', $order->id), [
            'estado' => OnlineOrderStatus::SHIPPED,
        ])->assertSessionHasErrors('guia_envio');

        Storage::fake('local');
        $this->actingAs($user)->patch(route('pedidos-online.status', $order->id), [
            'estado' => OnlineOrderStatus::SHIPPED,
            'guia_envio' => UploadedFile::fake()->image('guia.jpg', 900, 1200),
        ])->assertRedirect()->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(OnlineOrderStatus::SHIPPED, $order->estado);
        $this->assertNotNull($order->guia_envio_subida_at);
        Storage::disk('local')->assertExists($order->guia_envio_path);

        $trackingUrl = URL::temporarySignedRoute('storefront.orders.tracking', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);
        $this->get($trackingUrl)->assertOk()->assertSee($order->numero)->assertSee('Pedido enviado')->assertSee('Tu guía ya está disponible');
        $guideUrl = URL::temporarySignedRoute('storefront.orders.shipping-guide', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);
        $this->get($guideUrl)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=guia-'.$order->numero.'.jpg');
        $downloadUrl = URL::temporarySignedRoute('storefront.orders.shipping-guide', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
            'download' => 1,
        ]);
        $this->get($downloadUrl)->assertOk()->assertDownload('guia-'.$order->numero.'.jpg');
        $this->get(route('storefront.orders.tracking', ['slug' => $store->slug, 'operation' => $order->operacion_id]))->assertForbidden();
    }

    public function test_customer_can_rate_a_delivered_order_only_once_through_a_signed_link(): void
    {
        [, $store] = $this->context('calificacion');
        $order = $this->order($store, 'WEB-CALIFICAR', OnlineOrderStatus::DELIVERED, '1712345678');
        $order->forceFill(['entregado_at' => now()])->save();

        $trackingUrl = URL::temporarySignedRoute('storefront.orders.tracking', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);
        $this->get($trackingUrl)
            ->assertOk()
            ->assertSee('¿Cómo fue tu experiencia?')
            ->assertSee('Enviar calificación');

        $ratingUrl = URL::temporarySignedRoute('storefront.orders.rate', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);
        $this->post($ratingUrl, [
            'calificacion' => 5,
            'comentario' => 'Excelente atención y entrega.',
        ])->assertRedirect(route('storefront.show', ['slug' => $store->slug]))
            ->assertSessionHas('purchase_thanks');

        $order->refresh();
        $this->assertSame(5, $order->calificacion);
        $this->assertSame('Excelente atención y entrega.', $order->comentario_calificacion);
        $this->assertNotNull($order->calificado_at);

        $this->get(route('storefront.show', ['slug' => $store->slug]))
            ->assertOk()
            ->assertSee('¡Gracias por tu compra y por compartir tu experiencia!');
        $this->get($trackingUrl)
            ->assertRedirect(route('storefront.show', ['slug' => $store->slug]))
            ->assertSessionHas('order_access_closed');
        $this->post($ratingUrl, ['calificacion' => 1])->assertSessionHasErrors('calificacion');
        $this->assertSame(5, $order->fresh()->calificacion);

        $this->post(route('storefront.orders.rate', [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]), ['calificacion' => 5])->assertForbidden();
    }

    public function test_delivered_order_tracking_and_rating_expire_after_twenty_four_hours(): void
    {
        [, $store] = $this->context('seguimiento-expirado');
        $order = $this->order($store, 'WEB-EXPIRADO', OnlineOrderStatus::DELIVERED, '1712345678');
        $order->forceFill(['entregado_at' => now()->subDay()->subMinute()])->save();

        $trackingUrl = URL::temporarySignedRoute('storefront.orders.tracking', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);
        $ratingUrl = URL::temporarySignedRoute('storefront.orders.rate', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);

        $this->get($trackingUrl)
            ->assertRedirect(route('storefront.show', ['slug' => $store->slug]))
            ->assertSessionHas('order_access_closed');
        $this->post($ratingUrl, ['calificacion' => 5])
            ->assertSessionHasErrors('calificacion');
        $this->post(route('storefront.orders.lookup.store', ['slug' => $store->slug]), [
            'numero_pedido' => $order->numero,
            'cedula' => $order->cliente_dni,
        ])->assertSessionHasErrors('pedido');

        $this->assertNull($order->fresh()->calificado_at);
    }

    private function context(string $name): array
    {
        $suffix = $name.'-'.Str::lower(Str::random(6));
        $store = Tienda::create(['nombre' => 'Tienda '.$name, 'slug' => $suffix, 'estado' => 'activa', 'publicada' => true]);
        $role = Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR]);
        $user = User::create(['rol_id' => $role->id, 'tienda_id' => $store->id, 'name' => 'Admin', 'email' => $suffix.'@orders.test', 'password' => 'password', 'activo' => true]);

        return [$user, $store];
    }

    private function order(Tienda $store, string $number, string $status, string $dni, bool $reported = true): PedidoOnline
    {
        return PedidoOnline::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'operacion_id' => (string) Str::uuid(),
            'operacion_hash' => hash('sha256', Str::random()),
            'numero' => $number.'-'.$store->id,
            'estado' => $status,
            'cliente_nombre' => 'Ana',
            'cliente_apellido' => 'Paredes',
            'cliente_dni' => $dni,
            'cliente_email' => 'ana-'.$store->id.'@example.com',
            'cliente_telefono' => '+593991234567',
            'requiere_factura' => false,
            'subtotal' => 50,
            'descuento' => 0,
            'envio' => 0,
            'total' => 50,
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'pago_reportado_at' => $reported ? now() : null,
        ]);
    }
}
