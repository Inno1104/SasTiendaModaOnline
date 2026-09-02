<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductSearchExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_catalog_searches_trimmed_barcode_and_combines_valid_filters(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $shirts = $this->category($store, 'Camisas');
        $otherCategory = $this->category($store, 'Accesorios');
        $expected = $this->product($store, $shirts, 'SKU-TARGET', 'Producto objetivo', 'damas', 'otro');
        $this->variant($expected, 'BAR-TARGET', 4, 5);
        $this->product($store, $otherCategory, 'SKU-OTHER', 'Producto secundario', 'unisex', 'casual');

        $response = $this->actingAs($administrator)->get(route('productos.index', [
            'buscar' => '  bar-target  ',
            'categoria_id' => $shirts->id,
            'genero' => 'damas',
            'tipo' => 'otro',
        ]));

        $response->assertOk()
            ->assertSee('Producto objetivo')
            ->assertDontSee('Producto secundario')
            ->assertSee('4 uds.')
            ->assertSee('badge-warning', false)
            ->assertSee('value="otro" selected', false);
    }

    public function test_admin_catalog_rejects_invalid_and_cross_store_filters(): void
    {
        [$administrator] = $this->context('Administrador');
        [, $foreignStore] = $this->context('Administrador');
        $foreignCategory = $this->category($foreignStore, 'Privada');

        $this->actingAs($administrator)->get(route('productos.index', [
            'buscar' => str_repeat('a', 101),
            'categoria_id' => $foreignCategory->id,
            'genero' => 'invalido',
            'tipo' => 'invalido',
            'page' => 0,
        ]))->assertSessionHasErrors(['buscar', 'categoria_id', 'genero', 'tipo', 'page']);
    }

    public function test_catalog_actions_match_the_authenticated_users_permissions(): void
    {
        [$seller, $store] = $this->context('Vendedor');
        $administrator = $this->user($store, 'Administrador');
        $category = $this->category($store, 'General');
        $product = $this->product($store, $category, 'SKU-PERM', 'Producto permisos');

        $this->actingAs($seller)->get(route('productos.index'))
            ->assertOk()
            ->assertDontSee(route('productos.create'), false)
            ->assertDontSee(route('productos.edit', $product), false)
            ->assertDontSee('<form method="POST" action="'.route('productos.destroy', $product).'"', false)
            ->assertSee(route('productos.show', $product), false);

        $this->get(route('productos.show', $product))
            ->assertOk()
            ->assertDontSee(route('productos.edit', $product), false);

        $this->actingAs($administrator)->get(route('productos.index'))
            ->assertOk()
            ->assertSee(route('productos.create'), false)
            ->assertSee(route('productos.edit', $product), false)
            ->assertSee('<form method="POST" action="'.route('productos.destroy', $product).'"', false);
    }

    public function test_product_listing_has_a_bounded_query_count_with_a_full_page(): void
    {
        [$administrator, $store] = $this->context('Administrador');
        $category = $this->category($store, 'Rendimiento');

        foreach (range(1, 15) as $number) {
            $product = $this->product($store, $category, 'SKU-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'Producto '.$number);
            $this->variant($product, 'BAR-'.$number, $number, 3);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($administrator)->get(route('productos.index'))->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, $queryCount, "El listado ejecutó {$queryCount} consultas; puede existir un N+1.");
    }

    public function test_pos_catalog_validates_tenant_filters_and_keeps_its_json_contract(): void
    {
        [$seller, $store] = $this->context('Vendedor');
        [, $foreignStore] = $this->context('Administrador');
        $category = $this->category($store, 'Local');
        $foreignCategory = $this->category($foreignStore, 'Externa');
        $foreignBrand = Marca::withoutGlobalScopes()->create([
            'tienda_id' => $foreignStore->id,
            'nombre' => 'Marca externa',
            'activo' => true,
        ]);
        $product = $this->product($store, $category, 'SKU-POS', 'Producto POS');
        $variant = $this->variant($product, 'BAR-POS-2026', 6, 2);

        $this->actingAs($seller)->getJson(route('ventas.catalogo', [
            'categoria_id' => $foreignCategory->id,
            'marca_id' => $foreignBrand->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['categoria_id', 'marca_id']);

        $this->getJson(route('ventas.catalogo', ['q' => '  bar-pos-2026  ']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.variantes.0.id', $variant->id)
            ->assertJsonPath('data.0.variantes.0.stock', 6);
    }

    private function context(string $role): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);

        return [$this->user($store, $role), $store];
    }

    private function user(Tienda $store, string $role): User
    {
        $roleModel = Rol::firstOrCreate(['nombre' => $role]);
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'rol_id' => $roleModel->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@product-search.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function category(Tienda $store, string $name): Categoria
    {
        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'genero' => 'unisex',
            'activo' => true,
        ]);
    }

    private function product(
        Tienda $store,
        Categoria $category,
        string $code,
        string $name,
        string $gender = 'unisex',
        string $type = 'casual'
    ): Producto {
        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => $code,
            'nombre' => $name,
            'genero' => $gender,
            'tipo' => $type,
            'precio_compra' => 10,
            'precio_venta' => 20,
            'activo' => true,
            'destacado' => false,
        ]);
    }

    private function variant(Producto $product, string $barcode, int $stock, int $minimum): ProductoVariante
    {
        return ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'codigo_barra' => $barcode,
            'stock' => $stock,
            'stock_minimo' => $minimum,
        ]);
    }
}
