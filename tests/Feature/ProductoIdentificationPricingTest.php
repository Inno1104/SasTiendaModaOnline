<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\ProductCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductoIdentificationPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_purchase_price_is_stored_as_zero(): void
    {
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category, ['precio_compra' => '']))
            ->assertSessionHas('success');

        $product = Producto::firstOrFail();
        $this->assertSame('0.00', $product->precio_compra);
    }

    public function test_prices_accept_two_decimals_and_reject_excess_precision(): void
    {
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category, [
                'precio_compra' => '10.999',
                'precio_venta' => '20.999',
                'precio_oferta' => '15.999',
            ]))
            ->assertSessionHasErrors(['precio_compra', 'precio_venta', 'precio_oferta']);

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_offer_must_be_positive_and_lower_than_regular_price(): void
    {
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);

        foreach (['0', '20', '25'] as $invalidOffer) {
            $this->actingAs($administrator)
                ->post(route('productos.store'), $this->payload($category, [
                    'precio_venta' => '20.00',
                    'precio_oferta' => $invalidOffer,
                ]))
                ->assertSessionHasErrors('precio_oferta');
        }

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_valid_offer_is_the_price_exposed_to_the_pos(): void
    {
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category, [
                'precio_venta' => '20.00',
                'precio_oferta' => '14.95',
                'variantes' => [
                    ['talla' => 'M', 'color' => 'Azul', 'codigo_barra' => 'pos-001', 'stock' => 2, 'stock_minimo' => 1],
                ],
            ]))
            ->assertSessionHas('success');

        $product = Producto::firstOrFail();
        $response = $this->getJson(route('ventas.catalogo', ['q' => 'POS-001']))->assertOk();

        $response->assertJsonPath('data.0.id', $product->id);
        $response->assertJsonPath('data.0.precio', 14.95);
        $response->assertJsonPath('data.0.variantes.0.codigo_barra', 'POS-001');
    }

    public function test_barcode_is_normalized_and_cannot_repeat_inside_the_same_store(): void
    {
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category, [
                'variantes' => [
                    ['talla' => 'S', 'color' => 'Negro', 'codigo_barra' => ' abc-123 ', 'stock' => 2, 'stock_minimo' => 1],
                ],
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('producto_variantes', ['tienda_id' => $store->id, 'codigo_barra' => 'ABC-123']);

        $this->post(route('productos.store'), $this->payload($category, [
            'nombre' => 'Segundo producto',
            'variantes' => [
                ['talla' => 'M', 'color' => 'Negro', 'codigo_barra' => 'abc-123', 'stock' => 1, 'stock_minimo' => 1],
            ],
        ]))->assertSessionHasErrors('variantes.0.codigo_barra');

        $this->assertDatabaseCount('productos', 1);
    }

    public function test_same_barcode_is_allowed_in_different_stores_but_database_blocks_local_duplicates(): void
    {
        [, $firstStore] = $this->userWithStore();
        [, $secondStore] = $this->userWithStore();
        $firstCategory = $this->category($firstStore);
        $secondCategory = $this->category($secondStore);
        $firstProduct = $this->product($firstStore, $firstCategory, 'PROD-FIRST');
        $secondProduct = $this->product($secondStore, $secondCategory, 'PROD-SECOND');

        ProductoVariante::withoutGlobalScopes()->create($this->variantData($firstStore, $firstProduct, 'SHARED-001'));
        ProductoVariante::withoutGlobalScopes()->create($this->variantData($secondStore, $secondProduct, 'SHARED-001'));

        $this->expectException(QueryException::class);
        ProductoVariante::withoutGlobalScopes()->create($this->variantData($firstStore, $firstProduct, 'SHARED-001'));
    }

    public function test_product_code_generator_skips_collisions_and_preserves_format(): void
    {
        [, $store] = $this->userWithStore();
        $category = $this->category($store);
        $this->product($store, $category, 'PROD-ABC123');

        Str::createRandomStringsUsingSequence(['abc123', 'xyz789']);

        try {
            $code = app(ProductCodeGenerator::class)->generate();
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame('PROD-XYZ789', $code);
        $this->assertMatchesRegularExpression('/^PROD-[A-Z0-9]{6}$/', $code);
    }

    private function payload(Categoria $category, array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Producto comercial',
            'categoria_id' => $category->id,
            'marca_id' => '',
            'proveedor_id' => '',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'descripcion' => '',
            'precio_compra' => '10.00',
            'precio_venta' => '20.00',
            'precio_oferta' => '',
            'activo' => 1,
            'destacado' => 0,
            'variantes' => [],
        ], $overrides);
    }

    private function userWithStore(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@product-pricing.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function category(Tienda $store): Categoria
    {
        $suffix = Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);
    }

    private function product(Tienda $store, Categoria $category, string $code): Producto
    {
        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => $code,
            'nombre' => 'Producto '.$code,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'activo' => true,
            'destacado' => false,
        ]);
    }

    private function variantData(Tienda $store, Producto $product, string $barcode): array
    {
        return [
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Azul',
            'codigo_barra' => $barcode,
            'stock' => 1,
            'stock_minimo' => 1,
        ];
    }
}
