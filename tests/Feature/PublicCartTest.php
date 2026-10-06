<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoVariante;
use App\Models\Tienda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_add_a_variant_and_server_recalculates_cart_price(): void
    {
        [$store, $variant] = $this->catalogContext('agregar', 8, 120, 90);
        ProductoImagen::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $variant->producto_id,
            'ruta' => 'productos/'.$store->id.'/azul-m.jpg',
            'talla' => 'M',
            'color' => 'Azul',
            'orden' => 0,
            'principal' => true,
        ]);

        $this->from($store->publicUrl().'#catalogo')->post(route('storefront.cart.store', $store->slug), [
            'variante_id' => $variant->id,
            'cantidad' => 2,
            'precio' => 0.01,
            'nombre' => 'Manipulado',
        ])->assertRedirect($store->publicUrl().'#catalogo')
            ->assertSessionHas('cart_success');

        $this->assertSame([$variant->id => 2], session('public_cart_store_'.$store->id));

        $this->get(route('storefront.cart.show', $store->slug))
            ->assertOk()
            ->assertSee('Carrito (2)')
            ->assertSee('M</b> / Azul', false)
            ->assertSee('productos/'.$store->id.'/azul-m.jpg')
            ->assertSee(route('storefront.product.show', [
                'slug' => $store->slug,
                'code' => $variant->producto->codigo,
                'variante' => $variant->id,
                'cantidad' => 2,
            ]))
            ->assertSee('$ 90.00')
            ->assertSee('$ 180.00')
            ->assertDontSee('Manipulado');
    }

    public function test_repeated_additions_accumulate_but_never_exceed_current_variant_stock(): void
    {
        [$store, $variant] = $this->catalogContext('limites', 5);

        $this->post(route('storefront.cart.store', $store->slug), ['variante_id' => $variant->id, 'cantidad' => 3])
            ->assertSessionHasNoErrors();
        $this->post(route('storefront.cart.store', $store->slug), ['variante_id' => $variant->id, 'cantidad' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame([$variant->id => 5], session('public_cart_store_'.$store->id));

        $this->from($store->publicUrl())
            ->post(route('storefront.cart.store', $store->slug), ['variante_id' => $variant->id, 'cantidad' => 1])
            ->assertRedirect($store->publicUrl())
            ->assertSessionHasErrors('cantidad');

        $this->assertSame([$variant->id => 5], session('public_cart_store_'.$store->id));
    }

    public function test_foreign_inactive_and_out_of_stock_variants_cannot_be_added(): void
    {
        [$store] = $this->catalogContext('principal');
        [, $foreignVariant] = $this->catalogContext('ajena');

        $this->from($store->publicUrl())
            ->post(route('storefront.cart.store', $store->slug), ['variante_id' => $foreignVariant->id, 'cantidad' => 1])
            ->assertSessionHasErrors('variante_id');

        [$inactiveStore, $inactiveVariant, $inactiveProduct] = $this->catalogContext('inactivo');
        $inactiveProduct->forceFill(['activo' => false])->save();
        $this->post(route('storefront.cart.store', $inactiveStore->slug), ['variante_id' => $inactiveVariant->id, 'cantidad' => 1])
            ->assertSessionHasErrors('variante_id');

        [$emptyStore, $emptyVariant] = $this->catalogContext('agotado', 0);
        $this->post(route('storefront.cart.store', $emptyStore->slug), ['variante_id' => $emptyVariant->id, 'cantidad' => 1])
            ->assertSessionHasErrors('cantidad');

        $this->assertNull(session('public_cart_store_'.$store->id));
        $this->assertNull(session('public_cart_store_'.$inactiveStore->id));
        $this->assertNull(session('public_cart_store_'.$emptyStore->id));
    }

    public function test_customer_can_update_remove_and_clear_cart_lines(): void
    {
        [$store, $first] = $this->catalogContext('acciones', 10);
        $secondProduct = $first->producto;
        $second = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $secondProduct->id,
            'talla' => 'L',
            'color' => 'Negro',
            'stock' => 6,
            'stock_minimo' => 2,
        ]);

        foreach ([$first, $second] as $variant) {
            $this->post(route('storefront.cart.store', $store->slug), ['variante_id' => $variant->id, 'cantidad' => 1]);
        }

        $this->patch(route('storefront.cart.update', ['slug' => $store->slug, 'variant' => $first->id]), ['cantidad' => 4])
            ->assertRedirect(route('storefront.cart.show', $store->slug));
        $this->assertSame([$first->id => 4, $second->id => 1], session('public_cart_store_'.$store->id));

        $this->delete(route('storefront.cart.destroy', ['slug' => $store->slug, 'variant' => $first->id]))
            ->assertRedirect(route('storefront.cart.show', $store->slug));
        $this->assertSame([$second->id => 1], session('public_cart_store_'.$store->id));

        $this->delete(route('storefront.cart.clear', $store->slug))
            ->assertRedirect(route('storefront.cart.show', $store->slug));
        $this->assertNull(session('public_cart_store_'.$store->id));
    }

    public function test_cart_is_reconciled_when_stock_or_catalog_state_changes(): void
    {
        [$store, $variant, $product] = $this->catalogContext('conciliacion', 8);
        $this->post(route('storefront.cart.store', $store->slug), ['variante_id' => $variant->id, 'cantidad' => 6]);

        $variant->forceFill(['stock' => 2])->save();
        $this->get(route('storefront.cart.show', $store->slug))
            ->assertOk()
            ->assertSee('Ajustamos el carrito')
            ->assertSee('Carrito (2)');
        $this->assertSame([$variant->id => 2], session('public_cart_store_'.$store->id));

        $product->forceFill(['activo' => false])->save();
        $this->get(route('storefront.cart.show', $store->slug))
            ->assertOk()
            ->assertSee('Tu carrito está vacío');
        $this->assertNull(session('public_cart_store_'.$store->id));
    }

    public function test_customer_can_replace_the_cart_variant_from_product_detail(): void
    {
        [$store, $original] = $this->catalogContext('editar-variante', 10);
        $replacement = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $original->producto_id,
            'talla' => 'L',
            'color' => 'Negro',
            'stock' => 8,
            'stock_minimo' => 2,
        ]);

        $this->post(route('storefront.cart.store', $store->slug), [
            'variante_id' => $original->id,
            'cantidad' => 2,
        ]);

        $this->patch(route('storefront.cart.update', ['slug' => $store->slug, 'variant' => $original->id]), [
            'nueva_variante_id' => $replacement->id,
            'cantidad' => 3,
        ])->assertRedirect(route('storefront.cart.show', $store->slug));

        $this->assertSame([$replacement->id => 3], session('public_cart_store_'.$store->id));
        $this->get(route('storefront.cart.show', $store->slug))
            ->assertSee('L</b> / Negro', false)
            ->assertDontSee('M</b> / Azul', false);
    }

    public function test_each_public_store_has_an_independent_cart(): void
    {
        [$firstStore, $firstVariant] = $this->catalogContext('uno');
        [$secondStore, $secondVariant] = $this->catalogContext('dos');

        $this->post(route('storefront.cart.store', $firstStore->slug), ['variante_id' => $firstVariant->id, 'cantidad' => 2]);
        $this->post(route('storefront.cart.store', $secondStore->slug), ['variante_id' => $secondVariant->id, 'cantidad' => 3]);

        $this->get($firstStore->publicUrl())->assertSee('Carrito (2)')->assertDontSee('Carrito (3)');
        $this->get($secondStore->publicUrl())->assertSee('Carrito (3)')->assertDontSee('Carrito (2)');
    }

    public function test_cart_rejects_invalid_quantities_and_unpublished_store_operations(): void
    {
        [$store, $variant] = $this->catalogContext('validacion');

        foreach ([0, -1, 100, 1.5, 'texto'] as $quantity) {
            $this->from($store->publicUrl())
                ->post(route('storefront.cart.store', $store->slug), ['variante_id' => $variant->id, 'cantidad' => $quantity])
                ->assertSessionHasErrors('cantidad');
        }

        $store->forceFill(['publicada' => false])->save();
        $this->post(route('storefront.cart.store', $store->slug), ['variante_id' => $variant->id, 'cantidad' => 1])
            ->assertNotFound();
    }

    /** @return array{Tienda,ProductoVariante,Producto} */
    private function catalogContext(
        string $suffix,
        int $stock = 10,
        float $price = 80,
        ?float $offer = null,
    ): array {
        $token = $suffix.'-'.Str::lower(Str::random(5));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$token,
            'estado' => 'activa',
            'publicada' => true,
            'simbolo_moneda' => '$',
            'color_primario' => '#d91468',
        ]);
        $category = Categoria::create([
            'tienda_id' => $store->id,
            'nombre' => 'Camisas '.$suffix,
            'nombre_normalizado' => 'camisas '.$suffix,
            'slug' => 'camisas-'.$token,
            'activo' => true,
        ]);
        $product = Producto::create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'CART-'.Str::upper(Str::random(10)),
            'nombre' => 'Camisa '.$suffix,
            'precio_compra' => 30,
            'precio_venta' => $price,
            'precio_oferta' => $offer,
            'activo' => true,
        ]);
        $variant = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Azul',
            'stock' => $stock,
            'stock_minimo' => 2,
        ]);

        return [$store, $variant, $product];
    }
}
