<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use App\Services\ProductoManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductoSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_routes_require_authentication(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $category = $this->category($store);
        $product = $this->product($store, $category);

        $this->get(route('productos.index'))->assertRedirect(route('login'));
        $this->get(route('productos.show', $product))->assertRedirect(route('login'));
        $this->post(route('productos.store'), $this->validPayload($category))->assertRedirect(route('login'));
    }

    public function test_catalog_permissions_follow_existing_roles(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $warehouseUser = $this->user($store, 'Almacén');
        $seller = $this->user($store, 'Vendedor');
        $category = $this->category($store);

        $this->actingAs($administrator)->get(route('productos.create'))->assertOk();
        $this->actingAs($warehouseUser)->get(route('productos.create'))->assertOk();
        $this->actingAs($seller)->get(route('productos.index'))->assertOk();
        $this->post(route('productos.store'), $this->validPayload($category))->assertForbidden();
    }

    public function test_inactive_users_cannot_access_products(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        $administrator->update(['activo' => false]);

        $this->actingAs($administrator)->get(route('productos.index'))->assertForbidden();
    }

    public function test_creation_persists_only_validated_fields_and_forces_current_store(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore('Administrador');
        [, $foreignStore] = $this->userWithStore('Administrador');
        $category = $this->category($store);

        $response = $this->actingAs($administrator)->post(route('productos.store'), $this->validPayload($category, [
            'tienda_id' => $foreignStore->id,
            'codigo' => 'INJECTED',
            'subcategoria_id' => 999,
            'imagen' => UploadedFile::fake()->image('camisa.webp'),
        ]));

        $response->assertRedirect(route('productos.index'))->assertSessionHas('success');
        $product = Producto::withoutGlobalScopes()->where('nombre', 'Camisa segura')->firstOrFail();

        $this->assertSame($store->id, $product->tienda_id);
        $this->assertNotSame('INJECTED', $product->codigo);
        $this->assertNull($product->subcategoria_id);
        $this->assertNotNull($product->imagen);
        Storage::disk('public')->assertExists($product->imagen);
    }

    public function test_relations_from_another_store_are_rejected(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        [, $foreignStore] = $this->userWithStore('Administrador');
        $localCategory = $this->category($store, 'local');
        $foreignCategory = $this->category($foreignStore, 'foreign');

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->validPayload($localCategory, [
                'categoria_id' => $foreignCategory->id,
            ]))
            ->assertSessionHasErrors('categoria_id');

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_invalid_image_and_commercial_values_do_not_create_a_product(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore('Administrador');
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->validPayload($category, [
                'precio_venta' => -1,
                'imagen' => UploadedFile::fake()->create('malware.php', 10, 'application/x-php'),
            ]))
            ->assertSessionHasErrors(['precio_venta', 'imagen']);

        $this->assertDatabaseCount('productos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('productos'));
    }

    public function test_variants_are_validated_and_duplicate_combinations_are_rejected(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->validPayload($category, [
                'variantes' => [
                    ['talla' => 'M', 'color' => 'Negro', 'stock' => 5, 'stock_minimo' => 1],
                    ['talla' => ' m ', 'color' => ' negro ', 'stock' => 2, 'stock_minimo' => 1],
                    ['talla' => 'L', 'color' => 'Azul', 'stock' => -1, 'stock_minimo' => 1],
                ],
            ]))
            ->assertSessionHasErrors(['variantes.1.talla', 'variantes.2.stock']);

        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('producto_variantes', 0);
    }

    public function test_product_and_variants_are_created_atomically_for_the_same_store(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $category = $this->category($store);

        $this->actingAs($administrator)
            ->post(route('productos.store'), $this->validPayload($category, [
                'variantes' => [
                    ['talla' => 'S', 'detalle_talla' => 'Pecho 84-88 cm; cintura 70-74 cm', 'color' => 'Azul', 'codigo_barra' => 'BAR-1', 'stock' => 8, 'stock_minimo' => 2],
                    ['talla' => 'M', 'color' => 'Azul', 'codigo_barra' => '', 'stock' => 6, 'stock_minimo' => 2],
                ],
            ]))
            ->assertSessionHas('success');

        $product = Producto::firstOrFail();
        $this->assertCount(2, $product->variantes);
        $this->assertTrue($product->variantes->every(fn ($variant) => $variant->tienda_id === $store->id));
        $this->assertDatabaseHas('producto_variantes', ['producto_id' => $product->id, 'codigo_barra' => 'BAR-1']);
        $this->assertDatabaseHas('producto_variantes', ['producto_id' => $product->id, 'talla' => 'S', 'detalle_talla' => 'Pecho 84-88 cm; cintura 70-74 cm']);
        $movements = StockMovimiento::query()->orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertTrue($movements->every(fn ($movement) => $movement->tipo === StockMovimiento::TIPO_ENTRADA));
        $this->assertSame([8, 6], $movements->pluck('cantidad')->all());
        $this->assertTrue($movements->every(fn ($movement) => $movement->stock_anterior === 0));
        $this->assertTrue($movements->every(fn ($movement) => $movement->referencia_type === $product->getMorphClass()));
    }

    public function test_service_rolls_back_and_removes_image_when_database_write_fails(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore('Administrador');
        $this->actingAs($administrator);

        try {
            app(ProductoManager::class)->create(
                $this->servicePayload(999999),
                $administrator,
                UploadedFile::fake()->image('rollback.png')
            );
            $this->fail('La creación debió fallar por la categoría inexistente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            $this->assertDatabaseCount('productos', 0);
            $this->assertDatabaseCount('producto_variantes', 0);
            $this->assertSame([], Storage::disk('public')->allFiles('productos'));
        }
    }

    public function test_update_replaces_image_after_success_and_ignores_protected_fields(): void
    {
        Storage::fake('public');
        [$administrator, $store] = $this->userWithStore('Administrador');
        [, $foreignStore] = $this->userWithStore('Administrador');
        $category = $this->category($store);
        $product = $this->product($store, $category, ['imagen' => 'productos/old.jpg']);
        Storage::disk('public')->put($product->imagen, 'old-image');

        $this->actingAs($administrator)
            ->put(route('productos.update', $product), $this->validPayload($category, [
                'nombre' => 'Producto actualizado',
                'tienda_id' => $foreignStore->id,
                'codigo' => 'CHANGED',
                'imagen' => UploadedFile::fake()->image('new.jpg'),
            ]))
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertSame('Producto actualizado', $product->nombre);
        $this->assertSame($store->id, $product->tienda_id);
        $this->assertSame('PROD-EXISTING', $product->codigo);
        Storage::disk('public')->assertMissing('productos/old.jpg');
        Storage::disk('public')->assertExists($product->imagen);
    }

    public function test_route_binding_is_isolated_by_store(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        [, $foreignStore] = $this->userWithStore('Administrador');
        $foreignCategory = $this->category($foreignStore);
        $foreignProduct = $this->product($foreignStore, $foreignCategory);

        $this->actingAs($administrator)->get(route('productos.show', $foreignProduct))->assertNotFound();
        $this->put(route('productos.update', $foreignProduct), $this->validPayload($foreignCategory))->assertNotFound();
        $this->delete(route('productos.destroy', $foreignProduct))->assertNotFound();
    }

    private function validPayload(Categoria $category, array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Camisa segura',
            'categoria_id' => $category->id,
            'marca_id' => '',
            'proveedor_id' => '',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'descripcion' => 'Producto de prueba',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'precio_oferta' => '',
            'activo' => 1,
            'destacado' => 0,
            'variantes' => [],
        ], $overrides);
    }

    private function servicePayload(int $categoryId): array
    {
        return [
            'nombre' => 'Producto con fallo',
            'categoria_id' => $categoryId,
            'marca_id' => null,
            'proveedor_id' => null,
            'genero' => 'unisex',
            'tipo' => 'casual',
            'descripcion' => null,
            'precio_compra' => 10,
            'precio_venta' => 20,
            'precio_oferta' => null,
            'activo' => true,
            'destacado' => false,
            'variantes' => [
                ['talla' => 'M', 'color' => 'Azul', 'codigo_barra' => null, 'stock' => 1, 'stock_minimo' => 1],
            ],
        ];
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
        $roleModel = Rol::firstOrCreate(['nombre' => $role]);
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'rol_id' => $roleModel->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@products.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function category(Tienda $store, string $suffix = ''): Categoria
    {
        $suffix = $suffix !== '' ? $suffix : Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'slug' => 'categoria-'.$suffix.'-'.Str::lower(Str::random(5)),
            'genero' => 'unisex',
            'activo' => true,
        ]);
    }

    private function product(Tienda $store, Categoria $category, array $overrides = []): Producto
    {
        return Producto::withoutGlobalScopes()->create(array_replace([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'PROD-EXISTING',
            'nombre' => 'Producto existente',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'activo' => true,
            'destacado' => false,
        ], $overrides));
    }
}
