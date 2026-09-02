<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProveedorProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_supplier_product_purchase_stock_and_state_flow(): void
    {
        [$admin, $store] = $this->context();
        $category = $this->category($store);

        $this->actingAs($admin)->post(route('proveedores.store'), $this->supplierPayload('Proveedor integral'))
            ->assertSessionHas('success');
        $supplier = Proveedor::query()->sole();

        $this->post(route('productos.store'), $this->productPayload($category->id, $supplier->id))
            ->assertRedirect(route('productos.index'));
        $product = Producto::query()->where('nombre', 'Producto integral')->firstOrFail();
        $variant = $product->variantes()->sole();
        $this->assertSame(5, $variant->stock);

        $this->post(route('compras.store'), $this->purchasePayload($supplier->id, $variant->id, 4))
            ->assertRedirect();
        $purchase = Compra::query()->sole();
        $this->assertSame(9, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movimientos', [
            'tienda_id' => $store->id,
            'variante_id' => $variant->id,
            'tipo' => StockMovimiento::TIPO_COMPRA,
            'cantidad' => 4,
            'stock_anterior' => 5,
            'stock_resultante' => 9,
        ]);

        $this->patch(route('proveedores.estado', $supplier), ['activo' => false])
            ->assertSessionHas('success');
        $this->post(route('compras.store'), $this->purchasePayload($supplier->id, $variant->id, 1))
            ->assertSessionHasErrors('proveedor_id');
        $this->get(route('compras.show', $purchase))->assertOk()->assertSee('Proveedor integral');
        $this->assertSame($supplier->id, $product->fresh()->proveedor_id);
        $this->assertSame($supplier->id, $purchase->fresh()->proveedor_id);
        $this->assertSame(9, $variant->fresh()->stock);

        $this->patch(route('proveedores.estado', $supplier), ['activo' => true])
            ->assertSessionHas('success');
        $this->assertSame(
            ['proveedor.creado', 'proveedor.desactivado', 'proveedor.activado'],
            Auditoria::query()->where('auditable_type', Proveedor::class)->orderBy('id')->pluck('accion')->all()
        );
    }

    public function test_permissions_and_multi_store_isolation_remain_enforced(): void
    {
        [$admin, $store] = $this->context();
        $ownSupplier = $this->supplier($store, 'Proveedor propio');
        [$foreignAdmin, $foreignStore] = $this->context();
        $foreignSupplier = $this->supplier($foreignStore, 'Proveedor ajeno');
        $seller = $this->user($store, 'Vendedor');
        $warehouse = $this->user($store, 'Almacén');

        $this->actingAs($seller)->get(route('proveedores.index'))
            ->assertOk()
            ->assertSee($ownSupplier->nombre)
            ->assertDontSee($foreignSupplier->nombre);
        $this->post(route('proveedores.store'), $this->supplierPayload('No autorizado'))->assertForbidden();
        $this->patch(route('proveedores.estado', $ownSupplier), ['activo' => false])->assertForbidden();

        $this->actingAs($warehouse)->post(route('proveedores.store'), $this->supplierPayload('Proveedor almacén'))
            ->assertSessionHas('success');
        $this->actingAs($admin)->get(route('proveedores.edit', $foreignSupplier))->assertNotFound();
        $this->actingAs($foreignAdmin)->get(route('proveedores.edit', $ownSupplier))->assertNotFound();
    }

    public function test_equivalent_duplicate_requests_are_serialized_and_leave_one_record(): void
    {
        [$firstAdmin, $store] = $this->context();
        $secondAdmin = $this->user($store, 'Administrador');

        $this->actingAs($firstAdmin)->post(
            route('proveedores.store'),
            $this->supplierPayload('Proveedor primero', '20-512.345-678')
        )->assertSessionHas('success');
        $this->actingAs($secondAdmin)->post(
            route('proveedores.store'),
            $this->supplierPayload('Proveedor repetido', '20 512 345 678')
        )->assertSessionHasErrors('ruc');

        $this->assertSame(1, Proveedor::withoutGlobalScopes()->where('tienda_id', $store->id)->count());
        $this->assertDatabaseHas('proveedores', ['tienda_id' => $store->id, 'ruc' => '20512345678']);
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertSame('proveedor.creado', Auditoria::query()->sole()->accion);
    }

    public function test_supplier_schema_contains_production_constraints_and_indexes(): void
    {
        $supplierIndexes = collect(Schema::getIndexes('proveedores'))->keyBy('name');
        $productIndexes = collect(Schema::getIndexes('productos'))->pluck('name');
        $purchaseIndexes = collect(Schema::getIndexes('compras'))->pluck('name');

        $this->assertTrue(Schema::hasColumns('proveedores', [
            'id', 'tienda_id', 'nombre', 'ruc', 'email', 'telefono', 'activo', 'created_at', 'updated_at',
        ]));
        $this->assertTrue((bool) $supplierIndexes->get('proveedores_tienda_ruc_unique')['unique']);
        $this->assertTrue($supplierIndexes->has('proveedores_tienda_estado_nombre'));
        $this->assertTrue($supplierIndexes->has('proveedores_tienda_fecha'));
        $this->assertContains('productos_tienda_proveedor', $productIndexes);
        $this->assertContains('compras_tienda_proveedor', $purchaseIndexes);
        $this->assertFileExists(database_path('migrations/2026_08_25_000029_secure_supplier_identity.php'));
        $this->assertFileExists(database_path('migrations/2026_08_25_000030_optimize_supplier_queries.php'));
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);

        return [$this->user($store, 'Administrador'), $store];
    }

    private function user(Tienda $store, string $roleName): User
    {
        $suffix = Str::lower(Str::random(10));
        $role = Rol::firstOrCreate(['nombre' => $roleName]);

        return User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => $roleName.' '.$suffix,
            'email' => $suffix.'@supplier-production.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function supplier(Tienda $store, string $name): Proveedor
    {
        return Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'ruc' => (string) random_int(10000000000, 99999999999),
            'activo' => true,
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

    /** @return array<string, mixed> */
    private function supplierPayload(string $name, string $taxId = '20512345678'): array
    {
        return ['nombre' => $name, 'ruc' => $taxId, 'email' => 'ventas@example.test', 'telefono' => '0999999999', 'direccion' => 'Calle principal', 'ciudad' => 'Quito', 'pais' => 'Ecuador', 'contacto' => 'Contacto'];
    }

    /** @return array<string, mixed> */
    private function productPayload(int $categoryId, int $supplierId): array
    {
        return ['nombre' => 'Producto integral', 'categoria_id' => $categoryId, 'marca_id' => '', 'proveedor_id' => $supplierId, 'genero' => 'unisex', 'tipo' => 'casual', 'descripcion' => '', 'precio_compra' => '5.00', 'precio_venta' => '10.00', 'precio_oferta' => '', 'activo' => 1, 'destacado' => 0, 'variantes' => [['talla' => 'M', 'color' => 'Azul', 'codigo_barra' => 'SUP-PROD-001', 'stock' => 5, 'stock_minimo' => 2]]];
    }

    /** @return array<string, mixed> */
    private function purchasePayload(int $supplierId, int $variantId, int $quantity): array
    {
        return ['operacion_id' => (string) Str::uuid(), 'proveedor_id' => $supplierId, 'fecha' => today()->toDateString(), 'items' => [['variante_id' => $variantId, 'cantidad' => $quantity, 'precio' => '5.00']]];
    }
}
