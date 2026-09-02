<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StoreSettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StoreFiscalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_normalizes_series_and_calculates_currency_symbol(): void
    {
        [$admin, $store] = $this->context();

        $this->actingAs($admin)->post(route('configuracion.update'), $this->payload([
            'moneda' => ' eur ',
            'simbolo_moneda' => '<script>manipulado</script>',
            'serie_boleta' => ' b-002 ',
            'serie_factura' => ' f-002 ',
            'serie_ticket' => ' t-002 ',
            'motivo_cambio' => 'Cambio autorizado de moneda y series',
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $store->refresh();
        $this->assertSame('EUR', $store->moneda);
        $this->assertSame('€', $store->simbolo_moneda);
        $this->assertSame('B-002', $store->serie_boleta);
        $this->assertSame('F-002', $store->serie_factura);
        $this->assertSame('T-002', $store->serie_ticket);
        $this->assertSame(1, $store->configuracion_version);
    }

    public function test_currency_tax_and_series_changes_require_reason_and_confirmation(): void
    {
        [$admin, $store] = $this->context();

        $this->actingAs($admin)->post(route('configuracion.update'), $this->payload([
            'igv' => '16.00',
            'motivo_cambio' => '',
            'confirmar_cambio' => false,
        ]))->assertSessionHasErrors(['motivo_cambio', 'confirmar_cambio']);

        $this->assertSame('15.00', $store->fresh()->igv);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_tax_rejects_more_than_two_decimals_and_unknown_currency(): void
    {
        [$admin, $store] = $this->context();

        $this->actingAs($admin)->post(route('configuracion.update'), $this->payload([
            'igv' => '15.999',
            'moneda' => 'BTC',
        ]))->assertSessionHasErrors(['igv', 'moneda']);

        $this->assertSame('USD', $store->fresh()->moneda);
    }

    public function test_stale_form_cannot_overwrite_a_newer_configuration(): void
    {
        [$admin, $store] = $this->context();
        $first = $this->payload([
            'igv' => '16.00',
            'motivo_cambio' => 'Primera actualización fiscal válida',
        ]);
        $this->actingAs($admin)->post(route('configuracion.update'), $first)->assertSessionHasNoErrors();

        $this->post(route('configuracion.update'), $this->payload([
            'igv' => '17.00',
            'configuracion_version' => 0,
            'motivo_cambio' => 'Formulario antiguo que no debe sobrescribir',
        ]))->assertSessionHasErrors('configuracion_version');

        $store->refresh();
        $this->assertSame('16.00', $store->igv);
        $this->assertSame(1, $store->configuracion_version);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_real_change_is_audited_with_reason_and_no_op_does_not_touch_version(): void
    {
        [$admin, $store] = $this->context();
        $this->actingAs($admin)->post(route('configuracion.update'), $this->payload([
            'precios_incluyen_igv' => true,
            'motivo_cambio' => '',
            'confirmar_cambio' => false,
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $audit = Auditoria::query()->sole();
        $this->assertSame('tienda.configuracion_fiscal_actualizada', $audit->accion);
        $this->assertNull($audit->metadata['motivo']);

        $this->post(route('configuracion.update'), $this->payload([
            'precios_incluyen_igv' => true,
            'configuracion_version' => 1,
            'motivo_cambio' => '',
            'confirmar_cambio' => false,
        ]))->assertSessionHasNoErrors()->assertSessionHas('info');
        $this->assertSame(1, $store->fresh()->configuracion_version);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_view_uses_centralized_options_and_external_confirmation_assets(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->get(route('configuracion.index'))
            ->assertOk()
            ->assertSee('USD — Dólar estadounidense')
            ->assertSee('store-settings.css')
            ->assertSee('store-settings.js')
            ->assertDontSee('name="simbolo_moneda"', false);

        $this->assertTrue(Schema::hasColumn('tiendas', 'configuracion_version'));
    }

    public function test_settings_and_version_roll_back_when_audit_fails(): void
    {
        [$admin, $store] = $this->context();
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);

        try {
            app(StoreSettingsManager::class)->update($store, $this->payload([
                'igv' => '16.00',
                'motivo_cambio' => 'Cambio que debe revertirse por auditoría',
            ]), $admin);
            $this->fail('La falla de auditoría debía cancelar la transacción.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $store->refresh();
        $this->assertSame('15.00', $store->igv);
        $this->assertSame(0, $store->configuracion_version);
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'igv' => '15.00',
            'precios_incluyen_igv' => false,
            'moneda' => 'USD',
            'serie_boleta' => 'B001',
            'serie_factura' => 'F001',
            'serie_ticket' => 'T001',
            'configuracion_version' => 0,
            'motivo_cambio' => 'Cambio fiscal autorizado para pruebas',
            'confirmar_cambio' => true,
        ], $overrides);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
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
            'email' => $suffix.'@fiscal-settings.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$admin, $store];
    }
}
