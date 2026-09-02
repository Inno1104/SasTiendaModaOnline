<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorePublicDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_public_data_and_social_networks_are_saved_as_structured_json(): void
    {
        [$administrator, $store] = $this->context();

        $this->actingAs($administrator)->post(route('tienda.update'), $this->payload() + [
            'sitio_web' => '  https://www.tiendamoda.example/catalogo  ',
            'redes_sociales' => [
                'instagram' => ' https://instagram.com/tiendamoda ',
                'facebook' => 'https://facebook.com/tiendamoda',
                'tiktok' => 'https://tiktok.com/@tiendamoda',
            ],
        ])->assertSessionHas('success')->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame('https://www.tiendamoda.example/catalogo', $store->sitio_web);
        $this->assertSame([
            'instagram' => 'https://instagram.com/tiendamoda',
            'facebook' => 'https://facebook.com/tiendamoda',
            'tiktok' => 'https://tiktok.com/@tiendamoda',
        ], $store->redes_sociales);

        $publicData = $store->publicContactData();
        $this->assertSame('EC', $publicData['ubicacion']['pais_codigo']);
        $this->assertSame('Ecuador', $publicData['ubicacion']['pais']);
        $this->assertSame($store->redes_sociales, $publicData['redes_sociales']);
    }

    public function test_unsafe_or_non_https_urls_and_unknown_social_keys_are_rejected(): void
    {
        [$administrator, $store] = $this->context();

        $this->actingAs($administrator)->post(route('tienda.update'), $this->payload() + [
            'sitio_web' => 'javascript:alert(1)',
            'redes_sociales' => [
                'instagram' => 'http://instagram.com/inseguro',
                'facebook' => 'data:text/html,malicioso',
                'otra' => 'https://example.test/no-admitida',
            ],
        ])->assertSessionHasErrors([
            'sitio_web', 'redes_sociales', 'redes_sociales.instagram', 'redes_sociales.facebook',
        ]);

        $store->refresh();
        $this->assertNull($store->sitio_web);
        $this->assertNull($store->redes_sociales);
    }

    public function test_empty_social_networks_are_stored_as_null(): void
    {
        [$administrator, $store] = $this->context();
        $store->forceFill(['redes_sociales' => ['instagram' => 'https://instagram.com/anterior']])->save();

        $this->actingAs($administrator)->post(route('tienda.update'), $this->payload() + [
            'redes_sociales' => ['instagram' => ' ', 'facebook' => '', 'tiktok' => null],
        ])->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertNull($store->fresh()->redes_sociales);
    }

    public function test_location_requires_country_then_province_before_city(): void
    {
        [$administrator, $store] = $this->context();
        $base = array_diff_key($this->payload(), array_flip(['pais_codigo', 'provincia', 'ciudad']));

        $this->actingAs($administrator)->post(route('tienda.update'), $base + [
            'ciudad' => 'Quito',
        ])->assertSessionHasErrors(['pais_codigo', 'provincia']);

        $this->post(route('tienda.update'), $base + [
            'pais_codigo' => 'EC',
            'ciudad' => 'Quito',
        ])->assertSessionHasErrors('provincia');

        $this->post(route('tienda.update'), $base + [
            'pais_codigo' => 'ZZ',
            'provincia' => 'Desconocida',
        ])->assertSessionHasErrors('pais_codigo');

        $this->assertNull($store->fresh()->pais_codigo);
    }

    public function test_schema_contains_public_data_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('tiendas', ['sitio_web', 'redes_sociales']));
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $administrator = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => $suffix.'@public-store.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$administrator, $store];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'nombre' => 'Tienda Moda',
            'ruc' => '1791234567001',
            'email' => 'contacto@moda.test',
            'telefono' => '+593991234567',
            'whatsapp' => '+593987654321',
            'direccion' => 'Av. Principal 123',
            'pais_codigo' => 'EC',
            'provincia' => 'Pichincha',
            'ciudad' => 'Quito',
        ];
    }
}
