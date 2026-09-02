<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserAdministrationService;
use App\Services\UserPlanGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UsuarioProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_tenant_audit_and_session_lifecycle_is_production_ready(): void
    {
        Rol::create(['nombre' => 'Rol anterior']);
        [$administrator, $store] = $this->context();
        [$foreignAdministrator] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);

        $this->assertNotSame(1, $administrator->rol_id);
        $this->assertTrue($administrator->isAdmin());

        $payload = [
            'name' => 'Usuario producción', 'email' => 'production-user@example.test',
            'rol_id' => $sellerRole->id, 'password' => 'password1',
            'password_confirmation' => 'password1',
        ];
        $this->actingAs($administrator)->post(route('usuarios.store'), $payload)->assertSessionHas('success');
        $seller = User::query()->where('email', 'production-user@example.test')->sole();

        $this->actingAs($foreignAdministrator)->get(route('usuarios.index', ['edit' => $seller->id]))->assertNotFound();
        $this->post(route('usuarios.toggle', $seller), [
            'activo' => false, 'motivo' => 'Intento desde otra tienda',
        ])->assertNotFound();

        $this->actingAs($administrator)->post(route('usuarios.toggle', $seller), [
            'activo' => false, 'motivo' => 'Baja confirmada para prueba de producción',
        ])->assertSessionHas('success');

        $this->assertSame(['usuario.creado', 'usuario.desactivado'], Auditoria::withoutGlobalScopes()
            ->where('tienda_id', $store->id)->orderBy('id')->pluck('accion')->all());
        $this->actingAs($seller->fresh())->get(route('dashboard'))->assertForbidden();
        $this->assertGuest();
    }

    public function test_user_and_audit_creation_roll_back_together_during_recovery_scenario(): void
    {
        [$administrator, $store] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $manager = new UserAdministrationService($audit, app(UserPlanGuard::class));

        try {
            $manager->create($store->id, [
                'name' => 'Usuario incompleto', 'email' => 'rollback-user@example.test',
                'rol_id' => $sellerRole->id, 'telefono' => null, 'password' => 'password1',
            ], $administrator);
            $this->fail('La falla de auditoría debía revertir el usuario.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback-user@example.test']);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_user_query_index_migration_is_reversible(): void
    {
        $path = database_path('migrations/2026_08_26_000048_optimize_user_management_queries.php');
        $this->assertFileExists($path);
        $migration = require $path;

        $migration->down();
        try {
            $indexes = collect(Schema::getIndexes('users'))->pluck('name');
            $this->assertNotContains('usuarios_tienda_rol_estado_nombre', $indexes);
            $this->assertNotContains('usuarios_tienda_fecha', $indexes);
        } finally {
            $migration->up();
        }

        $indexes = collect(Schema::getIndexes('users'))->pluck('name');
        $this->assertContains('usuarios_tienda_rol_estado_nombre', $indexes);
        $this->assertContains('usuarios_tienda_fecha', $indexes);
    }

    public function test_recovery_checklist_covers_backup_concurrency_sessions_and_restore(): void
    {
        $checklist = file_get_contents(base_path('docs/users-production-checklist.md'));

        $this->assertStringContainsString('MySqlUserPlanConcurrencyTest.php', $checklist);
        $this->assertStringContainsString('mysqldump --single-transaction', $checklist);
        $this->assertStringContainsString('mysql BASE_DATOS_RESTAURACION', $checklist);
        $this->assertStringContainsString('SHA-256', $checklist);
        $this->assertStringContainsString('SESSION_DRIVER', $checklist);
        $this->assertStringContainsString('último administrador activo', $checklist);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $administratorRole = Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR]);
        $administrator = User::create([
            'rol_id' => $administratorRole->id, 'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix, 'email' => $suffix.'@production-admin.test',
            'password' => 'password1', 'activo' => true, 'es_super_admin' => false,
        ]);

        return [$administrator, $store];
    }
}
