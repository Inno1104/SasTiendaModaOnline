<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Tienda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_contact_delivery_and_fiscal_data_are_normalized_and_encrypted(): void
    {
        [$store, $variant] = $this->context('seguro');
        $this->addToCart($store, $variant);

        $payload = $this->validPayload();
        $payload['cliente']['nombre'] = '  Ana   María ';
        $payload['cliente']['email'] = ' ANA@EXAMPLE.COM ';
        $payload['cliente']['telefono'] = '+593 (99) 123-4567';
        $payload['entrega']['direccion'] = '  Av. Central   123 ';
        $payload['fiscal'] = [
            'requiere_factura' => '1',
            'tipo_identificacion' => 'ruc_nit',
            'identificacion' => ' 179 001 234-5 ',
            'razon_social' => '  Comercial   Andina ',
            'direccion_fiscal' => ' Av. Fiscal 10 ',
        ];

        $this->post(route('storefront.checkout.data.store', $store->slug), $payload)
            ->assertRedirect($store->publicUrl().'#checkout')
            ->assertSessionHas('checkout_success')
            ->assertSessionHasNoErrors();

        $encrypted = session('guest_checkout_store_'.$store->id);
        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('ana@example.com', $encrypted);
        $this->assertStringNotContainsString('Av. Central', $encrypted);

        $stored = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Ana María', $stored['cliente']['nombre']);
        $this->assertSame('ana@example.com', $stored['cliente']['email']);
        $this->assertSame('+593991234567', $stored['cliente']['telefono']);
        $this->assertSame('Av. Central 123', $stored['entrega']['direccion']);
        $this->assertSame('179001234-5', $stored['fiscal']['identificacion']);
        $this->assertSame('Comercial Andina', $stored['fiscal']['razon_social']);
        $this->assertArrayNotHasKey('acepta_privacidad', $stored);
    }

    public function test_guest_can_buy_without_invoice_and_fiscal_values_are_discarded(): void
    {
        [$store, $variant] = $this->context('sin-factura');
        $this->addToCart($store, $variant);
        $payload = $this->validPayload();
        $payload['fiscal'] = [
            'requiere_factura' => '0',
            'tipo_identificacion' => 'ruc_nit',
            'identificacion' => '1790012345001',
            'razon_social' => 'No debe guardarse',
            'direccion_fiscal' => 'No debe guardarse',
        ];

        $this->post(route('storefront.checkout.data.store', $store->slug), $payload)
            ->assertSessionHasNoErrors();

        $stored = json_decode(
            Crypt::decryptString(session('guest_checkout_store_'.$store->id)),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertFalse($stored['fiscal']['requiere_factura']);
        $this->assertNull($stored['fiscal']['identificacion']);
        $this->assertNull($stored['fiscal']['razon_social']);
    }

    public function test_invoice_requires_complete_and_valid_fiscal_identification(): void
    {
        [$store, $variant] = $this->context('fiscal');
        $this->addToCart($store, $variant);
        $payload = $this->validPayload();
        $payload['fiscal'] = [
            'requiere_factura' => '1',
            'tipo_identificacion' => '',
            'identificacion' => '***',
            'razon_social' => '',
            'direccion_fiscal' => '',
        ];

        $this->from($store->publicUrl().'#checkout')
            ->post(route('storefront.checkout.data.store', $store->slug), $payload)
            ->assertSessionHasErrors([
                'fiscal.tipo_identificacion',
                'fiscal.identificacion',
                'fiscal.razon_social',
            ]);

        $this->assertNull(session('guest_checkout_store_'.$store->id));
    }

    public function test_contact_address_country_and_privacy_validation_are_enforced(): void
    {
        [$store, $variant] = $this->context('validacion');
        $this->addToCart($store, $variant);
        $payload = $this->validPayload();
        $payload['cliente']['nombre'] = '<script>';
        $payload['cliente']['email'] = 'correo-invalido';
        $payload['cliente']['telefono'] = '12';
        $payload['entrega']['pais_codigo'] = 'XX';
        $payload['entrega']['direccion'] = '';
        unset($payload['acepta_privacidad']);

        $this->post(route('storefront.checkout.data.store', $store->slug), $payload)
            ->assertSessionHasErrors([
                'cliente.nombre', 'cliente.email', 'cliente.telefono',
                'entrega.pais_codigo', 'entrega.direccion', 'acepta_privacidad',
            ]);
    }

    public function test_checkout_data_is_isolated_between_public_stores(): void
    {
        [$firstStore, $firstVariant] = $this->context('primera');
        [$secondStore, $secondVariant] = $this->context('segunda');
        $this->addToCart($firstStore, $firstVariant);
        $this->addToCart($secondStore, $secondVariant);

        $this->post(route('storefront.checkout.data.store', $firstStore->slug), $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->get($firstStore->publicUrl())->assertSee('invitada@example.com');
        $this->get($secondStore->publicUrl())->assertDontSee('invitada@example.com');
        $this->assertNotNull(session('guest_checkout_store_'.$firstStore->id));
        $this->assertNull(session('guest_checkout_store_'.$secondStore->id));
    }

    public function test_checkout_requires_a_valid_cart_and_a_published_store(): void
    {
        [$store, $variant] = $this->context('carrito');

        $this->from($store->publicUrl())
            ->post(route('storefront.checkout.data.store', $store->slug), $this->validPayload())
            ->assertSessionHasErrors('carrito');

        $this->addToCart($store, $variant);
        $store->forceFill(['publicada' => false])->save();
        $this->post(route('storefront.checkout.data.store', $store->slug), $this->validPayload())
            ->assertNotFound();
    }

    public function test_corrupted_checkout_session_is_removed_without_exposing_an_error(): void
    {
        [$store, $variant] = $this->context('corrupto');
        $this->addToCart($store, $variant);

        $this->withSession(['guest_checkout_store_'.$store->id => 'contenido-alterado'])
            ->get($store->publicUrl())
            ->assertOk()
            ->assertSee('Compra como invitado');

        $this->assertNull(session('guest_checkout_store_'.$store->id));
    }

    /** @return array{Tienda,ProductoVariante} */
    private function context(string $suffix): array
    {
        $token = $suffix.'-'.Str::lower(Str::random(5));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$token,
            'estado' => 'activa',
            'publicada' => true,
            'pais_codigo' => 'EC',
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
            'codigo' => 'CHECKOUT-'.Str::upper(Str::random(8)),
            'nombre' => 'Producto '.$suffix,
            'precio_venta' => 25,
            'activo' => true,
        ]);
        $variant = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => 10,
            'stock_minimo' => 2,
        ]);

        return [$store, $variant];
    }

    private function addToCart(Tienda $store, ProductoVariante $variant): void
    {
        $this->post(route('storefront.cart.store', $store->slug), [
            'variante_id' => $variant->id,
            'cantidad' => 2,
        ])->assertSessionHasNoErrors();
    }

    /** @return array<string,mixed> */
    private function validPayload(): array
    {
        return [
            'cliente' => [
                'nombre' => 'Ana',
                'apellido' => 'Paredes',
                'email' => 'invitada@example.com',
                'telefono' => '+593991234567',
            ],
            'entrega' => [
                'pais_codigo' => 'EC',
                'provincia' => 'Pichincha',
                'ciudad' => 'Quito',
                'codigo_postal' => '170101',
                'direccion' => 'Av. Central 123',
                'referencia' => 'Puerta azul',
            ],
            'fiscal' => [
                'requiere_factura' => '0',
                'tipo_identificacion' => null,
                'identificacion' => null,
                'razon_social' => null,
                'direccion_fiscal' => null,
            ],
            'acepta_privacidad' => '1',
        ];
    }
}
