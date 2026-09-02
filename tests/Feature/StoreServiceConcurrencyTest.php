<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StoreLocationNormalizer;
use App\Services\StoreProfileManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StoreServiceConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_normalizer_has_one_geographic_responsibility(): void
    {
        $normalized = app(StoreLocationNormalizer::class)->normalize([
            'pais_codigo' => ' ec ',
            'provincia' => '  Santo   Domingo ',
            'ciudad' => '  Santo Domingo  ',
            'nombre' => '  No debe cambiar  ',
        ]);

        $this->assertSame('EC', $normalized['pais_codigo']);
        $this->assertSame('Santo Domingo', $normalized['provincia']);
        $this->assertSame('Santo Domingo', $normalized['ciudad']);
        $this->assertSame('  No debe cambiar  ', $normalized['nombre']);
    }

    public function test_profile_manager_audits_real_changes_and_ignores_empty_updates(): void
    {
        [$admin, $store] = $this->context();
        $manager = app(StoreProfileManager::class);

        $this->assertTrue($manager->update($store, ['nombre' => 'Moda Renovada'], $admin));
        $audit = Auditoria::query()->sole();
        $this->assertSame('tienda.identidad_actualizada', $audit->accion);
        $this->assertSame('Moda Renovada', $audit->metadata['cambios']['nombre']['nuevo']);

        $this->assertFalse($manager->update($store->fresh(), ['nombre' => 'Moda Renovada'], $admin));
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_profile_and_new_logo_roll_back_together_when_audit_fails(): void
    {
        Storage::fake('public');
        [$admin, $store] = $this->context();
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);

        try {
            app(StoreProfileManager::class)->update($store, [
                'nombre' => 'No persistir',
                'logo' => UploadedFile::fake()->image('logo.png', 500, 500),
            ], $admin);
            $this->fail('La operación debía revertirse.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertSame('Moda Original', $store->fresh()->nombre);
        $this->assertNull($store->fresh()->logo);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_managers_declare_database_locks_and_settings_version_check(): void
    {
        $profile = file_get_contents(app_path('Services/StoreProfileManager.php'));
        $settings = file_get_contents(app_path('Services/StoreSettingsManager.php'));

        $this->assertStringContainsString('lockForUpdate()', $profile);
        $this->assertStringContainsString('DB::transaction', $profile);
        $this->assertStringContainsString('lockForUpdate()', $settings);
        $this->assertStringContainsString('configuracion_version', $settings);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Moda Original',
            'slug' => 'services-'.$suffix,
            'estado' => 'activa',
        ]);
        $admin = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => $suffix.'@store-services.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$admin, $store];
    }
}
