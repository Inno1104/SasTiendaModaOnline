<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProveedorIdentityValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_identity_and_contact_data_are_normalized(): void
    {
        [$admin, $store] = $this->context();

        $this->actingAs($admin)->post(route('proveedores.store'), [
            'nombre' => '  Textiles   del   Pacífico  ',
            'ruc' => ' 20-512.345 678 ',
            'email' => '  VENTAS@Proveedor.COM ',
            'telefono' => ' +51 (01) 456-7890 ',
            'direccion' => ' Av.   Industrial   123 ',
            'ciudad' => '  Lima   Metropolitana ',
            'pais' => '  Perú ',
            'contacto' => '  Ana   Torres ',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('proveedores', [
            'tienda_id' => $store->id,
            'nombre' => 'Textiles del Pacífico',
            'ruc' => '20512345678',
            'email' => 'ventas@proveedor.com',
            'telefono' => '+51014567890',
            'direccion' => 'Av. Industrial 123',
            'ciudad' => 'Lima Metropolitana',
            'pais' => 'Perú',
            'contacto' => 'Ana Torres',
        ]);
    }

    public function test_equivalent_tax_ids_are_rejected_inside_the_same_store(): void
    {
        [$admin, $store] = $this->context();
        $existing = $this->supplier($store, 'Proveedor uno', '20512345678');
        $editable = $this->supplier($store, 'Proveedor dos', '20587654321');

        $this->actingAs($admin)->post(route('proveedores.store'), $this->payload('Proveedor repetido', '20-512.345-678'))
            ->assertSessionHasErrors('ruc');
        $this->put(route('proveedores.update', $editable), $this->payload('Proveedor editado', '20 512 345 678'))
            ->assertSessionHasErrors('ruc');

        $this->assertSame('20587654321', $editable->fresh()->ruc);
        $this->assertSame(2, Proveedor::withoutGlobalScopes()->where('tienda_id', $store->id)->count());
        $this->assertSame('20512345678', $existing->fresh()->ruc);
    }

    public function test_two_stores_can_register_the_same_tax_id(): void
    {
        [$firstAdmin, $firstStore] = $this->context();
        [$secondAdmin, $secondStore] = $this->context();

        $this->actingAs($firstAdmin)->post(route('proveedores.store'), $this->payload('Proveedor norte', '20512345678'))
            ->assertSessionHasNoErrors();
        $this->actingAs($secondAdmin)->post(route('proveedores.store'), $this->payload('Proveedor sur', '20-512-345-678'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('proveedores', ['tienda_id' => $firstStore->id, 'ruc' => '20512345678']);
        $this->assertDatabaseHas('proveedores', ['tienda_id' => $secondStore->id, 'ruc' => '20512345678']);
    }

    public function test_multiple_suppliers_without_tax_id_are_allowed(): void
    {
        [$admin, $store] = $this->context();

        $this->actingAs($admin)->post(route('proveedores.store'), $this->payload('Proveedor sin RUC uno', ''))
            ->assertSessionHasNoErrors();
        $this->post(route('proveedores.store'), $this->payload('Proveedor sin RUC dos', '   '))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Proveedor::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->whereNull('ruc')
            ->count());
    }

    public function test_database_enforces_store_scoped_tax_identity(): void
    {
        [, $store] = $this->context();
        $this->supplier($store, 'Proveedor original', '20512345678');

        $this->expectException(QueryException::class);
        $this->supplier($store, 'Proveedor duplicado', '20512345678');
    }

    public function test_invalid_formats_and_lengths_are_rejected_without_writes(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->post(route('proveedores.store'), [
            ...$this->payload(str_repeat('N', 201), '1234'),
            'email' => 'correo-invalido',
            'telefono' => '+12345',
            'ciudad' => str_repeat('C', 101),
            'pais' => str_repeat('P', 101),
            'contacto' => str_repeat('A', 151),
        ])->assertSessionHasErrors(['nombre', 'ruc', 'email', 'telefono', 'ciudad', 'pais', 'contacto']);

        $this->assertDatabaseCount('proveedores', 0);
    }

    public function test_supplier_schema_contains_the_store_scoped_unique_index(): void
    {
        $this->assertTrue(Schema::hasColumns('proveedores', [
            'tienda_id', 'nombre', 'ruc', 'email', 'telefono', 'direccion', 'ciudad', 'pais', 'contacto',
        ]));

        $indexes = collect(Schema::getIndexes('proveedores'))->pluck('name');
        $this->assertContains('proveedores_tienda_ruc_unique', $indexes);
        $this->assertNotContains('proveedores_ruc_unique', $indexes);
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
        $admin = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@supplier-identity.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$admin, $store];
    }

    /** @return array<string, string> */
    private function payload(string $name, string $taxId): array
    {
        return [
            'nombre' => $name,
            'ruc' => $taxId,
            'email' => 'ventas@example.test',
            'telefono' => '0999999999',
            'direccion' => 'Calle principal 123',
            'ciudad' => 'Quito',
            'pais' => 'Ecuador',
            'contacto' => 'Contacto principal',
        ];
    }

    private function supplier(Tienda $store, string $name, ?string $taxId): Proveedor
    {
        return Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'ruc' => $taxId,
            'activo' => true,
        ]);
    }
}
