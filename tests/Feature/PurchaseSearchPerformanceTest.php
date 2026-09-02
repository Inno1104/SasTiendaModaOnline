<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseSearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_validated_before_querying(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->get(route('compras.index', [
            'estado' => 'inventado', 'proveedor_id' => 999999, 'desde' => '2026-08-20',
            'hasta' => '2026-08-10', 'orden' => 'sql', 'page' => 0,
        ]))->assertSessionHasErrors(['estado', 'proveedor_id', 'hasta', 'orden', 'page']);

        $this->getJson(route('compras.catalogo', ['q' => str_repeat('x', 101), 'page' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'page']);
    }

    public function test_list_searches_number_supplier_and_ruc_with_literal_wildcards(): void
    {
        [$admin, $store, $supplier] = $this->context();
        $supplier->update(['nombre' => 'Proveedor 100% Real', 'ruc' => '1790012345001']);
        $purchase = $this->purchase($store, $supplier, $admin, 'COM-2026-0042', 'recibido', '2026-08-20', 25);
        $other = Proveedor::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'nombre' => 'Otro', 'activo' => true]);
        $this->purchase($store, $other, $admin, 'COM-2026-9999', 'recibido', '2026-08-21', 10);

        foreach (['0042', '100%', '1790012345001'] as $search) {
            $this->actingAs($admin)->get(route('compras.index', ['buscar' => $search]))
                ->assertOk()->assertSee($purchase->numero_compra)->assertDontSee('COM-2026-9999');
        }
    }

    public function test_combined_filters_are_store_scoped_and_order_is_stable(): void
    {
        [$admin, $store, $supplier] = $this->context();
        $matching = $this->purchase($store, $supplier, $admin, 'COM-A', 'anulado', '2026-08-20', 80);
        $this->purchase($store, $supplier, $admin, 'COM-B', 'recibido', '2026-08-20', 90);
        [, $foreignStore, $foreignSupplier, , $foreignAdmin] = $this->context();
        $this->purchase($foreignStore, $foreignSupplier, $foreignAdmin, 'COM-FOREIGN', 'anulado', '2026-08-20', 100);

        $response = $this->actingAs($admin)->get(route('compras.index', [
            'proveedor_id' => $supplier->id, 'estado' => 'anulado',
            'desde' => '2026-08-20', 'hasta' => '2026-08-20', 'orden' => 'mayor_total',
        ]));

        $response->assertOk()->assertSee($matching->numero_compra)
            ->assertDontSee('COM-B')->assertDontSee('COM-FOREIGN');
    }

    public function test_remote_catalog_searches_identifiers_and_excludes_other_store_or_inactive_products(): void
    {
        [$admin, , , $variant] = $this->context();
        $variant->update(['codigo_barra' => 'BAR-REMOTE-88', 'talla' => 'XL', 'color' => 'Turquesa']);
        [, , , $foreignVariant] = $this->context();
        $foreignVariant->update(['codigo_barra' => 'BAR-FOREIGN']);
        $inactive = $variant->producto->replicate();
        $inactive->codigo = 'INACTIVE-'.Str::upper(Str::random(5));
        $inactive->nombre = 'Producto inactivo remoto';
        $inactive->activo = false;
        $inactive->save();
        ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $inactive->tienda_id, 'producto_id' => $inactive->id,
            'talla' => 'XL', 'color' => 'Turquesa', 'stock' => 0, 'stock_minimo' => 0,
        ]);

        foreach (['BAR-REMOTE-88', $variant->producto->codigo, $variant->producto->nombre, 'XL', 'Turquesa'] as $search) {
            $this->actingAs($admin)->getJson(route('compras.catalogo', ['q' => $search]))
                ->assertOk()->assertJsonPath('data.0.id', $variant->id);
        }

        $content = $this->getJson(route('compras.catalogo'))->assertOk()->getContent();
        $this->assertStringNotContainsString('BAR-FOREIGN', $content);
        $this->assertStringNotContainsString('Producto inactivo remoto', $content);
    }

    public function test_remote_catalog_is_paginated_and_query_budget_is_constant(): void
    {
        [$admin, $store, , $variant] = $this->context();
        for ($index = 1; $index <= 45; $index++) {
            ProductoVariante::withoutGlobalScopes()->create([
                'tienda_id' => $store->id, 'producto_id' => $variant->producto_id,
                'talla' => 'T'.$index, 'color' => 'C'.$index, 'stock' => $index, 'stock_minimo' => 1,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($admin)->getJson(route('compras.catalogo'));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('per_page', 20);
        $this->assertLessThanOrEqual(4, $queryCount);
    }

    public function test_create_page_does_not_embed_the_complete_variant_catalog(): void
    {
        [$admin, $store, , $variant] = $this->context();
        for ($index = 1; $index <= 30; $index++) {
            ProductoVariante::withoutGlobalScopes()->create([
                'tienda_id' => $store->id, 'producto_id' => $variant->producto_id,
                'talla' => 'LOAD-'.$index, 'color' => 'Negro', 'stock' => 1, 'stock_minimo' => 0,
            ]);
        }

        $this->actingAs($admin)->get(route('compras.create'))
            ->assertOk()->assertSee('data-catalog-url', false)->assertDontSee('LOAD-30');
    }

    public function test_purchase_filter_indexes_exist(): void
    {
        $indexes = collect(Schema::getIndexes('compras'))->pluck('name');

        $this->assertTrue($indexes->contains('compras_tienda_estado_fecha'));
        $this->assertTrue($indexes->contains('compras_tienda_fecha_orden'));
    }

    /** @return array{User, Tienda, Proveedor, ProductoVariante, User} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $admin = User::create(['rol_id' => $role->id, 'tienda_id' => $store->id, 'name' => 'Admin '.$suffix, 'email' => $suffix.'@purchase-search.test', 'password' => 'password', 'activo' => true]);
        $supplier = Proveedor::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'nombre' => 'Proveedor '.$suffix, 'activo' => true]);
        $category = Categoria::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'nombre' => 'CategorÃ­a '.$suffix, 'slug' => 'categoria-'.$suffix, 'genero' => 'unisex', 'activo' => true]);
        $product = Producto::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'categoria_id' => $category->id, 'codigo' => 'P-'.Str::upper($suffix), 'nombre' => 'Producto '.$suffix, 'genero' => 'unisex', 'tipo' => 'casual', 'precio_compra' => 10, 'precio_venta' => 20, 'activo' => true]);
        $variant = ProductoVariante::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'producto_id' => $product->id, 'talla' => 'M', 'color' => 'Azul', 'stock' => 5, 'stock_minimo' => 1]);

        return [$admin, $store, $supplier, $variant, $admin];
    }

    private function purchase(Tienda $store, Proveedor $supplier, User $user, string $number, string $status, string $date, float $total): Compra
    {
        return Compra::withoutGlobalScopes()->create(['tienda_id' => $store->id, 'numero_compra' => $number, 'proveedor_id' => $supplier->id, 'user_id' => $user->id, 'fecha' => $date, 'total' => $total, 'estado' => $status]);
    }
}
