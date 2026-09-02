<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\InventoryReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryReportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_validated_before_building_the_report(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)->get(route('reportes.inventario', [
            'buscar' => str_repeat('x', 101),
            'categoria_id' => PHP_INT_MAX,
            'estado' => 'inventado',
            'orden' => 'aleatorio',
            'por_pagina' => 500,
            'page' => 0,
        ]))->assertSessionHasErrors(['buscar', 'categoria_id', 'estado', 'orden', 'por_pagina', 'page']);
    }

    public function test_large_report_is_paginated_streamed_and_uses_a_bounded_query_plan(): void
    {
        [$user, $store, $category] = $this->context();
        foreach (range(1, 125) as $number) {
            $name = $number === 125 ? '=Producto fórmula' : 'Producto '.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $product = $this->product($store, $category, $name, $number);
            $this->variant($product, $number === 1 ? 1 : 10, 2);
        }
        $this->actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('reportes.inventario', ['por_pagina' => 50]))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $products = $response->viewData('productos');
        $summary = $response->viewData('summary');
        $this->assertSame(125, $products->total());
        $this->assertCount(50, $products->items());
        $this->assertSame(125, $summary['productos']);
        $this->assertSame(124, $summary['productos_disponibles']);
        $this->assertSame(1, $summary['productos_bajo']);
        $this->assertSame(1241, $summary['unidades']);
        $response->assertSee('Inventario General')
            ->assertSee('Total de productos')
            ->assertSee('Disponibles')
            ->assertSee('Exportar CSV')
            ->assertSee('inventory-table', false)
            ->assertSee('inventory-report.css', false);
        $this->assertLessThanOrEqual(8, $queryCount, "El reporte ejecutó {$queryCount} consultas.");
        $this->assertStringContainsString('por_pagina=50', $products->nextPageUrl());

        $query = app(InventoryReportQuery::class)->build([]);
        $plan = collect(DB::select('EXPLAIN QUERY PLAN '.$query->toSql(), $query->getBindings()))
            ->pluck('detail')->implode(' ');
        $this->assertStringContainsString('productos_busqueda_pos', $plan);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $export = $this->get(route('reportes.inventario.export'))->assertOk();
        $csv = $export->streamedContent();
        $exportQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertStringContainsString('Producto 001', $csv);
        $this->assertStringContainsString("'=Producto fórmula", $csv);
        $this->assertLessThanOrEqual(8, $exportQueries, "La exportación ejecutó {$exportQueries} consultas.");
        $this->assertTrue(Schema::hasIndex('productos', 'productos_busqueda_pos'));
    }

    /** @return array{User, Tienda, Categoria} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $user = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => 'Administrador'])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@inventory-report.test',
            'password' => 'password',
            'activo' => true,
        ]);
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'nombre_normalizado' => 'categoria '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);

        return [$user, $store, $category];
    }

    private function product(Tienda $store, Categoria $category, string $name, int $number): Producto
    {
        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'RPT-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'nombre' => $name,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => true,
        ]);
    }

    private function variant(Producto $product, int $stock, int $minimum): void
    {
        ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => $stock,
            'stock_minimo' => $minimum,
        ]);
    }
}
