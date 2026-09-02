<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\ClienteCreator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteIdentificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_numbers_are_sequential_and_independent_per_store(): void
    {
        [$firstAdmin, $firstStore] = $this->userWithStore();
        [$secondAdmin, $secondStore] = $this->userWithStore();

        $this->actingAs($firstAdmin)->post(route('clientes.store'), $this->payload('first'))->assertSessionHas('success');
        $this->post(route('clientes.store'), $this->payload('second'))->assertSessionHas('success');
        $this->actingAs($secondAdmin)->post(route('clientes.store'), $this->payload('third'))->assertSessionHas('success');

        $this->assertSame(
            ['CLI-00001', 'CLI-00002'],
            Cliente::withoutGlobalScopes()->where('tienda_id', $firstStore->id)->orderBy('id')->pluck('codigo')->all()
        );
        $this->assertSame(
            ['CLI-00001'],
            Cliente::withoutGlobalScopes()->where('tienda_id', $secondStore->id)->pluck('codigo')->all()
        );
    }

    public function test_sequence_continues_after_deactivation_and_preexisting_numbers(): void
    {
        [$administrator, $store] = $this->userWithStore();
        $existing = $this->customer($store, 'CLI-00009', 'existing');
        $existing->update(['activo' => false]);

        $this->actingAs($administrator)->post(route('clientes.store'), $this->payload('new'))->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'tienda_id' => $store->id,
            'codigo' => 'CLI-00010',
            'activo' => true,
        ]);
        $this->assertDatabaseHas('cliente_contadores', [
            'tienda_id' => $store->id,
            'ultimo_numero' => 10,
        ]);
    }

    public function test_document_and_email_are_unique_inside_each_store(): void
    {
        [$administrator, $store] = $this->userWithStore();
        $this->customer($store, 'CLI-EXISTING', 'existing', [
            'dni' => 'DOC-UNIQUE',
            'email' => 'unique@example.com',
        ]);

        $this->actingAs($administrator)->post(route('clientes.store'), $this->payload('duplicate', [
            'dni' => 'DOC-UNIQUE',
            'email' => 'unique@example.com',
        ]))->assertSessionHasErrors(['dni', 'email']);

        $this->assertSame(1, Cliente::withoutGlobalScopes()->where('tienda_id', $store->id)->count());
    }

    public function test_same_document_email_and_number_are_allowed_in_different_stores(): void
    {
        [$firstAdmin, $firstStore] = $this->userWithStore();
        [$secondAdmin, $secondStore] = $this->userWithStore();
        $sharedIdentity = ['dni' => 'DOC-SHARED', 'email' => 'shared@example.com'];

        $this->actingAs($firstAdmin)->post(route('clientes.store'), $this->payload('first', $sharedIdentity))->assertSessionHas('success');
        $this->actingAs($secondAdmin)->post(route('clientes.store'), $this->payload('second', $sharedIdentity))->assertSessionHas('success');

        $customers = Cliente::withoutGlobalScopes()->where('dni', 'DOC-SHARED')->orderBy('tienda_id')->get();
        $this->assertCount(2, $customers);
        $this->assertEqualsCanonicalizing([$firstStore->id, $secondStore->id], $customers->pluck('tienda_id')->all());
        $this->assertSame(['CLI-00001'], $customers->where('tienda_id', $firstStore->id)->pluck('codigo')->all());
        $this->assertSame(['CLI-00001'], $customers->where('tienda_id', $secondStore->id)->pluck('codigo')->all());
    }

    public function test_database_constraints_backstop_same_store_duplicates(): void
    {
        [, $store] = $this->userWithStore();
        $this->customer($store, 'CLI-DATABASE-1', 'first', ['dni' => 'DB-DOC']);

        $this->expectException(QueryException::class);
        $this->customer($store, 'CLI-DATABASE-2', 'second', ['dni' => 'DB-DOC']);
    }

    public function test_pos_quick_creation_uses_the_same_sequence_and_scoped_validation(): void
    {
        [$administrator, $store] = $this->userWithStore();
        app(ClienteCreator::class)->create($this->payload('regular'), $store->id);

        $this->actingAs($administrator)->postJson(route('ventas.clientes.store'), [
            'nombre' => '  Cliente ',
            'apellido' => ' POS ',
            'dni' => ' POS-200 ',
            'email' => ' POS@EXAMPLE.COM ',
        ])->assertCreated()->assertJsonPath('data.codigo', 'CLI-00002');

        $this->assertDatabaseHas('clientes', [
            'tienda_id' => $store->id,
            'codigo' => 'CLI-00002',
            'nombre' => 'Cliente',
            'apellido' => 'POS',
            'dni' => 'POS-200',
            'email' => 'pos@example.com',
        ]);
    }

    private function payload(string $suffix, array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Cliente',
            'apellido' => ucfirst($suffix),
            'dni' => 'DOC-'.$suffix,
            'email' => $suffix.'@example.com',
        ], $overrides);
    }

    private function userWithStore(): array
    {
        $suffix = uniqid();
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@identification.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function customer(Tienda $store, string $code, string $suffix, array $overrides = []): Cliente
    {
        return Cliente::withoutGlobalScopes()->create(array_replace([
            'tienda_id' => $store->id,
            'codigo' => $code,
            'nombre' => 'Cliente',
            'apellido' => ucfirst($suffix),
            'dni' => 'LEGACY-'.$suffix,
            'email' => 'legacy-'.$suffix.'@example.com',
            'activo' => true,
        ], $overrides));
    }
}
