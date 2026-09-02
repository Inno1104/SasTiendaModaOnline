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
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompraSecurityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_routes_require_authentication(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $purchase = $this->purchase($store, $supplier, $admin);

        $this->get(route('compras.index'))->assertRedirect(route('login'));
        $this->get(route('compras.create'))->assertRedirect(route('login'));
        $this->post(route('compras.store'), $this->payload($supplier, $variant))->assertRedirect(route('login'));
        $this->get(route('compras.show', $purchase))->assertRedirect(route('login'));
    }

    public function test_only_administrators_and_warehouse_users_can_access_purchases(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $warehouse = $this->user($store, 'Almacén');
        $seller = $this->user($store, 'Vendedor');

        $this->actingAs($seller)->get(route('compras.index'))->assertForbidden();
        $this->get(route('compras.create'))->assertForbidden();
        $this->post(route('compras.store'), $this->payload($supplier, $variant))->assertForbidden();

        $this->actingAs($warehouse)->get(route('compras.index'))->assertOk();
        $this->get(route('compras.create'))->assertOk();
        $this->post(route('compras.store'), $this->payload($supplier, $variant))
            ->assertRedirect(route('compras.index'));
    }

    public function test_inactive_users_cannot_access_or_register_purchases(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $admin->update(['activo' => false]);

        $this->actingAs($admin)->get(route('compras.index'))->assertForbidden();
        $this->post(route('compras.store'), $this->payload($supplier, $variant))->assertRedirect(route('login'));
        $this->assertDatabaseCount('compras', 0);
    }

    public function test_purchase_forces_current_store_and_persists_only_validated_fields(): void
    {
        [$admin, $store] = $this->context();
        [, $foreignStore] = $this->context();
        [$supplier, $variant] = $this->catalog($store);

        $this->actingAs($admin)->post(route('compras.store'), [
            ...$this->payload($supplier, $variant),
            'tienda_id' => $foreignStore->id,
            'estado' => 'anulado',
            'total' => 1,
            'observaciones' => '  Recepción verificada  ',
        ])->assertRedirect(route('compras.index'));

        $purchase = Compra::withoutGlobalScopes()->sole();
        $this->assertSame($store->id, $purchase->tienda_id);
        $this->assertSame('recibido', $purchase->estado);
        $this->assertSame('Recepción verificada', $purchase->observaciones);
        $this->assertSame('25.00', $purchase->total);
    }

    public function test_foreign_and_inactive_resources_are_rejected_without_partial_writes(): void
    {
        [$admin, $store] = $this->context();
        [, $foreignStore] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        [$foreignSupplier, $foreignVariant] = $this->catalog($foreignStore);

        $this->actingAs($admin)->post(route('compras.store'), $this->payload($foreignSupplier, $variant))
            ->assertSessionHasErrors('proveedor_id');
        $this->post(route('compras.store'), $this->payload($supplier, $foreignVariant))
            ->assertSessionHasErrors('items.0.variante_id');

        $variant->producto->update(['activo' => false]);
        $this->post(route('compras.store'), $this->payload($supplier, $variant))
            ->assertSessionHasErrors('items.0.variante_id');

        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('detalle_compras', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_lines_dates_prices_and_payload_size_are_strictly_validated(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $duplicated = [
            ['variante_id' => $variant->id, 'cantidad' => 1, 'precio' => '10.999'],
            ['variante_id' => $variant->id, 'cantidad' => 1, 'precio' => '10.00'],
        ];

        $this->actingAs($admin)->post(route('compras.store'), [
            ...$this->payload($supplier, $variant),
            'fecha' => now()->addDay()->format('Y-m-d'),
            'observaciones' => str_repeat('x', 1001),
            'items' => $duplicated,
        ])->assertSessionHasErrors([
            'fecha',
            'observaciones',
            'items.0.precio',
        ]);

        $tooManyItems = array_fill(0, 101, [
            'variante_id' => $variant->id,
            'cantidad' => 1,
            'precio' => '1.00',
        ]);
        $this->post(route('compras.store'), [
            ...$this->payload($supplier, $variant),
            'items' => $tooManyItems,
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('compras', 0);
    }

    public function test_total_overflow_rolls_back_purchase_details_stock_and_kardex(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);

        $this->actingAs($admin)->post(route('compras.store'), [
            ...$this->payload($supplier, $variant),
            'items' => [[
                'variante_id' => $variant->id,
                'cantidad' => 2,
                'precio' => '99999999.99',
            ]],
        ])->assertSessionHasErrors('items.0.precio');

        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('detalle_compras', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_service_rechecks_tenant_when_called_directly(): void
    {
        [$admin, $store] = $this->context();
        [, $foreignStore] = $this->context();
        [, $variant] = $this->catalog($store);
        [$foreignSupplier] = $this->catalog($foreignStore);

        $this->actingAs($admin);

        try {
            app(PurchaseService::class)->receive($this->payload($foreignSupplier, $variant), $admin);
            $this->fail('El servicio debió rechazar un proveedor de otra tienda.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('proveedor_id', $exception->errors());
        }

        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_repeated_operation_returns_original_purchase_without_consuming_a_number(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $payload = $this->payload($supplier, $variant);

        $this->actingAs($admin)->post(route('compras.store'), $payload)->assertSessionHasNoErrors();
        $purchase = Compra::query()->sole();
        $supplier->update(['activo' => false]);
        $variant->producto->update(['activo' => false]);

        $this->post(route('compras.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('detalle_compras', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame("C-{$store->id}-".now()->year.'-00001', $purchase->numero_compra);
        $this->assertDatabaseHas('compra_contadores', [
            'tienda_id' => $store->id,
            'anio' => now()->year,
            'ultimo_numero' => 1,
        ]);
    }

    public function test_operation_key_cannot_be_reused_with_different_purchase_data(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $payload = $this->payload($supplier, $variant);

        $this->actingAs($admin)->post(route('compras.store'), $payload)->assertSessionHasNoErrors();
        $payload['items'][0]['cantidad'] = 3;
        $this->post(route('compras.store'), $payload)->assertSessionHasErrors('operacion_id');

        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('detalle_compras', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertDatabaseHas('compra_contadores', [
            'tienda_id' => $store->id,
            'anio' => now()->year,
            'ultimo_numero' => 1,
        ]);
    }

    public function test_duplicate_lines_with_the_same_price_are_consolidated_exactly(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $payload = $this->payload($supplier, $variant);
        $payload['items'] = [
            ['variante_id' => $variant->id, 'cantidad' => 2, 'precio' => '12.50'],
            ['variante_id' => $variant->id, 'cantidad' => 3, 'precio' => '12.5'],
        ];

        $this->actingAs($admin)->post(route('compras.store'), $payload)->assertSessionHasNoErrors();

        $purchase = Compra::query()->sole();
        $this->assertSame('62.50', $purchase->total);
        $this->assertDatabaseCount('detalle_compras', 1);
        $this->assertDatabaseHas('detalle_compras', [
            'compra_id' => $purchase->id,
            'variante_id' => $variant->id,
            'cantidad' => 5,
            'precio_unitario' => 12.50,
            'subtotal' => 62.50,
        ]);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_duplicate_lines_with_different_prices_are_rejected_atomically(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $payload = $this->payload($supplier, $variant);
        $payload['items'] = [
            ['variante_id' => $variant->id, 'cantidad' => 1, 'precio' => '12.50'],
            ['variante_id' => $variant->id, 'cantidad' => 1, 'precio' => '12.51'],
        ];

        $this->actingAs($admin)->post(route('compras.store'), $payload)
            ->assertSessionHasErrors('items.1.precio');

        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('detalle_compras', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_equivalent_normalized_lines_are_idempotent(): void
    {
        [$admin, $store] = $this->context();
        [$supplier, $variant] = $this->catalog($store);
        $payload = $this->payload($supplier, $variant);
        $payload['items'] = [
            ['variante_id' => $variant->id, 'cantidad' => 1, 'precio' => '12.5'],
            ['variante_id' => $variant->id, 'cantidad' => 1, 'precio' => '12.50'],
        ];

        $this->actingAs($admin)->post(route('compras.store'), $payload)->assertSessionHasNoErrors();
        $payload['items'] = [
            ['variante_id' => $variant->id, 'cantidad' => 2, 'precio' => '12.50'],
        ];
        $this->post(route('compras.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('detalle_compras', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame('25.00', Compra::query()->sole()->total);
    }

    /** @return array{User, Tienda} */
    private function context(string $role = 'Administrador'): array
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
            'email' => $suffix.'@purchase-security.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    /** @return array{Proveedor, ProductoVariante} */
    private function catalog(Tienda $store): array
    {
        $suffix = Str::lower(Str::random(8));
        $supplier = Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Proveedor '.$suffix,
            'activo' => true,
        ]);
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);
        $product = Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'proveedor_id' => $supplier->id,
            'codigo' => 'PROD-'.Str::upper($suffix),
            'nombre' => 'Producto '.$suffix,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 12.50,
            'precio_venta' => 20,
            'activo' => true,
        ]);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Azul',
            'stock' => 5,
            'stock_minimo' => 1,
        ]);

        return [$supplier, $variant];
    }

    /** @return array<string, mixed> */
    private function payload(Proveedor $supplier, ProductoVariante $variant): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'proveedor_id' => $supplier->id,
            'fecha' => now()->format('Y-m-d'),
            'observaciones' => null,
            'items' => [[
                'variante_id' => $variant->id,
                'cantidad' => 2,
                'precio' => '12.50',
            ]],
        ];
    }

    private function purchase(Tienda $store, Proveedor $supplier, User $user): Compra
    {
        return Compra::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'numero_compra' => 'C-TEST-'.Str::upper(Str::random(8)),
            'proveedor_id' => $supplier->id,
            'user_id' => $user->id,
            'fecha' => now(),
            'total' => 0,
            'estado' => 'recibido',
        ]);
    }
}
