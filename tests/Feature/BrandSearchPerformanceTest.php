<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrandSearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_validated_and_normalized_before_querying(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)->get(route('marcas.index', [
            'buscar' => str_repeat('a', 101),
            'estado' => 'eliminada',
            'orden' => 'aleatorio',
            'page' => 0,
        ]))->assertSessionHasErrors(['buscar', 'estado', 'orden', 'page']);
    }

    public function test_search_and_status_are_combined_and_store_scoped(): void
    {
        [$user, $store] = $this->context();
        [, $foreignStore] = $this->context();
        $match = $this->brand($store, 'Ropa   Urbana', true);
        $this->brand($store, 'Ropa Casual', true);
        $this->brand($store, 'Ropa urbana retirada', false);
        $this->brand($foreignStore, 'Ropa Urbana', true);

        $response = $this->actingAs($user)->get(route('marcas.index', [
            'buscar' => '  ROPA urbana ',
            'estado' => 'activo',
        ]))->assertOk();

        /** @var LengthAwarePaginator $brands */
        $brands = $response->viewData('marcas');
        $this->assertSame(1, $brands->total());
        $this->assertSame($match->id, $brands->first()->id);
    }

    public function test_search_treats_sql_wildcards_as_literal_characters(): void
    {
        [$user, $store] = $this->context();
        $literal = $this->brand($store, 'Marca 100%_real', true);
        $this->brand($store, 'Marca 100Xreal', true);

        $response = $this->actingAs($user)->get(route('marcas.index', ['buscar' => '%_']))
            ->assertOk();

        $brands = $response->viewData('marcas');
        $this->assertSame(1, $brands->total());
        $this->assertSame($literal->id, $brands->first()->id);
    }

    public function test_pagination_is_stable_and_preserves_active_filters(): void
    {
        [$user, $store] = $this->context();
        foreach (range(1, 16) as $number) {
            $this->brand($store, sprintf('Marca %02d', $number), true);
        }

        $filters = ['estado' => 'activo', 'orden' => 'nombre_desc'];
        $response = $this->actingAs($user)->get(route('marcas.index', $filters))->assertOk();

        /** @var LengthAwarePaginator $firstPage */
        $firstPage = $response->viewData('marcas');
        $this->assertSame(16, $firstPage->total());
        $this->assertCount(15, $firstPage->items());
        $this->assertSame('Marca 16', $firstPage->first()->nombre);

        parse_str((string) parse_url((string) $firstPage->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
        foreach ($filters as $key => $value) {
            $this->assertSame($value, $nextQuery[$key]);
        }
        $this->assertSame('2', $nextQuery['page']);

        $secondPage = $this->get(route('marcas.index', $filters + ['page' => 2]))
            ->assertOk()
            ->viewData('marcas');
        $this->assertCount(1, $secondPage->items());
        $this->assertSame('Marca 01', $secondPage->first()->nombre);
    }

    public function test_product_counts_use_a_bounded_query_budget(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        foreach (range(1, 40) as $number) {
            $brand = $this->brand($store, sprintf('Rendimiento %02d', $number), true);
            DB::table('productos')->insert([
                'tienda_id' => $store->id,
                'categoria_id' => $category->id,
                'marca_id' => $brand->id,
                'codigo' => 'BRAND-PERF-'.$number,
                'nombre' => 'Producto '.$number,
                'genero' => 'unisex',
                'tipo' => 'casual',
                'precio_compra' => 1,
                'precio_venta' => 2,
                'activo' => true,
                'destacado' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user->load('rol', 'tienda');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('marcas.index'));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $brands = $response->viewData('marcas');
        $this->assertSame(40, $brands->total());
        $this->assertCount(15, $brands->items());
        $this->assertSame(1, $brands->first()->productos_count);
        $this->assertLessThanOrEqual(8, $queryCount, "El listado ejecutó {$queryCount} consultas; puede existir un N+1.");
    }

    /** @return array{User, Tienda} */
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
            'email' => $suffix.'@brand-search.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function brand(Tienda $store, string $name, bool $active): Marca
    {
        return Marca::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => Str::lower(Str::squish($name)),
            'activo' => $active,
        ]);
    }

    private function category(Tienda $store): Categoria
    {
        $suffix = Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'nombre_normalizado' => 'categoria '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);
    }
}
