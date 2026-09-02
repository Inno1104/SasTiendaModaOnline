<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreAuditTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_changes_create_specific_traceable_events_without_file_contents(): void
    {
        Storage::fake('public');
        [$admin, $store] = $this->context();
        $client = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->actingAs($admin);

        $client->post(route('tienda.update'), [
            'nombre' => 'Moda Auditada',
            'ruc' => '1790012345001',
            'email' => 'contacto@auditada.test',
            'pais_codigo' => 'EC',
            'provincia' => 'Pichincha',
            'ciudad' => 'Quito',
            'logo' => UploadedFile::fake()->image('logo.png', 500, 500),
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $client->post(route('tienda.update'), [
            'nombre' => $store->nombre,
            'ruc' => $store->ruc,
            'email' => $store->email,
            'eliminar_logo' => true,
        ])->assertSessionHasNoErrors();

        $client->post(route('configuracion.update'), [
            'igv' => '16.00',
            'precios_incluyen_igv' => false,
            'moneda' => 'EUR',
            'serie_boleta' => 'B010',
            'serie_factura' => 'F010',
            'serie_ticket' => 'T010',
            'configuracion_version' => 0,
            'motivo_cambio' => 'Actualización fiscal autorizada por gerencia',
            'confirmar_cambio' => true,
        ])->assertSessionHasNoErrors();

        $audits = Auditoria::query()->orderBy('id')->get()->keyBy('accion');
        $expected = [
            'tienda.identidad_actualizada',
            'tienda.ubicacion_actualizada',
            'tienda.logo_actualizado',
            'tienda.logo_eliminado',
            'tienda.configuracion_fiscal_actualizada',
            'tienda.series_actualizadas',
        ];
        $this->assertEqualsCanonicalizing($expected, $audits->keys()->all());

        foreach ($expected as $action) {
            $audit = $audits->get($action);
            $this->assertSame($admin->id, $audit->user_id);
            $this->assertSame($store->id, $audit->tienda_id);
            $this->assertSame('203.0.113.10', $audit->ip);
            $this->assertNotNull($audit->created_at);
            $this->assertArrayHasKey('cambios', $audit->metadata);
        }

        $this->assertSame('Moda Original', $audits['tienda.identidad_actualizada']->metadata['cambios']['nombre']['anterior']);
        $this->assertSame('Moda Auditada', $audits['tienda.identidad_actualizada']->metadata['cambios']['nombre']['nuevo']);
        $this->assertFalse($audits['tienda.logo_actualizado']->metadata['cambios']['logo']['anterior']);
        $this->assertTrue($audits['tienda.logo_actualizado']->metadata['cambios']['logo']['nuevo']);
        $this->assertTrue($audits['tienda.logo_eliminado']->metadata['cambios']['logo']['anterior']);
        $this->assertFalse($audits['tienda.logo_eliminado']->metadata['cambios']['logo']['nuevo']);
        $this->assertSame('Actualización fiscal autorizada por gerencia', $audits['tienda.configuracion_fiscal_actualizada']->metadata['motivo']);
        $this->assertSame('Actualización fiscal autorizada por gerencia', $audits['tienda.series_actualizadas']->metadata['motivo']);
        $this->assertStringNotContainsString('tiendas/', json_encode($audits['tienda.logo_actualizado']->metadata, JSON_THROW_ON_ERROR));
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Moda Original',
            'slug' => 'audit-'.$suffix,
            'estado' => 'activa',
            'igv' => 15,
            'precios_incluyen_igv' => false,
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'serie_boleta' => 'B001',
            'serie_factura' => 'F001',
            'serie_ticket' => 'T001',
        ]);
        $admin = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => $suffix.'@store-audit.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$admin, $store];
    }
}
