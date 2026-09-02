<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategorySearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_validated_and_normalized_before_querying(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)->get(route('categorias.index', [
            'buscar' => str_repeat('a', 101),
            'estado' => 'eliminada',
            'genero' => 'otro',
            'orden' => 'aleatorio',
            'page' => 0,
        ]))->assertSessionHasErrors(['buscar', 'estado', 'genero', 'orden', 'page']);
    }

    public function test_category_page_exposes_responsive_controls_and_accessible_actions(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store, 'Categoría visual', true, 'unisex');

        $this->actingAs($user)->get(route('categorias.index'))
            ->assertOk()
            ->assertSee('css/categorias.css')
            ->assertSee('js/categorias.js')
            ->assertSee('Organización del catálogo')
            ->assertSee('data-category-delete', false)
            ->assertSee('aria-label="Editar '.$category->nombre.'"', false)
            ->assertSee('categoryDeleteDialog');
    }

    public function test_search_status_and_gender_filters_are_combined_and_store_scoped(): void
    {
        [$user, $store] = $this->context();
        [, $foreignStore] = $this->context();
        $match = $this->category($store, 'Ropa   Deportiva', true, 'damas');
        $this->category($store, 'Ropa Casual', true, 'caballeros');
        $this->category($store, 'Ropa deportiva antigua', false, 'damas');
        $this->category($foreignStore, 'Ropa Deportiva', true, 'damas');

        $response = $this->actingAs($user)->get(route('categorias.index', [
            'buscar' => '  ROPA deportiva ',
            'estado' => 'activo',
            'genero' => 'damas',
        ]))->assertOk();

        /** @var LengthAwarePaginator $categories */
        $categories = $response->viewData('categorias');
        $this->assertSame(1, $categories->total());
        $this->assertSame($match->id, $categories->first()->id);
    }

    public function test_pagination_is_stable_and_preserves_active_filters(): void
    {
        [$user, $store] = $this->context();
        foreach (range(1, 16) as $number) {
            $this->category($store, sprintf('Categoría %02d', $number), true, 'unisex');
        }

        $filters = ['estado' => 'activo', 'genero' => 'unisex', 'orden' => 'nombre_desc'];
        $response = $this->actingAs($user)->get(route('categorias.index', $filters))->assertOk();

        /** @var LengthAwarePaginator $firstPage */
        $firstPage = $response->viewData('categorias');
        $this->assertSame(16, $firstPage->total());
        $this->assertCount(15, $firstPage->items());
        $this->assertSame('Categoría 16', $firstPage->first()->nombre);

        parse_str((string) parse_url((string) $firstPage->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
        foreach ($filters as $key => $value) {
            $this->assertSame($value, $nextQuery[$key]);
        }
        $this->assertSame('2', $nextQuery['page']);

        $secondPage = $this->get(route('categorias.index', $filters + ['page' => 2]))
            ->assertOk()
            ->viewData('categorias');
        $this->assertCount(1, $secondPage->items());
        $this->assertSame('Categoría 01', $secondPage->first()->nombre);
    }

    public function test_dependency_counts_use_a_bounded_query_budget(): void
    {
        [$user, $store] = $this->context();
        foreach (range(1, 40) as $number) {
            $category = $this->category($store, sprintf('Rendimiento %02d', $number), true, 'unisex');
            DB::table('productos')->insert([
                'tienda_id' => $store->id,
                'categoria_id' => $category->id,
                'codigo' => 'CAT-PERF-'.$number,
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
            DB::table('subcategorias')->insert([
                'tienda_id' => $store->id,
                'categoria_id' => $category->id,
                'nombre' => 'Subcategoría '.$number,
                'slug' => 'subcategoria-perf-'.$number,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user->load('rol', 'tienda');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('categorias.index'));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $categories = $response->viewData('categorias');
        $this->assertSame(40, $categories->total());
        $this->assertCount(15, $categories->items());
        $this->assertSame(1, $categories->first()->productos_count);
        $this->assertSame(1, $categories->first()->subcategorias_count);
        $this->assertLessThanOrEqual(8, $queryCount, "El listado ejecutó {$queryCount} consultas; puede existir un N+1.");
    }

    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@category-search.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function category(Tienda $store, string $name, bool $active, string $gender): Categoria
    {
        $suffix = Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => Str::lower(Str::squish($name)),
            'slug' => Str::slug($name).'-'.$suffix,
            'genero' => $gender,
            'activo' => $active,
        ]);
    }
}
