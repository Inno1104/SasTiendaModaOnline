<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BrandManager;
use App\Services\BrandNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BrandAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_records_actor_store_entity_and_snapshot(): void
    {
        [$user, $store] = $this->context();

        $this->actingAs($user)->post(route('marcas.store'), $this->payload('Ropa urbana'))
            ->assertSessionHas('success');

        $brand = Marca::query()->where('nombre', 'Ropa urbana')->firstOrFail();
        $audit = Auditoria::query()->sole();
        $this->assertSame($store->id, $audit->tienda_id);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('marca.creada', $audit->accion);
        $this->assertSame(Marca::class, $audit->auditable_type);
        $this->assertSame($brand->id, $audit->auditable_id);
        $this->assertSame('Ropa urbana', $audit->metadata['nombre']);
        $this->assertTrue($audit->metadata['activo']);
    }

    public function test_real_update_records_only_meaningful_old_and_new_values(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store, 'Nike');

        $this->actingAs($user)->post(route('marcas.update', $brand), $this->payload('Nike Sports'))
            ->assertSessionHas('success');

        $audit = Auditoria::query()->sole();
        $this->assertSame('marca.actualizada', $audit->accion);
        $this->assertSame('Nike', $audit->metadata['cambios']['nombre']['anterior']);
        $this->assertSame('Nike Sports', $audit->metadata['cambios']['nombre']['nuevo']);
        $this->assertArrayNotHasKey('nombre_normalizado', $audit->metadata['cambios']);
    }

    public function test_state_changes_have_specific_audit_actions(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store, 'Adidas');

        $this->actingAs($user)->post(route('marcas.update', $brand), $this->payload('Adidas', false))
            ->assertSessionHas('success');
        $this->post(route('marcas.update', $brand), $this->payload('Adidas', true))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('auditorias', [
            'auditable_id' => $brand->id,
            'accion' => 'marca.desactivada',
        ]);
        $this->assertDatabaseHas('auditorias', [
            'auditable_id' => $brand->id,
            'accion' => 'marca.activada',
        ]);
    }

    public function test_no_op_update_does_not_create_false_audit_event(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store, 'Puma');

        $this->actingAs($user)->post(route('marcas.update', $brand), $this->payload(' Puma '))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rejected_delete_does_not_create_audit_event(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store, 'Levi’s');
        $this->product($store, $brand);

        $this->actingAs($user)->post(route('marcas.destroy', $brand))
            ->assertSessionHasErrors('marca');

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_successful_delete_preserves_audit_snapshot(): void
    {
        [$user, $store] = $this->context();
        $brand = $this->brand($store, 'Marca temporal', false);

        $this->actingAs($user)->post(route('marcas.destroy', $brand))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('marcas', ['id' => $brand->id]);
        $audit = Auditoria::withoutGlobalScopes()
            ->where('auditable_type', Marca::class)
            ->where('auditable_id', $brand->id)
            ->sole();
        $this->assertSame('marca.eliminada', $audit->accion);
        $this->assertSame('Marca temporal', $audit->metadata['nombre']);
        $this->assertFalse($audit->metadata['activo']);
    }

    public function test_creation_rolls_back_when_audit_cannot_be_persisted(): void
    {
        [$user] = $this->context();
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $manager = new BrandManager(app(BrandNameNormalizer::class), $audit);

        try {
            $manager->create(['nombre' => 'Marca fallida', 'activo' => true], $user);
            $this->fail('The audit failure should interrupt brand creation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('marcas', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_new_store_initial_brands_are_audited_with_its_administrator(): void
    {
        $suffix = Str::lower(Str::random(10));

        $this->post(route('registro.store'), [
            'tienda' => 'Tienda '.$suffix,
            'name' => 'Propietario',
            'email' => $suffix.'@brand-audit-registration.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'plan_id' => '',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', $suffix.'@brand-audit-registration.test')->firstOrFail();
        $this->assertSame(4, Auditoria::query()->where('accion', 'marca.creada')->count());
        $this->assertSame([$user->id], Auditoria::query()->distinct()->pluck('user_id')->all());
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
            'email' => $suffix.'@brand-audit.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function payload(string $name, bool $active = true): array
    {
        return ['nombre' => $name, 'activo' => $active];
    }

    private function brand(Tienda $store, string $name, bool $active = true): Marca
    {
        return Marca::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => Str::lower(Str::squish($name)),
            'activo' => $active,
        ]);
    }

    private function product(Tienda $store, Marca $brand): Producto
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
            'marca_id' => $brand->id,
            'codigo' => 'PROD-'.Str::upper($suffix),
            'nombre' => 'Producto dependiente',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => true,
            'destacado' => false,
        ]);
    }
}
