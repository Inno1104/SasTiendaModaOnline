<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Tienda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_only_exposes_active_products_and_categories_from_requested_store(): void
    {
        $store = $this->publishedStore('principal');
        $otherStore = $this->publishedStore('ajena');
        $category = $this->category($store, 'Camisas');
        $inactiveCategory = $this->category($store, 'Oculta', false);
        $brand = $this->brand($store, 'Andina');

        $visible = $this->product($store, $category, 'Camisa de lino', [
            'marca_id' => $brand->id,
            'descripcion' => 'Prenda ligera para verano.',
            'imagen' => 'productos/camisa-lino.webp',
        ]);
        $this->variant($visible, 'M', 'Azul', 7);
        $this->variant($visible, 'L', 'Blanco', 0);

        $inactive = $this->product($store, $category, 'Producto desactivado', ['activo' => false]);
        $this->variant($inactive, 'U', 'Negro', 20);
        $hiddenByCategory = $this->product($store, $inactiveCategory, 'Producto de categoría oculta');
        $this->variant($hiddenByCategory, 'S', 'Rojo', 9);

        $foreignCategory = $this->category($otherStore, 'Camisas ajenas');
        $foreign = $this->product($otherStore, $foreignCategory, 'Producto de otra tienda');
        $this->variant($foreign, 'XL', 'Verde', 30);

        $response = $this->get(route('storefront.show', $store->slug));

        $response->assertOk()
            ->assertSee('Camisa de lino')
            ->assertSee('Camisas')
            ->assertSee('Andina')
            ->assertSee('productos/camisa-lino.webp', false)
            ->assertSee('M</b> / Azul', false)
            ->assertSee('L</b> / Blanco', false)
            ->assertSee('7 disponibles')
            ->assertDontSee('Producto desactivado')
            ->assertDontSee('Producto de categoría oculta')
            ->assertDontSee('Producto de otra tienda')
            ->assertDontSee('Camisas ajenas')
            ->assertSee('css/storefront-catalog.css', false);
    }

    public function test_catalog_calculates_offer_and_shows_exact_availability_by_variant(): void
    {
        $store = $this->publishedStore('ofertas');
        $category = $this->category($store, 'Vestidos');
        $product = $this->product($store, $category, 'Vestido de temporada', [
            'precio_venta' => 100,
            'precio_oferta' => 75,
            'destacado' => true,
        ]);
        $this->variant($product, 'S', 'Rojo', 2);
        $this->variant($product, 'M', 'Rojo', 0);

        $this->get(route('storefront.show', $store->slug))
            ->assertOk()
            ->assertSee('-25%')
            ->assertSee('$ 75.00')
            ->assertSee('$ 100.00')
            ->assertSee('2 disponibles')
            ->assertSee('title="Agotada"', false);
    }

    public function test_search_and_category_filters_are_combined_without_leaking_other_products(): void
    {
        $store = $this->publishedStore('filtros');
        $shirts = $this->category($store, 'Camisas');
        $pants = $this->category($store, 'Pantalones');
        $brand = $this->brand($store, 'Zafiro');
        $shirt = $this->product($store, $shirts, 'Camisa clásica', ['marca_id' => $brand->id]);
        $pantsProduct = $this->product($store, $pants, 'Pantalón clásico');
        $this->variant($shirt, 'M', 'Celeste', 3);
        $this->variant($pantsProduct, '32', 'Negro', 4);

        $this->get(route('storefront.show', ['slug' => $store->slug, 'q' => 'Zafiro']))
            ->assertOk()
            ->assertSee('Camisa clásica')
            ->assertDontSee('Pantalón clásico');

        $this->get(route('storefront.show', ['slug' => $store->slug, 'categoria' => $pants->slug]))
            ->assertOk()
            ->assertSee('Pantalón clásico')
            ->assertDontSee('Camisa clásica');

        $this->get(route('storefront.show', [
            'slug' => $store->slug,
            'categoria' => $shirts->slug,
            'q' => 'Pantalón',
        ]))->assertOk()
            ->assertSee('No encontramos productos')
            ->assertDontSee('Pantalón clásico');
    }

    public function test_sql_wildcards_are_treated_as_literal_search_characters(): void
    {
        $store = $this->publishedStore('comodines');
        $category = $this->category($store, 'Accesorios');
        $product = $this->product($store, $category, 'Gorra urbana');
        $this->variant($product, 'U', 'Negro', 5);

        $this->get(route('storefront.show', ['slug' => $store->slug, 'q' => '%_']))
            ->assertOk()
            ->assertSee('No encontramos productos')
            ->assertDontSee('Gorra urbana');
    }

    public function test_invalid_public_filters_are_rejected(): void
    {
        $store = $this->publishedStore('validacion');

        $this->from(route('storefront.show', $store->slug))
            ->get(route('storefront.show', ['slug' => $store->slug, 'categoria' => '../privada']))
            ->assertRedirect(route('storefront.show', $store->slug))
            ->assertSessionHasErrors('categoria');

        $this->from(route('storefront.show', $store->slug))
            ->get(route('storefront.show', ['slug' => $store->slug, 'q' => str_repeat('x', 101)]))
            ->assertSessionHasErrors('q');
    }

    private function publishedStore(string $name): Tienda
    {
        return Tienda::create([
            'nombre' => 'Tienda '.$name,
            'slug' => 'tienda-'.$name.'-'.Str::lower(Str::random(5)),
            'estado' => 'activa',
            'publicada' => true,
            'simbolo_moneda' => '$',
            'color_primario' => '#d91468',
        ]);
    }

    private function category(Tienda $store, string $name, bool $active = true): Categoria
    {
        return Categoria::create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => Str::lower($name),
            'slug' => Str::slug($name).'-'.$store->id.'-'.Str::lower(Str::random(4)),
            'activo' => $active,
        ]);
    }

    private function brand(Tienda $store, string $name): Marca
    {
        return Marca::create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => Str::lower($name),
            'activo' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function product(Tienda $store, Categoria $category, string $name, array $attributes = []): Producto
    {
        return Producto::create(array_merge([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'PUB-'.Str::upper(Str::random(10)),
            'nombre' => $name,
            'precio_compra' => 40,
            'precio_venta' => 80,
            'activo' => true,
            'destacado' => false,
        ], $attributes));
    }

    private function variant(Producto $product, string $size, string $color, int $stock): ProductoVariante
    {
        return ProductoVariante::create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => $size,
            'color' => $color,
            'stock' => $stock,
            'stock_minimo' => 3,
        ]);
    }
}
