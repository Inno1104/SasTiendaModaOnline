<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use App\Services\InventoryMovementService;
use App\Services\StockManager;
use App\Services\StockStatistics;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class StockSecurityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_are_validated_before_querying(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)->get(route('stock.index', [
            'buscar' => str_repeat('a', 101),
            'categoria_id' => PHP_INT_MAX,
            'filtro' => 'desconocido',
            'producto_estado' => 'archivado',
            'orden' => 'aleatorio',
            'por_pagina' => 500,
            'page' => 0,
        ]))->assertSessionHasErrors(['buscar', 'categoria_id', 'filtro', 'producto_estado', 'orden', 'por_pagina', 'page']);
    }

    public function test_search_treats_sql_wildcards_as_literals_and_searches_barcode(): void
    {
        [$user, $store] = $this->context();
        $product = $this->product($store, 'Producto literal');
        $literal = $this->variant($product, 5, 2, 'M%_', 'Negro', 'BAR%_01');
        $this->variant($product, 4, 2, 'MXX', 'Azul', 'BARXX01');

        $response = $this->actingAs($user)->get(route('stock.index', ['buscar' => '%_']))
            ->assertOk()
            ->assertSee('stock-spark', false);

        $variants = $response->viewData('variantes');
        $this->assertSame(1, $variants->total());
        $this->assertSame($literal->id, $variants->first()->id);
    }

    public function test_search_covers_all_identifiers_and_combines_category_filter(): void
    {
        [$user, $store] = $this->context();
        $targetProduct = $this->product($store, 'Abrigo Busqueda Unica');
        $target = $this->variant($targetProduct, 7, 2, 'TALLA-UNICA', 'COLOR-UNICO', 'BARRA-UNICA');
        $otherProduct = $this->product($store, 'Producto secundario');
        $this->variant($otherProduct, 7, 2, 'OTRA-TALLA', 'OTRO-COLOR', 'OTRA-BARRA');

        foreach (['Abrigo Busqueda Unica', $targetProduct->codigo, 'BARRA-UNICA', 'TALLA-UNICA', 'COLOR-UNICO'] as $search) {
            $variants = $this->actingAs($user)->get(route('stock.index', [
                'buscar' => $search,
                'categoria_id' => $targetProduct->categoria_id,
            ]))->assertOk()->viewData('variantes');

            $this->assertSame([$target->id], $variants->pluck('id')->all(), "Falló la búsqueda por {$search}.");
        }
    }

    public function test_pagination_is_stable_preserves_filters_and_has_a_bounded_query_count(): void
    {
        [$user, $store] = $this->context();
        $product = $this->product($store, 'Producto paginado');

        foreach (range(1, 25) as $number) {
            $this->variant($product, 5, 2, 'T'.str_pad((string) $number, 2, '0', STR_PAD_LEFT));
        }

        $this->actingAs($user);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $firstResponse = $this->get(route('stock.index', [
            'categoria_id' => $product->categoria_id,
            'filtro' => 'disponible',
            'orden' => 'stock_asc',
            'por_pagina' => 20,
        ]))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $firstPage = $firstResponse->viewData('variantes');
        $secondResponse = $this->get($firstPage->nextPageUrl())->assertOk();
        $secondPage = $secondResponse->viewData('variantes');

        $this->assertCount(20, $firstPage->items());
        $this->assertCount(5, $secondPage->items());
        $this->assertEmpty(array_intersect($firstPage->pluck('id')->all(), $secondPage->pluck('id')->all()));
        $this->assertStringContainsString('categoria_id='.$product->categoria_id, $firstPage->nextPageUrl());
        $this->assertStringContainsString('por_pagina=20', $firstPage->nextPageUrl());
        $this->assertLessThanOrEqual(9, $queryCount, "El listado ejecutó {$queryCount} consultas.");
    }

    public function test_state_filters_are_exclusive_and_stock_order_is_stable(): void
    {
        [$user, $store] = $this->context();
        $product = $this->product($store);
        $exhausted = $this->variant($product, 0, 2, 'S');
        $low = $this->variant($product, 2, 2, 'M');
        $available = $this->variant($product, 8, 2, 'L');

        foreach ([
            'agotado' => $exhausted->id,
            'bajo' => $low->id,
            'disponible' => $available->id,
        ] as $filter => $expectedId) {
            $variants = $this->actingAs($user)
                ->get(route('stock.index', ['filtro' => $filter]))
                ->assertOk()
                ->viewData('variantes');
            $this->assertSame(1, $variants->total());
            $this->assertSame($expectedId, $variants->first()->id);
        }

        $ordered = $this->get(route('stock.index', ['orden' => 'stock_desc']))
            ->assertOk()
            ->viewData('variantes');
        $this->assertSame([$available->id, $low->id, $exhausted->id], $ordered->pluck('id')->all());
    }

    public function test_stock_counters_are_exclusive_and_ignore_inactive_products(): void
    {
        [$user, $store] = $this->context();
        $active = $this->product($store, 'Producto activo');
        $this->variant($active, 0, 3, 'S');
        $this->variant($active, 3, 3, 'M');
        $this->variant($active, 4, 3, 'L');

        $inactive = $this->product($store, 'Producto inactivo');
        $inactive->update(['activo' => false]);
        $inactiveVariant = $this->variant($inactive, 0, 3, 'U');

        $response = $this->actingAs($user)->get(route('stock.index'))->assertOk();

        $this->assertSame(3, $response->viewData('variantes')->total());
        $this->assertSame(7, (int) $response->viewData('totalUnidades'));
        $this->assertSame(1, $response->viewData('bajoStock'));
        $this->assertSame(1, $response->viewData('sinStock'));
        $this->assertSame(1, $response->viewData('variantesDisponibles'));

        $inactiveResults = $this->get(route('stock.index', ['producto_estado' => 'inactivo']))
            ->assertOk()
            ->viewData('variantes');
        $this->assertSame([$inactiveVariant->id], $inactiveResults->pluck('id')->all());

        $allResults = $this->get(route('stock.index', ['producto_estado' => 'todos']))
            ->assertOk()
            ->viewData('variantes');
        $this->assertSame(4, $allResults->total());
    }

    public function test_operational_statistics_are_consolidated_in_one_query(): void
    {
        [$user, $store] = $this->context();
        $product = $this->product($store);
        $this->variant($product, 0, 2, 'S');
        $this->variant($product, 2, 2, 'M');
        $this->variant($product, 8, 2, 'L');
        $this->actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $statistics = app(StockStatistics::class)->operational();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame([
            'total_unidades' => 10,
            'bajo_stock' => 1,
            'sin_stock' => 1,
            'disponibles' => 1,
        ], $statistics);
        $this->assertSame(1, $queryCount);
    }

    public function test_dashboard_statistics_share_exclusive_rules_and_tenant_scope(): void
    {
        [$user, $store] = $this->context();
        $active = $this->product($store, 'Producto operativo');
        $this->variant($active, 0, 2, 'S');
        $this->variant($active, 2, 2, 'M');
        $this->variant($active, 8, 2, 'L');
        $inactive = $this->product($store, 'Producto inactivo');
        $inactive->update(['activo' => false]);
        $this->variant($inactive, 0, 2, 'U');
        [, $foreignStore] = $this->context();
        $this->variant($this->product($foreignStore), 1, 2, 'XL');
        $this->actingAs($user);

        $this->assertSame([
            'productos_bajo' => 1,
            'variantes_bajo' => 1,
            'variantes_agotadas' => 1,
        ], app(StockStatistics::class)->dashboard($store->id));
    }

    public function test_variant_locks_are_always_acquired_in_id_order(): void
    {
        [, $store] = $this->context();
        $product = $this->product($store);
        $first = $this->variant($product, 5, 2, 'S');
        $second = $this->variant($product, 5, 2, 'M');

        $lockedIds = DB::transaction(fn () => app(InventoryMovementService::class)
            ->lockVariants([$second->id, $first->id, $second->id], $store->id)
            ->keys()
            ->all());

        $this->assertSame([$first->id, $second->id], $lockedIds);
    }

    public function test_no_op_adjustment_does_not_increment_version_or_write_audit(): void
    {
        [$user, $store] = $this->context();
        $variant = $this->variant($this->product($store), 10, 2);

        $this->actingAs($user)->post(route('stock.update', $variant), [
            'stock' => 10,
            'stock_minimo' => 2,
            'expected_version' => 0,
        ])->assertSessionHas('success');

        $this->assertSame(0, $variant->fresh()->lock_version);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_service_rejects_cross_store_adjustment_even_when_called_directly(): void
    {
        [$foreignUser] = $this->context();
        [, $store] = $this->context();
        $variant = $this->variant($this->product($store));

        $this->actingAs($foreignUser);
        $this->expectException(AuthorizationException::class);
        app(StockManager::class)->adjust($variant, 8, 2, 0, $foreignUser);
    }

    public function test_database_rejects_negative_stock_outside_http_layer(): void
    {
        [, $store] = $this->context();
        $variant = $this->variant($this->product($store));

        $this->expectException(QueryException::class);
        DB::table('producto_variantes')->where('id', $variant->id)->update(['stock' => -1]);
    }

    public function test_purchase_overflow_rolls_back_purchase_and_stock(): void
    {
        [$user, $store] = $this->context();
        $variant = $this->variant($this->product($store), ProductoVariante::MAX_STOCK);
        $supplier = Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Proveedor límite',
            'activo' => true,
        ]);

        $this->actingAs($user)->post(route('compras.store'), [
            'proveedor_id' => $supplier->id,
            'fecha' => today()->toDateString(),
            'items' => [['variante_id' => $variant->id, 'cantidad' => 1, 'precio' => 1]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(ProductoVariante::MAX_STOCK, $variant->fresh()->stock);
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('detalle_compras', 0);
    }

    public function test_stock_and_audit_roll_back_when_movement_cannot_be_persisted(): void
    {
        [$user, $store] = $this->context();
        $variant = $this->variant($this->product($store), 10, 2);
        Event::listen('eloquent.creating: '.StockMovimiento::class, function (): void {
            throw new RuntimeException('Fallo simulado del kardex.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post(route('stock.update', $variant), [
                'stock' => 7,
                'stock_minimo' => 2,
                'expected_version' => 0,
                'motivo' => 'Corrección de inventario',
            ]);
            $this->fail('El fallo simulado debía interrumpir el ajuste.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado del kardex.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.StockMovimiento::class);
            $this->withExceptionHandling();
        }

        $variant->refresh();
        $this->assertSame(10, $variant->stock);
        $this->assertSame(0, $variant->lock_version);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_movement_is_immutable_through_eloquent(): void
    {
        [$user, $store] = $this->context();
        $variant = $this->variant($this->product($store));
        $movement = $this->movement($variant, $user);

        $this->expectException(LogicException::class);
        $movement->update(['motivo' => 'Intento de modificación']);
    }

    public function test_database_rejects_movement_update_and_delete(): void
    {
        [$user, $store] = $this->context();
        $movement = $this->movement($this->variant($this->product($store)), $user);

        foreach (['update', 'delete'] as $operation) {
            try {
                $query = DB::table('stock_movimientos')->where('id', $movement->id);
                $operation === 'update'
                    ? $query->update(['motivo' => 'Alterado'])
                    : $query->delete();
                $this->fail("La base de datos permitió {$operation} un movimiento.");
            } catch (QueryException) {
                $this->assertDatabaseHas('stock_movimientos', [
                    'id' => $movement->id,
                    'motivo' => 'Prueba de inmutabilidad',
                ]);
            }
        }
    }

    public function test_database_rejects_directly_manipulated_kardex_rows(): void
    {
        [$user, $store] = $this->context();
        $variant = $this->variant($this->product($store), 10, 2);
        $valid = [
            'tienda_id' => $store->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'user_id' => $user->id,
            'tipo' => StockMovimiento::TIPO_ENTRADA,
            'direccion' => 'entrada',
            'stock_anterior' => 10,
            'cantidad' => 1,
            'stock_resultante' => 11,
            'created_at' => now(),
        ];
        $invalidRows = [
            'tipo desconocido' => ['tipo' => 'manipulado'],
            'dirección desconocida' => ['direccion' => 'lateral'],
            'saldo aritmético incorrecto' => ['stock_resultante' => 12],
            'signo contrario a dirección' => ['cantidad' => -1, 'stock_resultante' => 9],
            'tipo incompatible con dirección' => ['tipo' => StockMovimiento::TIPO_VENTA],
            'movimiento sin cambio' => ['cantidad' => 0, 'stock_resultante' => 10],
        ];

        foreach ($invalidRows as $case => $changes) {
            try {
                DB::table('stock_movimientos')->insert(array_replace($valid, $changes));
                $this->fail("La base de datos aceptó el caso inválido: {$case}.");
            } catch (QueryException) {
                $this->assertDatabaseCount('stock_movimientos', 0);
            }
        }
    }

    public function test_kardex_constraint_failure_rolls_back_stock_version_and_audit(): void
    {
        [$user, $store] = $this->context();
        $variant = $this->variant($this->product($store), 10, 2);
        Event::listen('eloquent.creating: '.StockMovimiento::class, function (StockMovimiento $movement): void {
            $movement->direccion = 'entrada';
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post(route('stock.update', $variant), [
                'stock' => 7,
                'stock_minimo' => 2,
                'expected_version' => 0,
                'motivo' => 'Intento inconsistente',
            ]);
            $this->fail('La restricción del Kardex debía rechazar el movimiento manipulado.');
        } catch (QueryException) {
            $variant->refresh();
            $this->assertSame(10, $variant->stock);
            $this->assertSame(0, $variant->lock_version);
            $this->assertDatabaseCount('stock_movimientos', 0);
            $this->assertDatabaseCount('auditorias', 0);
        } finally {
            Event::forget('eloquent.creating: '.StockMovimiento::class);
            $this->withExceptionHandling();
        }
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $user = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => 'Administrador'])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@stock-integrity.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function product(Tienda $store, string $name = 'Producto de prueba'): Producto
    {
        $suffix = Str::lower(Str::random(8));
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'nombre_normalizado' => 'categoria '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);

        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'PROD-'.Str::upper(Str::random(8)),
            'nombre' => $name,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 1,
            'precio_venta' => 2,
            'activo' => true,
        ]);
    }

    private function variant(
        Producto $product,
        int $stock = 10,
        int $minimum = 2,
        string $size = 'M',
        string $color = 'Negro',
        ?string $barcode = null
    ): ProductoVariante {
        return ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => $size,
            'color' => $color,
            'codigo_barra' => $barcode,
            'stock' => $stock,
            'stock_minimo' => $minimum,
        ]);
    }

    private function movement(ProductoVariante $variant, User $user): StockMovimiento
    {
        return StockMovimiento::create([
            'tienda_id' => $variant->tienda_id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'user_id' => $user->id,
            'tipo' => StockMovimiento::TIPO_AJUSTE,
            'direccion' => 'salida',
            'stock_anterior' => 11,
            'cantidad' => -1,
            'stock_resultante' => 10,
            'motivo' => 'Prueba de inmutabilidad',
        ]);
    }
}
