<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\ProductImageStorage;
use App\Services\ProductoManager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProductImageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_image_is_stored_inside_current_store_directory(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category, [
                'imagen' => UploadedFile::fake()->image('producto.webp', 1200, 1200),
            ]))
            ->assertSessionHas('success');

        $product = Producto::firstOrFail();
        $this->assertStringStartsWith('productos/'.$store->id.'/', $product->imagen);
        Storage::disk('public')->assertExists($product->imagen);
    }

    public function test_update_without_a_new_file_preserves_current_image(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);
        $product = $this->product($store, $category, 'productos/'.$store->id.'/current.jpg');
        Storage::disk('public')->put($product->imagen, 'current-image');

        $this->actingAs($administrator)
            ->put(route('productos.update', $product), $this->payload($category, [
                'nombre' => 'Producto sin cambio de imagen',
            ]))
            ->assertSessionHas('success');

        $this->assertSame('productos/'.$store->id.'/current.jpg', $product->fresh()->imagen);
        Storage::disk('public')->assertExists($product->imagen);
    }

    public function test_update_can_add_a_new_variant_with_its_color_images(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);
        $product = $this->product($store, $category, '');
        $images = collect(range(0, 4))->mapWithKeys(fn (int $index) => [$index => [
            'archivo' => UploadedFile::fake()->image("nuevo-rojo-$index.webp", 800, 800),
            'talla' => 'M',
            'color' => 'Rojo',
        ]])->all();

        $this->actingAs($administrator)->put(route('productos.update', $product), $this->payload($category, [
            'nombre' => $product->nombre,
            'variantes' => [['talla' => 'M', 'color' => 'Rojo', 'codigo_barra' => 'ROJO-M', 'stock' => 9, 'stock_minimo' => 2]],
            'imagenes' => $images,
        ]))->assertSessionHas('success');

        $product->refresh()->load('variantes', 'imagenes');
        $this->assertCount(1, $product->variantes);
        $this->assertCount(5, $product->imagenes);
        $this->assertSame('M', $product->imagenes->first()->talla);
        $this->assertSame('Rojo', $product->imagenes->first()->color);
        $this->assertTrue($product->imagenes->first()->principal);
        $this->assertSame($product->imagenes->first()->ruta, $product->imagen);
        foreach ($product->imagenes as $image) Storage::disk('public')->assertExists($image->ruta);
        $this->assertDatabaseHas('auditorias', ['accion' => 'producto.imagenes_agregadas', 'auditable_id' => $product->id]);
    }

    public function test_existing_gallery_images_can_be_replaced_and_deleted_safely(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);
        $product = $this->product($store, $category, 'productos/'.$store->id.'/principal.jpg');
        $replace = ProductoImagen::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'producto_id' => $product->id,
            'ruta' => $product->imagen, 'talla' => null, 'color' => 'Beige', 'orden' => 0, 'principal' => true,
        ]);
        $remove = ProductoImagen::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'producto_id' => $product->id,
            'ruta' => 'productos/'.$store->id.'/alterna.jpg', 'talla' => null, 'color' => 'Beige', 'orden' => 1, 'principal' => false,
        ]);
        Storage::disk('public')->put($replace->ruta, 'old-main');
        Storage::disk('public')->put($remove->ruta, 'old-alternate');

        $this->actingAs($administrator)->put(route('productos.update', $product), $this->payload($category, [
            'imagenes_existentes' => [
                ['id' => $replace->id, 'archivo' => UploadedFile::fake()->image('reemplazo.webp', 800, 800)],
                ['id' => $remove->id, 'eliminar' => 1],
            ],
        ]))->assertSessionHas('success');

        $replacementPath = $replace->fresh()->ruta;
        $this->assertNotSame('productos/'.$store->id.'/principal.jpg', $replacementPath);
        $this->assertSame($replacementPath, $product->fresh()->imagen);
        $this->assertDatabaseMissing('producto_imagenes', ['id' => $remove->id]);
        Storage::disk('public')->assertExists($replacementPath);
        Storage::disk('public')->assertMissing('productos/'.$store->id.'/principal.jpg');
        Storage::disk('public')->assertMissing('productos/'.$store->id.'/alterna.jpg');
        $this->assertDatabaseHas('auditorias', ['accion' => 'producto.imagenes_actualizadas', 'auditable_id' => $product->id]);
    }

    public function test_multiple_sizes_are_split_with_full_stock_and_share_color_images(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);
        $product = $this->product($store, $category, '');

        $this->actingAs($administrator)->put(route('productos.update', $product), $this->payload($category, [
            'nombre' => $product->nombre,
            'variantes' => [[
                'talla' => 'S, X, M, L',
                'color' => 'Blanco',
                'codigo_barra' => '',
                'stock' => 10,
                'stock_minimo' => 3,
            ]],
            'imagenes' => [[
                'archivo' => UploadedFile::fake()->image('blanco-compartido.webp', 800, 800),
                'talla' => '',
                'color' => 'Blanco',
            ]],
        ]))->assertSessionHas('success');

        $product->refresh()->load('variantes', 'imagenes');
        $this->assertSame(['L', 'M', 'S', 'X'], $product->variantes->pluck('talla')->sort()->values()->all());
        $this->assertSame([10], $product->variantes->pluck('stock')->unique()->values()->all());
        $this->assertSame(40, $product->variantes->sum('stock'));
        $this->assertDatabaseCount('stock_movimientos', 4);
        $this->assertCount(1, $product->imagenes);
        $this->assertNull($product->imagenes->first()->talla);
        $this->assertSame('Blanco', $product->imagenes->first()->color);
    }

    public function test_product_gallery_stores_five_images_with_variant_assignments(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);
        $images = collect(range(0, 4))->mapWithKeys(fn (int $index) => [$index => [
            'archivo' => UploadedFile::fake()->image("gallery-$index.webp", 800, 800),
            'talla' => $index === 0 ? '' : 'M',
            'color' => $index === 0 ? '' : 'Azul',
        ]])->all();

        $this->actingAs($administrator)->post(route('productos.store'), $this->payload($category, [
            'imagenes' => $images,
            'variantes' => [['talla' => 'M', 'color' => 'Azul', 'codigo_barra' => '', 'stock' => 5, 'stock_minimo' => 1]],
        ]))->assertSessionHas('success');

        $product = Producto::with('imagenes')->sole();
        $this->assertCount(5, $product->imagenes);
        $this->assertTrue($product->imagenes->first()->principal);
        $this->assertSame($product->imagenes->first()->ruta, $product->imagen);
        $this->assertSame('M', $product->imagenes->get(1)->talla);
        $this->assertSame('Azul', $product->imagenes->get(1)->color);
        foreach ($product->imagenes as $image) Storage::disk('public')->assertExists($image->ruta);
    }

    public function test_product_gallery_rejects_more_than_five_images(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);
        $images = collect(range(0, 5))->mapWithKeys(fn (int $index) => [$index => [
            'archivo' => UploadedFile::fake()->image("excess-$index.jpg"),
        ]])->all();

        $this->actingAs($administrator)->post(route('productos.store'), $this->payload($category, ['imagenes' => $images]))
            ->assertSessionHasErrors('imagenes');
        $this->assertDatabaseCount('productos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('productos'));
    }

    public function test_oversized_dimensions_are_rejected_without_orphan_files(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->payload($category, [
                'imagen' => UploadedFile::fake()->image('oversized.jpg', 6001, 10),
            ]))
            ->assertSessionHasErrors('imagen');

        $this->assertDatabaseCount('productos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('productos'));
    }

    public function test_stale_product_instances_do_not_leave_replaced_images_orphaned(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore();
        $category = $this->category($store);
        $product = $this->product($store, $category, 'productos/'.$store->id.'/original.jpg');
        Storage::disk('public')->put($product->imagen, 'original');
        $firstEditor = Producto::withoutGlobalScopes()->findOrFail($product->id);
        $staleSecondEditor = Producto::withoutGlobalScopes()->findOrFail($product->id);
        $this->actingAs($administrator);
        $manager = app(ProductoManager::class);

        $firstResult = $manager->update($firstEditor, ['nombre' => 'Primera edición'], $administrator, UploadedFile::fake()->image('first.jpg'));
        $secondResult = $manager->update($staleSecondEditor, ['nombre' => 'Segunda edición'], $administrator, UploadedFile::fake()->image('second.jpg'));

        Storage::disk('public')->assertMissing('productos/'.$store->id.'/original.jpg');
        Storage::disk('public')->assertMissing($firstResult->imagen);
        Storage::disk('public')->assertExists($secondResult->imagen);
        $this->assertSame([$secondResult->imagen], Storage::disk('public')->allFiles('productos/'.$store->id));
        $this->assertSame($secondResult->imagen, $product->fresh()->imagen);
    }

    public function test_storage_failure_throws_before_database_persistence(): void
    {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('putFile')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->once()->andReturn($filesystem);

        $this->expectException(RuntimeException::class);
        app(ProductImageStorage::class)->store(UploadedFile::fake()->image('failure.jpg'), 10);
    }

    public function test_cleanup_refuses_paths_outside_managed_product_directory(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/shared.png', 'shared');
        Storage::disk('public')->put('productos-safe/image.png', 'safe');
        $storage = app(ProductImageStorage::class);

        $this->assertFalse($storage->delete('logos/shared.png'));
        $this->assertFalse($storage->delete('productos/../logos/shared.png'));
        $this->assertFalse($storage->delete('productos-safe/image.png'));
        Storage::disk('public')->assertExists('logos/shared.png');
        Storage::disk('public')->assertExists('productos-safe/image.png');
    }

    private function payload(Categoria $category, array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Producto con imagen',
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

    private function userWithStore(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@product-images.test',
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

    private function product(Tienda $store, Categoria $category, string $image): Producto
    {
        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'PROD-'.Str::upper(Str::random(8)),
            'nombre' => 'Producto existente',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'imagen' => $image,
            'activo' => true,
        ]);
    }
}
