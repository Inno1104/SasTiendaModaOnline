<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CategoryManager;
use App\Services\CategoryNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CategoryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_records_actor_store_entity_and_snapshot(): void
    {
        [$user, $store] = $this->context();

        $this->actingAs($user)
            ->post(route('categorias.store'), $this->payload(['nombre' => 'Ropa deportiva']))
            ->assertSessionHas('success');

        $category = Categoria::query()->where('nombre', 'Ropa deportiva')->firstOrFail();
        $audit = Auditoria::query()->sole();

        $this->assertSame($store->id, $audit->tienda_id);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('categoria.creada', $audit->accion);
        $this->assertSame(Categoria::class, $audit->auditable_type);
        $this->assertSame($category->id, $audit->auditable_id);
        $this->assertSame('Ropa deportiva', $audit->metadata['nombre']);
        $this->assertSame('ropa-deportiva', $audit->metadata['slug']);
    }

    public function test_real_update_records_only_meaningful_old_and_new_values(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store, ['nombre' => 'Camisas', 'slug' => 'camisas']);

        $this->actingAs($user)
            ->post(route('categorias.update', $category), $this->payload(['nombre' => 'Camisetas']))
            ->assertSessionHas('success');

        $audit = Auditoria::query()->sole();

        $this->assertSame('categoria.actualizada', $audit->accion);
        $this->assertSame('Camisas', $audit->metadata['cambios']['nombre']['anterior']);
        $this->assertSame('Camisetas', $audit->metadata['cambios']['nombre']['nuevo']);
        $this->assertSame('camisas', $audit->metadata['cambios']['slug']['anterior']);
        $this->assertSame('camisetas', $audit->metadata['cambios']['slug']['nuevo']);
        $this->assertArrayNotHasKey('nombre_normalizado', $audit->metadata['cambios']);
    }

    public function test_state_changes_have_specific_audit_actions(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);

        $this->actingAs($user)
            ->post(route('categorias.update', $category), $this->payload(['activo' => false]))
            ->assertSessionHas('success');

        $this->post(route('categorias.update', $category), $this->payload(['activo' => true]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('auditorias', [
            'auditable_id' => $category->id,
            'accion' => 'categoria.desactivada',
        ]);
        $this->assertDatabaseHas('auditorias', [
            'auditable_id' => $category->id,
            'accion' => 'categoria.activada',
        ]);
    }

    public function test_no_op_update_does_not_create_false_audit_event(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store, ['slug' => 'categoria-principal']);

        $this->actingAs($user)
            ->post(route('categorias.update', $category), $this->payload())
            ->assertSessionHas('success');

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rejected_state_change_and_delete_do_not_create_audit_events(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store);
        $this->product($store, $category);

        $this->actingAs($user)
            ->post(route('categorias.update', $category), $this->payload(['activo' => false]))
            ->assertSessionHasErrors('activo');

        $this->post(route('categorias.destroy', $category))
            ->assertSessionHasErrors('categoria');

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_successful_delete_preserves_audit_snapshot(): void
    {
        [$user, $store] = $this->context();
        $category = $this->category($store, ['nombre' => 'Liquidación', 'slug' => 'liquidacion']);

        $this->actingAs($user)
            ->post(route('categorias.destroy', $category))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('categorias', ['id' => $category->id]);
        $audit = Auditoria::withoutGlobalScopes()
            ->where('auditable_type', Categoria::class)
            ->where('auditable_id', $category->id)
            ->sole();
        $this->assertSame('categoria.eliminada', $audit->accion);
        $this->assertSame('Liquidación', $audit->metadata['nombre']);
        $this->assertSame('liquidacion', $audit->metadata['slug']);
    }

    public function test_category_creation_rolls_back_when_audit_cannot_be_persisted(): void
    {
        [$user] = $this->context();
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $manager = new CategoryManager(app(CategoryNameNormalizer::class), $audit);

        try {
            $manager->create($this->payload(), $user);
            $this->fail('The audit failure should interrupt category creation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('categorias', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    /** @return array{User, Tienda} */
    private function context(): array
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
            'email' => $suffix.'@category-audit.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    /** @param array<string, mixed> $overrides */
    private function category(Tienda $store, array $overrides = []): Categoria
    {
        $suffix = Str::lower(Str::random(8));

        return Categoria::withoutGlobalScopes()->create(array_replace([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría principal',
            'nombre_normalizado' => 'categoria principal',
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'descripcion' => null,
            'activo' => true,
        ], $overrides));
    }

    private function product(Tienda $store, Categoria $category): Producto
    {
        return Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'PROD-'.Str::upper(Str::random(8)),
            'nombre' => 'Producto dependiente',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => true,
            'destacado' => false,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Categoría principal',
            'genero' => 'unisex',
            'descripcion' => '',
            'activo' => true,
        ], $overrides);
    }
}
