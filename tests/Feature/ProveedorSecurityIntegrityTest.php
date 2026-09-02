<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProveedorSecurityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_routes_require_authentication(): void
    {
        [, $store] = $this->context();
        $supplier = $this->supplier($store);

        $this->get(route('proveedores.index'))->assertRedirect(route('login'));
        $this->get(route('proveedores.create'))->assertRedirect(route('login'));
        $this->post(route('proveedores.store'), $this->payload())->assertRedirect(route('login'));
        $this->get(route('proveedores.edit', $supplier))->assertRedirect(route('login'));
        $this->put(route('proveedores.update', $supplier), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('proveedores.destroy', $supplier))->assertRedirect(route('login'));
    }

    public function test_active_store_users_can_view_but_only_catalog_managers_can_mutate(): void
    {
        [$seller, $store] = $this->context('Vendedor');
        $supplier = $this->supplier($store);

        $this->actingAs($seller)->get(route('proveedores.index'))->assertOk();
        $this->get(route('proveedores.create'))->assertForbidden();
        $this->post(route('proveedores.store'), $this->payload())->assertForbidden();
        $this->get(route('proveedores.edit', $supplier))->assertForbidden();
        $this->put(route('proveedores.update', $supplier), $this->payload())->assertForbidden();
        $this->delete(route('proveedores.destroy', $supplier))->assertForbidden();

        [$warehouse] = $this->context('Almacén', $store);
        $this->actingAs($warehouse)->post(route('proveedores.store'), $this->payload('Proveedor almacén'))
            ->assertRedirect(route('proveedores.index'));
        $this->assertDatabaseHas('proveedores', [
            'tienda_id' => $store->id,
            'nombre' => 'Proveedor almacén',
        ]);
    }

    public function test_store_forces_current_store_and_ignores_unvalidated_fields(): void
    {
        [$admin, $store] = $this->context();
        [, $foreignStore] = $this->context();

        $this->actingAs($admin)->post(route('proveedores.store'), [
            ...$this->payload('  Proveedor seguro  '),
            'tienda_id' => $foreignStore->id,
            'activo' => false,
            'campo_interno' => 'no permitido',
        ])->assertRedirect(route('proveedores.index'));

        $this->assertDatabaseHas('proveedores', [
            'tienda_id' => $store->id,
            'nombre' => 'Proveedor seguro',
            'activo' => true,
        ]);
        $this->assertDatabaseMissing('proveedores', [
            'tienda_id' => $foreignStore->id,
            'nombre' => 'Proveedor seguro',
        ]);
    }

    public function test_update_cannot_change_store_or_state_through_mass_assignment(): void
    {
        [$admin, $store] = $this->context();
        [, $foreignStore] = $this->context();
        $supplier = $this->supplier($store);

        $this->actingAs($admin)->put(route('proveedores.update', $supplier), [
            ...$this->payload('Proveedor actualizado'),
            'tienda_id' => $foreignStore->id,
            'activo' => false,
        ])->assertRedirect(route('proveedores.index'));

        $supplier->refresh();
        $this->assertSame($store->id, $supplier->tienda_id);
        $this->assertTrue((bool) $supplier->activo);
        $this->assertSame('Proveedor actualizado', $supplier->nombre);
    }

    public function test_route_binding_is_isolated_by_store_for_sensitive_operations(): void
    {
        [$admin] = $this->context();
        [, $foreignStore] = $this->context();
        $foreignSupplier = $this->supplier($foreignStore, 'Proveedor ajeno');

        $this->actingAs($admin)->get(route('proveedores.edit', $foreignSupplier))->assertNotFound();
        $this->put(route('proveedores.update', $foreignSupplier), $this->payload())->assertNotFound();
        $this->delete(route('proveedores.destroy', $foreignSupplier))->assertNotFound();

        $this->assertDatabaseHas('proveedores', [
            'id' => $foreignSupplier->id,
            'tienda_id' => $foreignStore->id,
            'activo' => true,
        ]);
    }

    public function test_supplier_fields_are_validated_and_normalized(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->post(route('proveedores.store'), [
            'nombre' => '   ',
            'email' => 'correo-invalido',
            'telefono' => str_repeat('1', 31),
            'direccion' => str_repeat('a', 256),
        ])->assertSessionHasErrors(['nombre', 'email', 'telefono', 'direccion']);

        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @return array{User, Tienda} */
    private function context(string $roleName = 'Administrador', ?Tienda $store = null): array
    {
        $suffix = Str::lower(Str::random(10));
        $store ??= Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);
        $role = Rol::firstOrCreate(['nombre' => $roleName]);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => $roleName.' '.$suffix,
            'email' => $suffix.'@supplier-security.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    /** @return array<string, string> */
    private function payload(string $name = 'Proveedor principal'): array
    {
        return [
            'nombre' => $name,
            'ruc' => 'RUC-'.Str::upper(Str::random(8)),
            'email' => 'contacto@example.test',
            'telefono' => '0999999999',
            'direccion' => 'Calle principal 123',
            'ciudad' => 'Quito',
            'pais' => 'Ecuador',
            'contacto' => 'Contacto principal',
        ];
    }

    private function supplier(Tienda $store, string $name = 'Proveedor existente'): Proveedor
    {
        return Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'activo' => true,
        ]);
    }
}
