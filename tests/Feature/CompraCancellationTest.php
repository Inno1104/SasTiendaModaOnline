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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompraCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_cancels_received_purchase_and_reverses_kardex_exactly(): void
    {
        [$admin, $store, $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 4);

        $this->assertSame(9, $variant->fresh()->stock);
        $this->post(route('compras.cancel', $purchase), [
            'motivo' => 'Mercadería recibida con daños graves',
        ])->assertRedirect()->assertSessionHas('success');

        $purchase->refresh();
        $this->assertSame('anulado', $purchase->estado);
        $this->assertSame($admin->id, $purchase->anulada_por);
        $this->assertNotNull($purchase->anulada_at);
        $this->assertSame('Mercadería recibida con daños graves', $purchase->motivo_anulacion);
        $this->assertSame(5, $variant->fresh()->stock);

        $movements = StockMovimiento::query()->where('referencia_type', Compra::class)
            ->where('referencia_id', $purchase->id)->orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertSame([StockMovimiento::TIPO_COMPRA, StockMovimiento::TIPO_ANULACION], $movements->pluck('tipo')->all());
        $this->assertSame([4, -4], $movements->pluck('cantidad')->all());
        $this->assertSame(['entrada', 'salida'], $movements->pluck('direccion')->all());
        $this->assertSame([5, 9], $movements->pluck('stock_anterior')->all());
        $this->assertSame([9, 5], $movements->pluck('stock_resultante')->all());
    }

    public function test_only_administrator_can_cancel_a_purchase(): void
    {
        [$admin, $store, $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 2);
        $warehouse = $this->user($store, 'Almacén');

        $this->actingAs($warehouse)->post(route('compras.cancel', $purchase), [
            'motivo' => 'Intento de anulación desde almacén',
        ])->assertForbidden();

        $this->assertSame('recibido', $purchase->fresh()->estado);
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movimientos', 1);
    }

    public function test_cancellation_requires_a_meaningful_trimmed_reason(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 2);

        $this->post(route('compras.cancel', $purchase), ['motivo' => ' corto '])
            ->assertSessionHasErrors('motivo');

        $this->assertSame('recibido', $purchase->fresh()->estado);
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movimientos', 1);
    }

    public function test_cancellation_is_rejected_when_received_units_are_no_longer_available(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 4);
        $variant->update(['stock' => 3]);

        $this->post(route('compras.cancel', $purchase), [
            'motivo' => 'Devolución total solicitada al proveedor',
        ])->assertSessionHasErrors('compra');

        $this->assertSame('recibido', $purchase->fresh()->estado);
        $this->assertNull($purchase->fresh()->anulada_at);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movimientos', 1);
    }

    public function test_purchase_cannot_be_cancelled_twice(): void
    {
        [$admin, , $supplier, $variant] = $this->context();
        $purchase = $this->receive($admin, $supplier, $variant, 2);
        $reason = ['motivo' => 'Compra duplicada confirmada por administración'];

        $this->post(route('compras.cancel', $purchase), $reason)->assertSessionHas('success');
        $this->post(route('compras.cancel', $purchase), $reason)->assertSessionHasErrors('compra');

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movimientos', 2);
    }

    public function test_pending_purchase_cannot_use_received_purchase_reversal(): void
    {
        [$admin, $store, $supplier] = $this->context();
        $purchase = Compra::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'numero_compra' => 'PENDING-'.Str::upper(Str::random(8)),
            'proveedor_id' => $supplier->id,
            'user_id' => $admin->id,
            'fecha' => now(),
            'total' => 0,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($admin)->post(route('compras.cancel', $purchase), [
            'motivo' => 'Intento sobre una compra todavía pendiente',
        ])->assertSessionHasErrors('compra');

        $this->assertSame('pendiente', $purchase->fresh()->estado);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_purchase_cancellation_is_isolated_by_store(): void
    {
        [$admin] = $this->context();
        [$foreignAdmin, , $supplier, $variant] = $this->context();
        $purchase = $this->receive($foreignAdmin, $supplier, $variant, 2);

        $this->actingAs($admin)->post(route('compras.cancel', $purchase), [
            'motivo' => 'Intento de anulación de otra tienda',
        ])->assertNotFound();

        $this->assertSame('recibido', $purchase->fresh()->estado);
        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_all_variants_are_validated_before_reversing_any_stock(): void
    {
        [$admin, $store, $supplier, $firstVariant] = $this->context();
        $secondVariant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $firstVariant->producto_id,
            'talla' => 'L',
            'color' => 'Rojo',
            'stock' => 5,
            'stock_minimo' => 1,
        ]);
        $this->actingAs($admin)->post(route('compras.store'), [
            'operacion_id' => (string) Str::uuid(),
            'proveedor_id' => $supplier->id,
            'fecha' => now()->format('Y-m-d'),
            'items' => [
                ['variante_id' => $firstVariant->id, 'cantidad' => 2, 'precio' => '10.00'],
                ['variante_id' => $secondVariant->id, 'cantidad' => 3, 'precio' => '10.00'],
            ],
        ])->assertSessionHasNoErrors();
        $purchase = Compra::query()->sole();
        $secondVariant->update(['stock' => 2]);

        $this->post(route('compras.cancel', $purchase), [
            'motivo' => 'Devolución completa de todas las variantes',
        ])->assertSessionHasErrors('compra');

        $this->assertSame(7, $firstVariant->fresh()->stock);
        $this->assertSame(2, $secondVariant->fresh()->stock);
        $this->assertSame('recibido', $purchase->fresh()->estado);
        $this->assertDatabaseCount('stock_movimientos', 2);
    }

    /** @return array{User, Tienda, Proveedor, ProductoVariante} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $admin = $this->user($store, 'Administrador');
        $supplier = Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'nombre' => 'Proveedor '.$suffix, 'activo' => true,
        ]);
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'nombre' => 'Categoría '.$suffix, 'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex', 'activo' => true,
        ]);
        $product = Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'categoria_id' => $category->id, 'codigo' => 'P-'.Str::upper($suffix),
            'nombre' => 'Producto '.$suffix, 'genero' => 'unisex', 'tipo' => 'casual',
            'precio_compra' => 10, 'precio_venta' => 20, 'activo' => true,
        ]);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'producto_id' => $product->id, 'talla' => 'M', 'color' => 'Azul',
            'stock' => 5, 'stock_minimo' => 1,
        ]);

        return [$admin, $store, $supplier, $variant];
    }

    private function user(Tienda $store, string $role): User
    {
        $suffix = Str::lower(Str::random(8));
        $roleModel = Rol::firstOrCreate(['nombre' => $role]);

        return User::create([
            'rol_id' => $roleModel->id, 'tienda_id' => $store->id, 'name' => $role.' '.$suffix,
            'email' => $suffix.'@purchase-cancel.test', 'password' => 'password', 'activo' => true,
        ]);
    }

    private function receive(User $user, Proveedor $supplier, ProductoVariante $variant, int $quantity): Compra
    {
        $this->actingAs($user)->post(route('compras.store'), [
            'operacion_id' => (string) Str::uuid(),
            'proveedor_id' => $supplier->id,
            'fecha' => now()->format('Y-m-d'),
            'items' => [[
                'variante_id' => $variant->id,
                'cantidad' => $quantity,
                'precio' => '10.00',
            ]],
        ])->assertSessionHasNoErrors();

        return Compra::query()->latest('id')->firstOrFail();
    }
}
