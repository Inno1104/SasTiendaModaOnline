<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProveedorSearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_validated_and_normalized_before_querying(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->get(route('proveedores.index', [
            'buscar' => str_repeat('a', 101),
            'estado' => 'eliminado',
            'orden' => 'aleatorio',
            'page' => 0,
        ]))->assertSessionHasErrors(['buscar', 'estado', 'orden', 'page']);
    }

    public function test_search_covers_name_tax_id_contact_phone_and_email(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store, 'Textiles Alpaca', true, [
            'ruc' => '20512345678',
            'contacto' => 'María Calderón',
            'telefono' => '0999111222',
            'email' => 'ventas@alpaca.test',
        ]);
        $this->supplier($store, 'Proveedor diferente', true);

        foreach (['alpaca', '20-512.345', 'maría calderón', '0999-111', 'VENTAS@ALPACA.TEST'] as $term) {
            $suppliers = $this->actingAs($admin)
                ->get(route('proveedores.index', ['buscar' => $term]))
                ->assertOk()
                ->viewData('proveedores');

            $this->assertSame(1, $suppliers->total(), "La búsqueda '{$term}' no fue precisa.");
            $this->assertSame($supplier->id, $suppliers->first()->id);
        }
    }

    public function test_search_treats_sql_wildcards_as_literal_characters(): void
    {
        [$admin, $store] = $this->context();
        $literal = $this->supplier($store, 'Proveedor 100%_real', true);
        $this->supplier($store, 'Proveedor 100Xreal', true);

        $suppliers = $this->actingAs($admin)
            ->get(route('proveedores.index', ['buscar' => '%_']))
            ->assertOk()
            ->viewData('proveedores');

        $this->assertSame(1, $suppliers->total());
        $this->assertSame($literal->id, $suppliers->first()->id);
    }

    public function test_status_filter_is_combined_with_search_and_store_scope(): void
    {
        [$admin, $store] = $this->context();
        [, $foreignStore] = $this->context();
        $match = $this->supplier($store, 'Textil activo', true);
        $this->supplier($store, 'Textil inactivo', false);
        $this->supplier($foreignStore, 'Textil extranjero', true);

        $suppliers = $this->actingAs($admin)->get(route('proveedores.index', [
            'buscar' => 'textil',
            'estado' => 'activo',
        ]))->assertOk()->viewData('proveedores');

        $this->assertSame(1, $suppliers->total());
        $this->assertSame($match->id, $suppliers->first()->id);
    }

    public function test_pagination_has_stable_order_and_preserves_filters(): void
    {
        [$admin, $store] = $this->context();
        foreach (range(1, 16) as $number) {
            $this->supplier($store, sprintf('Proveedor %02d', $number), true);
        }

        $filters = ['estado' => 'activo', 'orden' => 'nombre_desc'];
        /** @var LengthAwarePaginator $firstPage */
        $firstPage = $this->actingAs($admin)
            ->get(route('proveedores.index', $filters))
            ->assertOk()
            ->viewData('proveedores');

        $this->assertSame(16, $firstPage->total());
        $this->assertCount(15, $firstPage->items());
        $this->assertSame('Proveedor 16', $firstPage->first()->nombre);

        parse_str((string) parse_url((string) $firstPage->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
        $this->assertSame('activo', $nextQuery['estado']);
        $this->assertSame('nombre_desc', $nextQuery['orden']);
        $this->assertSame('2', $nextQuery['page']);

        $secondPage = $this->get(route('proveedores.index', $filters + ['page' => 2]))
            ->assertOk()
            ->viewData('proveedores');
        $this->assertCount(1, $secondPage->items());
        $this->assertSame('Proveedor 01', $secondPage->first()->nombre);
    }

    public function test_dependency_counts_have_a_bounded_query_budget(): void
    {
        [$admin, $store] = $this->context();
        $categoryId = DB::table('categorias')->insertGetId([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría rendimiento',
            'nombre_normalizado' => 'categoria rendimiento',
            'slug' => 'categoria-rendimiento',
            'genero' => 'unisex',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 40) as $number) {
            $supplier = $this->supplier($store, sprintf('Rendimiento %02d', $number), true);
            DB::table('productos')->insert([
                'tienda_id' => $store->id,
                'categoria_id' => $categoryId,
                'proveedor_id' => $supplier->id,
                'codigo' => 'SUP-PERF-'.$number,
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
            DB::table('compras')->insert([
                'tienda_id' => $store->id,
                'numero_compra' => 'SUP-PERF-'.$number,
                'proveedor_id' => $supplier->id,
                'user_id' => $admin->id,
                'fecha' => today(),
                'total' => 1,
                'estado' => 'recibido',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $admin->load('rol', 'tienda');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($admin)->get(route('proveedores.index'));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $suppliers = $response->viewData('proveedores');
        $this->assertSame(40, $suppliers->total());
        $this->assertCount(15, $suppliers->items());
        $this->assertSame(1, $suppliers->first()->productos_count);
        $this->assertSame(1, $suppliers->first()->compras_count);
        $this->assertLessThanOrEqual(8, $queryCount, "El listado ejecutó {$queryCount} consultas; puede existir un N+1.");
    }

    public function test_schema_contains_indexes_for_filters_order_and_dependency_counts(): void
    {
        $supplierIndexes = collect(Schema::getIndexes('proveedores'))->pluck('name');
        $productIndexes = collect(Schema::getIndexes('productos'))->pluck('name');
        $purchaseIndexes = collect(Schema::getIndexes('compras'))->pluck('name');

        $this->assertContains('proveedores_tienda_estado_nombre', $supplierIndexes);
        $this->assertContains('proveedores_tienda_fecha', $supplierIndexes);
        $this->assertContains('productos_tienda_proveedor', $productIndexes);
        $this->assertContains('compras_tienda_proveedor', $purchaseIndexes);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $admin = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@supplier-search.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$admin, $store];
    }

    /** @param array<string, mixed> $attributes */
    private function supplier(Tienda $store, string $name, bool $active, array $attributes = []): Proveedor
    {
        return Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'ruc' => (string) random_int(10000000000, 99999999999),
            'contacto' => 'Contacto general',
            'telefono' => '0988000000',
            'email' => Str::lower(Str::random(8)).'@example.test',
            'activo' => $active,
            ...$attributes,
        ]);
    }
}
