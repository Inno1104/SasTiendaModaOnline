<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductStatePlanAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_product_creation_respects_plan_limit_and_cleans_image(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->context(1);
        $category = $this->category($store);
        $this->product($store, $category, true);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category, [
                'imagen' => UploadedFile::fake()->image('blocked.jpg'),
            ]))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('productos', 1);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('productos'));
    }

    public function test_inactive_products_do_not_consume_plan_capacity(): void
    {
        [$administrator, $store] = $this->context(1);
        $category = $this->category($store);
        $this->product($store, $category, true);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category, [
                'nombre' => 'Producto inactivo',
                'activo' => 0,
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('productos', 2);
        $this->assertSame(1, Producto::where('activo', true)->count());
        $this->assertFalse($store->puedeAgregar('productos'));
    }

    public function test_reactivation_is_blocked_until_an_active_slot_is_released(): void
    {
        [$administrator, $store] = $this->context(1);
        $category = $this->category($store);
        $active = $this->product($store, $category, true, 'Activo');
        $inactive = $this->product($store, $category, false, 'Inactivo');

        $this->actingAs($administrator)
            ->put(route('productos.update', $inactive), $this->payload($category, [
                'nombre' => $inactive->nombre,
                'activo' => 1,
            ]))
            ->assertSessionHas('error');
        $this->assertFalse($inactive->fresh()->activo);
        $this->assertDatabaseCount('auditorias', 0);

        $this->delete(route('productos.destroy', $active))->assertSessionHas('success');
        $this->put(route('productos.update', $inactive), $this->payload($category, [
            'nombre' => $inactive->nombre,
            'activo' => 1,
        ]))->assertSessionHas('success');

        $this->assertFalse($active->fresh()->activo);
        $this->assertTrue($inactive->fresh()->activo);
        $this->assertSame(1, Producto::where('activo', true)->count());
        $this->assertDatabaseHas('auditorias', ['accion' => 'producto.desactivado', 'auditable_id' => $active->id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'producto.activado', 'auditable_id' => $inactive->id]);
    }

    public function test_unlimited_plan_allows_multiple_active_products(): void
    {
        [$administrator, $store] = $this->context(-1);
        $category = $this->category($store);

        for ($number = 1; $number <= 3; $number++) {
            $this->actingAs($administrator)
                ->post(route('productos.store'), $this->payload($category, ['nombre' => 'Producto '.$number]))
                ->assertSessionHas('success');
        }

        $this->assertSame(3, Producto::where('activo', true)->count());
    }

    public function test_create_and_real_updates_are_audited_with_actor_and_changes(): void
    {
        [$administrator, $store] = $this->context(10);
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category))
            ->assertSessionHas('success');
        $product = Producto::firstOrFail();

        $this->assertDatabaseHas('auditorias', [
            'tienda_id' => $store->id,
            'user_id' => $administrator->id,
            'accion' => 'producto.creado',
            'auditable_id' => $product->id,
        ]);

        $this->put(route('productos.update', $product), $this->payload($category, [
            'nombre' => 'Producto auditado',
            'precio_venta' => '25.00',
        ]))->assertSessionHas('success');

        $audit = Auditoria::where('accion', 'producto.actualizado')->firstOrFail();
        $this->assertSame('Producto con estado', $audit->metadata['cambios']['nombre']['anterior']);
        $this->assertSame('Producto auditado', $audit->metadata['cambios']['nombre']['nuevo']);
        $this->assertEquals(20, $audit->metadata['cambios']['precio_venta']['anterior']);
        $this->assertEquals(25, $audit->metadata['cambios']['precio_venta']['nuevo']);
    }

    public function test_no_op_update_and_repeated_deactivation_do_not_create_duplicate_audits(): void
    {
        [$administrator, $store] = $this->context(10);
        $category = $this->category($store);
        $product = $this->product($store, $category, true, 'Sin cambios');
        $this->actingAs($administrator);

        $this->put(route('productos.update', $product), $this->payload($category, [
            'nombre' => $product->nombre,
        ]))->assertSessionHas('success');
        $this->assertDatabaseCount('auditorias', 0);

        $this->delete(route('productos.destroy', $product))->assertSessionHas('success');
        $this->delete(route('productos.destroy', $product->fresh()))->assertSessionHas('success');

        $this->assertSame(1, Auditoria::where('accion', 'producto.desactivado')->count());
    }

    public function test_existing_product_accepts_a_new_size_color_variant_and_records_initial_stock(): void
    {
        [$administrator, $store] = $this->context(10);
        $category = $this->category($store);
        $product = $this->product($store, $category, true);
        ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => 4,
            'stock_minimo' => 2,
        ]);

        $this->actingAs($administrator)
            ->put(route('productos.update', $product), $this->payload($category, [
                'nombre' => $product->nombre,
                'variantes' => [[
                    'talla' => 'L',
                    'color' => 'Azul',
                    'codigo_barra' => 'NUEVA-L-AZUL',
                    'stock' => 8,
                    'stock_minimo' => 3,
                ]],
            ]))
            ->assertSessionHas('success');

        $variant = ProductoVariante::withoutGlobalScopes()
            ->where('producto_id', $product->id)
            ->where('talla', 'L')
            ->where('color', 'Azul')
            ->sole();
        $movement = StockMovimiento::withoutGlobalScopes()->where('variante_id', $variant->id)->sole();

        $this->assertSame(8, $movement->cantidad);
        $this->assertSame(8, $movement->stock_resultante);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'producto.variantes_agregadas',
            'auditable_id' => $product->id,
            'user_id' => $administrator->id,
        ]);
    }

    public function test_existing_product_rejects_a_duplicate_size_color_variant(): void
    {
        [$administrator, $store] = $this->context(10);
        $category = $this->category($store);
        $product = $this->product($store, $category, true);
        ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => 4,
            'stock_minimo' => 2,
        ]);

        $this->actingAs($administrator)
            ->put(route('productos.update', $product), $this->payload($category, [
                'nombre' => $product->nombre,
                'variantes' => [[
                    'talla' => 'm',
                    'color' => 'negro',
                    'codigo_barra' => '',
                    'stock' => 3,
                    'stock_minimo' => 1,
                ]],
            ]))
            ->assertSessionHasErrors('variantes.0.talla');

        $this->assertSame(1, ProductoVariante::withoutGlobalScopes()->where('producto_id', $product->id)->count());
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_existing_variant_metadata_can_be_edited_without_changing_stock_and_updates_images(): void
    {
        [$administrator, $store] = $this->context(10);
        $category = $this->category($store);
        $product = $this->product($store, $category, true);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Beige',
            'codigo_barra' => 'ANTIGUO-M',
            'stock' => 10,
            'stock_minimo' => 2,
        ]);
        $image = ProductoImagen::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'ruta' => 'productos/'.$store->id.'/beige.webp',
            'talla' => 'M',
            'color' => 'Beige',
            'orden' => 0,
            'principal' => true,
        ]);

        $this->actingAs($administrator)->put(route('productos.update', $product), $this->payload($category, [
            'nombre' => $product->nombre,
            'variantes_existentes' => [[
                'id' => $variant->id,
                'talla' => 'L',
                'detalle_talla' => 'Pecho 92-96 cm; cintura 78-82 cm',
                'color' => 'Arena',
                'codigo_barra' => 'ARENA-L',
                'stock_minimo' => 4,
            ]],
        ]))->assertSessionHas('success');

        $variant->refresh();
        $image->refresh();
        $this->assertSame('L', $variant->talla);
        $this->assertSame('Pecho 92-96 cm; cintura 78-82 cm', $variant->detalle_talla);
        $this->assertSame('Arena', $variant->color);
        $this->assertSame('ARENA-L', $variant->codigo_barra);
        $this->assertSame(4, $variant->stock_minimo);
        $this->assertSame(10, $variant->stock);
        $this->assertSame('L', $image->talla);
        $this->assertSame('Arena', $image->color);
        $this->assertDatabaseHas('auditorias', ['accion' => 'producto.variantes_actualizadas', 'auditable_id' => $product->id]);
    }

    public function test_existing_variant_stock_and_prices_are_updated_with_kardex_reason(): void
    {
        [$administrator, $store] = $this->context(10);
        $category = $this->category($store);
        $product = $this->product($store, $category, true);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'producto_id' => $product->id,
            'talla' => 'M', 'color' => 'Negro', 'stock' => 10, 'stock_minimo' => 2,
        ]);

        $this->actingAs($administrator)->put(route('productos.update', $product), $this->payload($category, [
            'nombre' => $product->nombre,
            'variantes_existentes' => [[
                'id' => $variant->id, 'talla' => 'M', 'color' => 'Negro', 'codigo_barra' => '',
                'precio_compra' => '12.50', 'precio_venta' => '30.00', 'precio_oferta' => '25.00',
                'stock' => 16, 'stock_minimo' => 4, 'motivo_stock' => 'Conteo físico confirmado',
            ]],
        ]))->assertSessionHas('success');

        $variant->refresh();
        $this->assertSame(16, $variant->stock);
        $this->assertSame('12.50', $variant->precio_compra);
        $this->assertSame('30.00', $variant->precio_venta);
        $this->assertSame('25.00', $variant->precio_oferta);
        $this->assertDatabaseHas('stock_movimientos', [
            'variante_id' => $variant->id, 'tipo' => 'ajuste', 'stock_anterior' => 10,
            'stock_resultante' => 16, 'motivo' => 'Conteo físico confirmado',
        ]);
    }

    private function payload(Categoria $category, array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Producto con estado',
            'categoria_id' => $category->id,
            'marca_id' => '',
            'proveedor_id' => '',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'descripcion' => '',
            'precio_compra' => '10.00',
            'precio_venta' => '20.00',
            'precio_oferta' => '',
            'activo' => 1,
            'destacado' => 0,
            'variantes' => [],
        ], $overrides);
    }

    private function context(int $productLimit): array
    {
        $suffix = Str::lower(Str::random(10));
        $plan = Plan::create([
            'nombre' => 'Plan '.$suffix,
            'slug' => 'plan-'.$suffix,
            'precio' => 10,
            'max_productos' => $productLimit,
            'max_usuarios' => -1,
            'max_ventas_mes' => -1,
            'activo' => true,
        ]);
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'plan_id' => $plan->id,
            'estado' => 'activa',
        ]);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@product-state.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function category(Tienda $store): Categoria
    {
        $suffix = Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);
    }

    private function product(
        Tienda $store,
        Categoria $category,
        bool $active,
        string $name = 'Producto existente'
    ): Producto {
        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'PROD-'.Str::upper(Str::random(8)),
            'nombre' => $name,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'activo' => $active,
            'destacado' => false,
        ]);
    }
}
