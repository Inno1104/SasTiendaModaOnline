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
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UsuarioStateAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivation_requires_reason_and_does_not_change_state_on_validation_error(): void
    {
        [$administrator, $store] = $this->context();
        $seller = $this->user($store, 'Vendedor');

        $this->actingAs($administrator)->post(route('usuarios.toggle', $seller), [
            'activo' => false,
            'motivo' => '   ',
        ])->assertSessionHasErrors('motivo');

        $this->assertTrue($seller->fresh()->activo);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_deactivation_revokes_remember_token_and_records_traceability(): void
    {
        [$administrator, $store] = $this->context();
        $seller = $this->user($store, 'Vendedor');
        $seller->forceFill(['remember_token' => 'known-token'])->save();

        $this->actingAs($administrator)->post(route('usuarios.toggle', $seller), [
            'activo' => false,
            'motivo' => 'Finalización de contrato',
        ])->assertSessionHas('success');

        $seller->refresh();
        $this->assertFalse($seller->activo);
        $this->assertNotSame('known-token', $seller->getRememberToken());

        $audit = Auditoria::query()->sole();
        $this->assertSame('usuario.desactivado', $audit->accion);
        $this->assertSame($administrator->id, $audit->user_id);
        $this->assertSame($seller->id, $audit->auditable_id);
        $this->assertTrue($audit->metadata['estado_anterior']);
        $this->assertFalse($audit->metadata['estado_nuevo']);
        $this->assertSame('Finalización de contrato', $audit->metadata['motivo']);
    }

    public function test_reactivation_is_explicit_audited_and_duplicate_request_is_rejected(): void
    {
        [$administrator, $store] = $this->context();
        $seller = $this->user($store, 'Vendedor', false);
        $payload = ['activo' => true, 'motivo' => 'Retorno autorizado al equipo'];

        $this->actingAs($administrator)->post(route('usuarios.toggle', $seller), $payload)
            ->assertSessionHas('success');
        $this->post(route('usuarios.toggle', $seller), $payload)
            ->assertSessionHasErrors('activo');

        $this->assertTrue($seller->fresh()->activo);
        $this->assertSame(1, Auditoria::query()->where('accion', 'usuario.activado')->count());
    }

    public function test_user_creation_and_meaningful_updates_are_audited_without_exposing_password(): void
    {
        [$administrator] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);

        $this->actingAs($administrator)->post(route('usuarios.store'), [
            'name' => 'Usuario auditado',
            'email' => 'audited@example.com',
            'rol_id' => $sellerRole->id,
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertSessionHas('success');

        $seller = User::query()->where('email', 'audited@example.com')->sole();
        $this->post(route('usuarios.update', $seller), [
            'name' => 'Usuario auditado actualizado',
            'email' => $seller->email,
            'rol_id' => $sellerRole->id,
            'password' => 'password2',
            'password_confirmation' => 'password2',
        ])->assertSessionHas('success');

        $this->assertSame(['usuario.creado', 'usuario.actualizado'], Auditoria::query()->orderBy('id')->pluck('accion')->all());
        $metadata = Auditoria::query()->where('accion', 'usuario.actualizado')->sole()->metadata;
        $this->assertTrue($metadata['contrasena_cambiada']);
        $this->assertStringNotContainsString('password2', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_status_change_rolls_back_when_audit_cannot_be_saved(): void
    {
        [$administrator, $store] = $this->context();
        $seller = $this->user($store, 'Vendedor');
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $manager = new UserAdministrationService($audit, app(UserPlanGuard::class));

        try {
            $manager->changeStatus($seller, false, 'Baja administrativa', $administrator);
            $this->fail('La falla de auditoría debía cancelar el cambio de estado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertTrue($seller->fresh()->activo);
        $this->assertDatabaseCount('auditorias', 0);
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

        return [$this->user($store, Rol::ADMINISTRATOR), $store];
    }

    private function user(Tienda $store, string $role, bool $active = true): User
    {
        $suffix = Str::lower(Str::random(8));

        return User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => $role])->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@user-state.test',
            'password' => 'password1',
            'activo' => $active,
            'es_super_admin' => false,
        ]);
    }
}
