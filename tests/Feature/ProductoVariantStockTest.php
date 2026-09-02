<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductoVariantStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_routes_require_authentication(): void
    {
        [, $store] = $this->userWithStore('Administrador');
        $variant = $this->variant($this->product($store));

        $this->get(route('stock.index'))->assertRedirect(route('login'));
        $this->post(route('stock.update', $variant), $this->stockPayload())->assertRedirect(route('login'));
    }

    public function test_administrator_and_warehouse_can_adjust_but_seller_has_read_only_access(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $warehouse = $this->user($store, 'Almacén');
        $seller = $this->user($store, 'Vendedor');
        $variant = $this->variant($this->product($store));

        $this->actingAs($seller)->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Solo lectura');
        $this->post(route('stock.update', $variant), $this->stockPayload())->assertForbidden();

        $this->actingAs($warehouse)
            ->post(route('stock.update', $variant), $this->stockPayload(['stock' => 12]))
            ->assertSessionHas('success');
        $this->assertSame(12, $variant->fresh()->stock);

        $this->actingAs($administrator)
            ->post(route('stock.update', $variant), $this->stockPayload([
                'stock' => 14,
                'expected_version' => 1,
            ]))
            ->assertSessionHas('success');
        $this->assertSame(14, $variant->fresh()->stock);
    }

    public function test_manual_adjustment_is_atomic_versioned_and_audited(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $variant = $this->variant($this->product($store), 10, 3);

        $this->actingAs($administrator)
            ->post(route('stock.update', $variant), $this->stockPayload([
                'stock' => 7,
                'stock_minimo' => 4,
            ]))
            ->assertSessionHas('success');

        $variant->refresh();
        $this->assertSame(7, $variant->stock);
        $this->assertSame(4, $variant->stock_minimo);
        $this->assertSame(1, $variant->lock_version);
        $this->assertDatabaseHas('auditorias', [
            'tienda_id' => $store->id,
            'user_id' => $administrator->id,
            'accion' => 'stock.ajustado',
            'auditable_id' => $variant->id,
        ]);
        $audit = $variant->tienda->getConnection()->table('auditorias')->where('accion', 'stock.ajustado')->first();
        $metadata = json_decode($audit->metadata, true);
        $this->assertSame(['stock' => 10, 'stock_minimo' => 3], $metadata['anterior']);
        $this->assertSame(['stock' => 7, 'stock_minimo' => 4], $metadata['nuevo']);
        $movement = StockMovimiento::query()->sole();
        $this->assertSame(StockMovimiento::TIPO_AJUSTE, $movement->tipo);
        $this->assertSame('salida', $movement->direccion);
        $this->assertSame(10, $movement->stock_anterior);
        $this->assertSame(-3, $movement->cantidad);
        $this->assertSame(7, $movement->stock_resultante);
        $this->assertSame($administrator->id, $movement->user_id);
        $this->assertNull($movement->referencia_type);
    }

    public function test_stale_form_cannot_overwrite_a_more_recent_stock_change(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $variant = $this->variant($this->product($store), 10);
        $variant->update(['stock' => 8, 'lock_version' => 1]);

        $this->actingAs($administrator)
            ->post(route('stock.update', $variant), $this->stockPayload([
                'stock' => 20,
                'expected_version' => 0,
            ]))
            ->assertSessionHasErrors('stock');

        $variant->refresh();
        $this->assertSame(8, $variant->stock);
        $this->assertSame(1, $variant->lock_version);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_stock_validation_rejects_negative_excessive_and_missing_version_values(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $variant = $this->variant($this->product($store), 10);

        $this->actingAs($administrator)
            ->post(route('stock.update', $variant), [
                'stock' => -1,
                'stock_minimo' => 2000000001,
            ])
            ->assertSessionHasErrors(['stock', 'stock_minimo', 'expected_version']);

        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_real_adjustment_requires_a_meaningful_reason(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $variant = $this->variant($this->product($store), 10, 2);

        $this->actingAs($administrator)->post(route('stock.update', $variant), [
            'stock' => 8,
            'stock_minimo' => 2,
            'expected_version' => 0,
            'motivo' => 'x',
        ])->assertSessionHasErrors('motivo');

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_stock_route_binding_is_isolated_by_store(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        [, $foreignStore] = $this->userWithStore('Administrador');
        $foreignVariant = $this->variant($this->product($foreignStore));

        $this->actingAs($administrator)
            ->post(route('stock.update', $foreignVariant), $this->stockPayload())
            ->assertNotFound();
    }

    public function test_database_prevents_duplicate_size_and_color_for_the_same_product(): void
    {
        [, $store] = $this->userWithStore('Administrador');
        $product = $this->product($store);
        $this->variant($product, 5, 1, 'M', 'Negro');

        $this->expectException(QueryException::class);
        $this->variant($product, 3, 1, 'M', 'Negro');
    }

    public function test_received_purchase_increments_stock_and_version(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $product = $this->product($store);
        $variant = $this->variant($product, 5);
        $supplier = Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Proveedor de prueba',
            'activo' => true,
        ]);

        $this->actingAs($administrator)->post(route('compras.store'), [
            'proveedor_id' => $supplier->id,
            'fecha' => today()->toDateString(),
            'items' => [
                ['variante_id' => $variant->id, 'cantidad' => 4, 'precio' => 3.50],
            ],
        ])->assertRedirect(route('compras.index'))->assertSessionHasNoErrors()->assertSessionHas('success');

        $variant->refresh();
        $this->assertSame(9, $variant->stock);
        $this->assertSame(1, $variant->lock_version);
        $this->assertDatabaseHas('detalle_compras', [
            'variante_id' => $variant->id,
            'cantidad' => 4,
            'subtotal' => 14,
        ]);
        $movement = StockMovimiento::query()->sole();
        $this->assertSame(StockMovimiento::TIPO_COMPRA, $movement->tipo);
        $this->assertSame('entrada', $movement->direccion);
        $this->assertSame(5, $movement->stock_anterior);
        $this->assertSame(4, $movement->cantidad);
        $this->assertSame(9, $movement->stock_resultante);
        $this->assertSame(Compra::class, $movement->referencia_type);
    }

    public function test_repeated_purchase_operation_does_not_restock_twice(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $variant = $this->variant($this->product($store), 5);
        $supplier = Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Proveedor idempotente',
            'activo' => true,
        ]);
        $payload = [
            'operacion_id' => (string) Str::uuid(),
            'proveedor_id' => $supplier->id,
            'fecha' => today()->toDateString(),
            'items' => [['variante_id' => $variant->id, 'cantidad' => 4, 'precio' => 3.50]],
        ];

        $this->actingAs($administrator)->post(route('compras.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('compras.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('detalle_compras', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertSame(9, $variant->fresh()->stock);
        $this->assertSame(1, $variant->fresh()->lock_version);
    }

    private function stockPayload(array $overrides = []): array
    {
        return array_replace([
            'stock' => 10,
            'stock_minimo' => 2,
            'expected_version' => 0,
            'motivo' => 'Ajuste verificado',
        ], $overrides);
    }

    private function userWithStore(string $role): array
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
        $suffix = Str::lower(Str::random(10));
        $roleModel = Rol::firstOrCreate(['nombre' => $role]);

        return User::create([
            'rol_id' => $roleModel->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@variant-stock.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function product(Tienda $store): Producto
    {
        $suffix = Str::lower(Str::random(8));
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);

        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'PROD-'.Str::upper(Str::random(8)),
            'nombre' => 'Producto '.$suffix,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 3.50,
            'precio_venta' => 10,
            'activo' => true,
        ]);
    }

    private function variant(
        Producto $product,
        int $stock = 10,
        int $minimum = 2,
        string $size = 'M',
        string $color = 'Negro'
    ): ProductoVariante {
        return ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => $size,
            'color' => $color,
            'stock' => $stock,
            'stock_minimo' => $minimum,
        ]);
    }
}
