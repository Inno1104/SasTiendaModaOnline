<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_publish_its_store_with_normalized_public_configuration(): void
    {
        [$admin, $store] = $this->context();

        $this->actingAs($admin)->post(route('tienda.storefront.update'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertTrue($store->publicada);
        $this->assertSame('#D91468', $store->color_primario);
        $this->assertSame('Moda responsable para cada ocasión.', $store->descripcion_publica);
        $this->assertSame('09:00', $store->horarios['lunes']['apertura']);
        $this->assertFalse($store->horarios['domingo']['activo']);
        $this->assertSame('Entregas en 24 a 48 horas.', $store->condiciones_venta['envios']);
        $this->assertSame('tienda.publicacion_actualizada', Auditoria::latest('id')->value('accion'));

        $this->get(route('storefront.show', $store->slug))
            ->assertOk()
            ->assertSee($store->nombre)
            ->assertSee('Moda responsable para cada ocasión.')
            ->assertSee('09:00 – 18:00')
            ->assertSee('Entregas en 24 a 48 horas.')
            ->assertSee('css/storefront.css', false);
    }

    public function test_unpublished_suspended_or_expired_stores_are_not_publicly_discoverable(): void
    {
        [, $store] = $this->context();
        $this->get(route('storefront.show', $store->slug))->assertNotFound();

        $store->forceFill(['publicada' => true, 'estado' => 'suspendida'])->save();
        $this->get(route('storefront.show', $store->slug))->assertNotFound();

        $store->forceFill(['estado' => 'activa', 'fecha_vencimiento' => now()->subDay()])->save();
        $this->get(route('storefront.show', $store->slug))->assertNotFound();
    }

    public function test_publication_rejects_invalid_color_schedule_and_unknown_condition_fields(): void
    {
        [$admin, $store] = $this->context();
        $payload = $this->payload();
        $payload['color_primario'] = 'javascript:red';
        $payload['horarios']['lunes']['cierre'] = '08:00';
        $payload['condiciones_venta']['campo_desconocido'] = 'No permitido';

        $this->actingAs($admin)->post(route('tienda.storefront.update'), $payload)
            ->assertSessionHasErrors(['color_primario', 'horarios.lunes.cierre', 'condiciones_venta']);

        $this->assertFalse($store->fresh()->publicada);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_only_an_active_store_administrator_can_change_publication(): void
    {
        [$admin, $store] = $this->context();
        $seller = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => 'Vendedor'])->id,
            'tienda_id' => $store->id,
            'name' => 'Vendedor',
            'email' => Str::random(8).'@seller.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        $this->actingAs($seller)->post(route('tienda.storefront.update'), $this->payload())->assertForbidden();

        $admin->forceFill(['activo' => false])->save();
        $this->actingAs($admin->fresh())->post(route('tienda.storefront.update'), $this->payload())->assertForbidden();
        $this->assertFalse($store->fresh()->publicada);
    }

    public function test_no_change_does_not_create_a_second_audit_and_slug_remains_stable(): void
    {
        [$admin, $store] = $this->context();
        $slug = $store->slug;

        $this->actingAs($admin)->post(route('tienda.storefront.update'), $this->payload())->assertSessionHas('success');
        $this->post(route('tienda.storefront.update'), $this->payload())->assertSessionHas('info');

        $this->assertSame(1, Auditoria::where('accion', 'tienda.publicacion_actualizada')->count());
        $this->assertSame($slug, $store->fresh()->slug);
    }

    public function test_schema_contains_storefront_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('tiendas', [
            'publicada', 'descripcion_publica', 'color_primario', 'horarios', 'condiciones_venta',
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
            'email' => 'contacto@'.$suffix.'.test',
            'telefono' => '+593991234567',
            'direccion' => 'Av. Principal 123',
            'pais_codigo' => 'EC',
            'provincia' => 'Pichincha',
            'ciudad' => 'Quito',
        ]);
        $admin = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => $suffix.'@storefront.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$admin, $store];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $schedule = [];
        foreach (['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'] as $day) {
            $active = $day !== 'domingo';
            $schedule[$day] = [
                'activo' => $active ? '1' : '0',
                'apertura' => $active ? '09:00' : null,
                'cierre' => $active ? '18:00' : null,
            ];
        }

        return [
            'publicada' => '1',
            'descripcion_publica' => '  Moda responsable   para cada ocasión. ',
            'color_primario' => '#d91468',
            'horarios' => $schedule,
            'condiciones_venta' => [
                'envios' => ' Entregas en 24 a 48 horas. ',
                'devoluciones' => 'Cambios dentro de 15 días.',
                'terminos' => null,
                'privacidad' => null,
            ],
        ];
    }
}
