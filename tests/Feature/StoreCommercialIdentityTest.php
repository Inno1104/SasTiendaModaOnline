<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreCommercialIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_identity_is_normalized_without_changing_slug(): void
    {
        [$administrator, $store] = $this->context();
        $originalSlug = $store->slug;

        $this->actingAs($administrator)->post(route('tienda.update'), [
            'nombre' => '  Moda    Centro  ',
            'ruc' => ' ec 179.123-AB ',
            'email' => ' CONTACTO@MODA.TEST ',
            'telefono' => '+593 (99) 123-4567',
            'whatsapp' => '+593 98 765 4321',
            'direccion' => '  Av. Central   123  ',
            'pais_codigo' => ' ec ',
            'provincia' => '  Pichincha  ',
            'ciudad' => '  Distrito   Metropolitano de Quito ',
            'slug' => 'slug-manipulado',
        ])->assertSessionHas('success')->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame('Moda Centro', $store->nombre);
        $this->assertSame('EC179123-AB', $store->ruc);
        $this->assertSame('contacto@moda.test', $store->email);
        $this->assertSame('+593991234567', $store->telefono);
        $this->assertSame('+593987654321', $store->whatsapp);
        $this->assertSame('Av. Central 123', $store->direccion);
        $this->assertSame('EC', $store->pais_codigo);
        $this->assertSame('Pichincha', $store->provincia);
        $this->assertSame('Distrito Metropolitano de Quito', $store->ciudad);
        $this->assertSame($originalSlug, $store->slug);
    }

    public function test_invalid_formats_are_rejected_without_partial_changes(): void
    {
        [$administrator, $store] = $this->context();
        $originalName = $store->nombre;

        $this->actingAs($administrator)->post(route('tienda.update'), [
            'nombre' => 'X',
            'ruc' => 'INVÁLIDO/@',
            'email' => 'correo-no-valido',
            'telefono' => 'teléfono privado',
            'whatsapp' => '123',
            'pais_codigo' => 'ECU',
            'provincia' => str_repeat('a', 101),
            'ciudad' => str_repeat('b', 101),
        ])->assertSessionHasErrors([
            'nombre', 'ruc', 'email', 'telefono', 'whatsapp', 'pais_codigo', 'provincia', 'ciudad',
        ]);

        $store->refresh();
        $this->assertSame($originalName, $store->nombre);
        $this->assertNull($store->ruc);
        $this->assertNull($store->pais_codigo);
    }

    public function test_normalized_no_op_does_not_touch_updated_timestamp(): void
    {
        [$administrator, $store] = $this->context();
        $store->forceFill([
            'nombre' => 'Moda Centro',
            'ruc' => '1791234567001',
            'email' => 'contacto@moda.test',
            'telefono' => '+593991234567',
            'whatsapp' => '+593987654321',
            'direccion' => 'Av. Central 123',
            'pais_codigo' => 'EC',
            'provincia' => 'Pichincha',
            'ciudad' => 'Quito',
            'updated_at' => now()->subDay()->startOfSecond(),
        ])->saveQuietly();
        $previousUpdate = $store->fresh()->updated_at;

        $this->actingAs($administrator)->post(route('tienda.update'), [
            'nombre' => ' Moda   Centro ',
            'ruc' => '179.123.4567001',
            'email' => ' CONTACTO@MODA.TEST ',
            'telefono' => '+593 (99) 123-4567',
            'whatsapp' => '+593 (98) 765-4321',
            'direccion' => ' Av. Central   123 ',
            'pais_codigo' => 'ec',
            'provincia' => ' Pichincha ',
            'ciudad' => ' Quito ',
        ])->assertSessionHas('info')->assertSessionHasNoErrors();

        $this->assertTrue($store->fresh()->updated_at->equalTo($previousUpdate));
    }

    public function test_schema_contains_new_commercial_identity_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('tiendas', [
            'whatsapp', 'pais_codigo', 'provincia', 'ciudad',
        ]));
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);
        $administrator = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => $suffix.'@commercial-identity.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$administrator, $store];
    }
}
