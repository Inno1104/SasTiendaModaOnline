<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SupplierManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProveedorAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_records_actor_store_entity_and_relevant_snapshot(): void
    {
        [$admin, $store] = $this->context();

        $this->actingAs($admin)->post(route('proveedores.store'), $this->payload('Proveedor auditado'))
            ->assertSessionHas('success');

        $supplier = Proveedor::query()->where('nombre', 'Proveedor auditado')->firstOrFail();
        $audit = Auditoria::query()->sole();
        $this->assertSame($store->id, $audit->tienda_id);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('proveedor.creado', $audit->accion);
        $this->assertSame(Proveedor::class, $audit->auditable_type);
        $this->assertSame($supplier->id, $audit->auditable_id);
        $this->assertSame('Proveedor auditado', $audit->metadata['nombre']);
        $this->assertSame('20512345678', $audit->metadata['ruc']);
        $this->assertTrue($audit->metadata['activo']);
    }

    public function test_real_update_records_only_meaningful_old_and_new_values(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store);
        $payload = $this->payload('Proveedor actualizado');
        $payload['telefono'] = '0987654321';

        $this->actingAs($admin)->put(route('proveedores.update', $supplier), $payload)
            ->assertSessionHas('success');

        $audit = Auditoria::query()->sole();
        $this->assertSame('proveedor.actualizado', $audit->accion);
        $this->assertSame('Proveedor principal', $audit->metadata['cambios']['nombre']['anterior']);
        $this->assertSame('Proveedor actualizado', $audit->metadata['cambios']['nombre']['nuevo']);
        $this->assertSame('0999999999', $audit->metadata['cambios']['telefono']['anterior']);
        $this->assertSame('0987654321', $audit->metadata['cambios']['telefono']['nuevo']);
        $this->assertArrayNotHasKey('updated_at', $audit->metadata['cambios']);
        $this->assertArrayNotHasKey('tienda_id', $audit->metadata['cambios']);
        $this->assertArrayNotHasKey('activo', $audit->metadata['cambios']);
    }

    public function test_state_changes_record_specific_actions_and_dependencies(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store);

        $this->actingAs($admin)->patch(route('proveedores.estado', $supplier), ['activo' => false]);
        $this->patch(route('proveedores.estado', $supplier), ['activo' => true]);

        $audits = Auditoria::query()->orderBy('id')->get();
        $this->assertSame(['proveedor.desactivado', 'proveedor.activado'], $audits->pluck('accion')->all());
        $this->assertTrue($audits[0]->metadata['cambios']['activo']['anterior']);
        $this->assertFalse($audits[0]->metadata['cambios']['activo']['nuevo']);
        $this->assertSame(0, $audits[0]->metadata['dependencias']['productos']);
        $this->assertSame(0, $audits[0]->metadata['dependencias']['compras']);
        $this->assertFalse($audits[1]->metadata['cambios']['activo']['anterior']);
        $this->assertTrue($audits[1]->metadata['cambios']['activo']['nuevo']);
    }

    public function test_no_op_update_and_repeated_state_do_not_create_audits(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store, false);

        $this->actingAs($admin)->put(route('proveedores.update', $supplier), $this->payload($supplier->nombre))
            ->assertSessionHas('success');
        $this->patch(route('proveedores.estado', $supplier), ['activo' => false])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_creation_rolls_back_when_audit_fails(): void
    {
        [$admin] = $this->context();
        $manager = $this->managerWithFailingAudit();

        try {
            $manager->create($this->payload('Proveedor fallido'), $admin);
            $this->fail('La falla de auditoría debía interrumpir la creación.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('proveedores', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_update_rolls_back_when_audit_fails(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store);
        $manager = $this->managerWithFailingAudit();

        try {
            $manager->update($supplier, $this->payload('Nombre no confirmado'), $admin);
            $this->fail('La falla de auditoría debía interrumpir la actualización.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertSame('Proveedor principal', $supplier->fresh()->nombre);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_state_change_rolls_back_when_audit_fails(): void
    {
        [$admin, $store] = $this->context();
        $supplier = $this->supplier($store);
        $manager = $this->managerWithFailingAudit();

        try {
            $manager->changeStatus($supplier, false, $admin);
            $this->fail('La falla de auditoría debía interrumpir el cambio de estado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertTrue($supplier->fresh()->activo);
        $this->assertDatabaseCount('auditorias', 0);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $admin = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@supplier-audit.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$admin, $store];
    }

    /** @return array<string, string> */
    private function payload(string $name): array
    {
        return [
            'nombre' => $name,
            'ruc' => '20512345678',
            'email' => 'ventas@example.test',
            'telefono' => '0999999999',
            'direccion' => 'Calle principal 123',
            'ciudad' => 'Quito',
            'pais' => 'Ecuador',
            'contacto' => 'Contacto principal',
        ];
    }

    private function supplier(Tienda $store, bool $active = true): Proveedor
    {
        return Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            ...$this->payload('Proveedor principal'),
            'activo' => $active,
        ]);
    }

    private function managerWithFailingAudit(): SupplierManager
    {
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));

        return new SupplierManager($audit);
    }
}
