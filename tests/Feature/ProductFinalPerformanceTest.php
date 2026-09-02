<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductFinalPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_pagination_is_stable_preserves_filters_and_remains_store_scoped(): void
    {
        [$administrator, $store] = $this->context();
        [, $foreignStore] = $this->context();
        $category = $this->category($store, 'Local');
        $foreignCategory = $this->category($foreignStore, 'Externa');

        foreach (range(1, 16) as $number) {
            $this->product($store, $category, $number, 'Producto filtrado', 'damas', 'formal');
            $this->product($foreignStore, $foreignCategory, $number + 100, 'Producto filtrado', 'damas', 'formal');
        }

        $filters = [
            'buscar' => 'Producto filtrado',
            'categoria_id' => $category->id,
            'genero' => 'damas',
            'tipo' => 'formal',
        ];
        $firstResponse = $this->actingAs($administrator)->get(route('productos.index', $filters))->assertOk();

        /** @var LengthAwarePaginator $firstPage */
        $firstPage = $firstResponse->viewData('productos');
        $this->assertSame(16, $firstPage->total());
        $this->assertCount(15, $firstPage->items());
        $this->assertSame(16, (int) $firstPage->first()->codigo);

        parse_str((string) parse_url((string) $firstPage->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
        $this->assertSame('2', (string) $nextQuery['page']);
        foreach ($filters as $name => $value) {
            $this->assertSame((string) $value, (string) $nextQuery[$name]);
        }

        $secondResponse = $this->get(route('productos.index', $filters + ['page' => 2]))->assertOk();
        /** @var LengthAwarePaginator $secondPage */
        $secondPage = $secondResponse->viewData('productos');
        $this->assertCount(1, $secondPage->items());
        $this->assertSame(1, (int) $secondPage->first()->codigo);
        $this->assertTrue(collect($firstPage->items())->pluck('id')->intersect(
            collect($secondPage->items())->pluck('id')
        )->isEmpty());
    }

    public function test_admin_list_includes_inactive_products_but_pos_excludes_them(): void
    {
        [$administrator, $store] = $this->context();
        $category = $this->category($store, 'Estado');
        $active = $this->product($store, $category, 1, 'Producto activo');
        $inactive = $this->product($store, $category, 2, 'Producto inactivo', active: false);
        $this->variant($active, 5, 2);
        $this->variant($inactive, 5, 2);

        $this->actingAs($administrator)->get(route('productos.index'))
            ->assertOk()
            ->assertSee('Producto activo')
            ->assertSee('Producto inactivo');

        $this->getJson(route('ventas.catalogo'))
            ->assertOk()
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $inactive->id]);
    }

    public function test_product_detail_query_count_stays_bounded_with_many_variants(): void
    {
        [$administrator, $store] = $this->context();
        $category = $this->category($store, 'Detalle');
        $product = $this->product($store, $category, 1, 'Producto detallado');

        foreach (range(1, 50) as $number) {
            $this->variant($product, $number, 5);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($administrator)->get(route('productos.show', $product))
            ->assertOk()
            ->assertSee('Talla 50');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, $queryCount, "El detalle ejecutó {$queryCount} consultas; puede existir un N+1.");
    }

    private function context(): array
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
            'email' => $suffix.'@product-final.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
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
        int $number,
        string $name,
        string $gender = 'unisex',
        string $type = 'casual',
        bool $active = true
    ): Producto {
        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => (string) $number,
            'nombre' => $name.' '.$number,
            'genero' => $gender,
            'tipo' => $type,
            'precio_compra' => 10,
            'precio_venta' => 20,
            'activo' => $active,
            'destacado' => false,
        ]);
    }

    private function variant(Producto $product, int $number, int $minimum): ProductoVariante
    {
        return ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => 'Talla '.$number,
            'color' => 'Color '.$number,
            'codigo_barra' => 'BAR-'.$product->id.'-'.$number,
            'stock' => $number,
            'stock_minimo' => $minimum,
        ]);
    }
}
