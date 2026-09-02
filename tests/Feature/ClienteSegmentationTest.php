<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteSegmentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_validated_before_querying(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)->get(route('clientes.index', [
            'estado' => 'invalid',
            'actividad' => 'invalid',
            'orden' => 'invalid',
            'registrado_desde' => '2026-08-20',
            'registrado_hasta' => '2026-08-10',
            'ultima_compra_desde' => '2026-08-20',
            'ultima_compra_hasta' => '2026-08-10',
        ]))->assertSessionHasErrors(['estado', 'actividad', 'orden', 'registrado_hasta', 'ultima_compra_hasta']);
    }

    public function test_search_matches_code_name_document_email_and_phone(): void
    {
        [$user, $store] = $this->context();
        $target = $this->customer($store, 'TARGET', ['codigo' => 'CLI-SPECIAL', 'dni' => 'DOC-7788', 'telefono' => '0991234567']);
        $other = $this->customer($store, 'OTHER');

        foreach (['CLI-SPECIAL', 'TARGET', 'DOC-7788', $target->email, '0991234567'] as $term) {
            $this->actingAs($user)->get(route('clientes.index', ['buscar' => $term]))
                ->assertOk()->assertSee($target->nombre)->assertDontSee($other->nombre);
        }
    }

    public function test_status_city_and_activity_filters_can_be_combined(): void
    {
        [$user, $store] = $this->context();
        $matching = $this->customer($store, 'MATCHING', ['ciudad' => 'Quito', 'activo' => true]);
        $inactive = $this->customer($store, 'INACTIVE', ['ciudad' => 'Quito', 'activo' => false]);
        $withoutPurchases = $this->customer($store, 'WITHOUT', ['ciudad' => 'Quito']);
        $otherCity = $this->customer($store, 'CUENCA', ['ciudad' => 'Cuenca']);
        $this->sale($store, $user, $matching, 'MATCHING', 50, 'completada');
        $this->sale($store, $user, $inactive, 'INACTIVE', 50, 'completada');
        $this->sale($store, $user, $otherCity, 'CUENCA', 50, 'completada');
        $this->sale($store, $user, $withoutPurchases, 'CANCELLED', 50, 'anulada');

        $this->actingAs($user)->get(route('clientes.index', [
            'estado' => 'activo',
            'ciudad' => 'Quito',
            'actividad' => 'con_compras',
        ]))->assertOk()
            ->assertSee($matching->nombre)
            ->assertDontSee($inactive->nombre)
            ->assertDontSee($withoutPurchases->nombre)
            ->assertDontSee($otherCity->nombre);
    }

    public function test_registration_and_last_completed_purchase_ranges_are_exact(): void
    {
        [$user, $store] = $this->context();
        $matching = $this->customer($store, 'MATCHING-DATE');
        $tooRecent = $this->customer($store, 'TOO-RECENT');
        $cancelledOnly = $this->customer($store, 'CANCELLED-ONLY');
        $matching->forceFill(['created_at' => '2026-08-10 10:00:00'])->saveQuietly();
        $tooRecent->forceFill(['created_at' => '2026-08-10 10:00:00'])->saveQuietly();
        $cancelledOnly->forceFill(['created_at' => '2026-08-10 10:00:00'])->saveQuietly();
        $this->sale($store, $user, $matching, 'IN-RANGE', 30, 'completada', '2026-08-15 10:00:00');
        $this->sale($store, $user, $matching, 'OLDER', 20, 'completada', '2026-08-05 10:00:00');
        $this->sale($store, $user, $tooRecent, 'AFTER-RANGE', 40, 'completada', '2026-08-22 10:00:00');
        $this->sale($store, $user, $cancelledOnly, 'CANCELLED', 50, 'anulada', '2026-08-15 10:00:00');

        $this->actingAs($user)->get(route('clientes.index', [
            'registrado_desde' => '2026-08-01',
            'registrado_hasta' => '2026-08-20',
            'ultima_compra_desde' => '2026-08-10',
            'ultima_compra_hasta' => '2026-08-20',
        ]))->assertOk()
            ->assertSee($matching->nombre)
            ->assertDontSee($tooRecent->nombre)
            ->assertDontSee($cancelledOnly->nombre);
    }

    public function test_commercial_ordering_uses_completed_purchase_totals(): void
    {
        [$user, $store] = $this->context();
        $highSpend = $this->customer($store, 'HIGH-SPEND');
        $manyPurchases = $this->customer($store, 'MANY-PURCHASES');
        $this->sale($store, $user, $highSpend, 'HIGH', 500, 'completada');
        foreach (range(1, 3) as $number) {
            $this->sale($store, $user, $manyPurchases, 'MANY-'.$number, 10, 'completada');
        }
        $this->sale($store, $user, $manyPurchases, 'IGNORED', 1000, 'anulada');

        $spendingContent = $this->actingAs($user)->get(route('clientes.index', ['orden' => 'gasto_desc']))->getContent();
        $this->assertLessThan(strpos($spendingContent, $manyPurchases->nombre), strpos($spendingContent, $highSpend->nombre));

        $purchaseContent = $this->get(route('clientes.index', ['orden' => 'compras_desc']))->getContent();
        $this->assertLessThan(strpos($purchaseContent, $highSpend->nombre), strpos($purchaseContent, $manyPurchases->nombre));
    }

    public function test_pagination_preserves_all_active_filters(): void
    {
        [$user, $store] = $this->context();
        foreach (range(1, 16) as $number) {
            $this->customer($store, 'PAGE-'.$number, ['ciudad' => 'Quito']);
        }

        $this->actingAs($user)->get(route('clientes.index', [
            'estado' => 'activo', 'ciudad' => 'Quito', 'actividad' => 'sin_compras', 'orden' => 'nombre_asc',
        ]))->assertOk()
            ->assertSee('estado=activo', false)
            ->assertSee('ciudad=Quito', false)
            ->assertSee('actividad=sin_compras', false)
            ->assertSee('orden=nombre_asc', false);
    }

    public function test_csv_export_reuses_filters_is_store_scoped_and_escapes_formulas(): void
    {
        [$user, $store] = $this->context();
        [, $foreignStore] = $this->context();
        $included = $this->customer($store, '=FORMULA', ['ciudad' => 'Quito']);
        $excluded = $this->customer($store, 'EXCLUDED', ['ciudad' => 'Cuenca']);
        $foreign = $this->customer($foreignStore, 'FOREIGN', ['ciudad' => 'Quito']);

        $response = $this->actingAs($user)->get(route('clientes.export', ['ciudad' => 'Quito']));

        $response->assertOk()->assertDownload();
        $content = $response->streamedContent();
        $this->assertStringContainsString("'=FORMULA", $content);
        $this->assertStringNotContainsString($excluded->nombre, $content);
        $this->assertStringNotContainsString($foreign->nombre, $content);
        $this->assertStringContainsString($included->codigo, $content);
    }

    private function context(): array
    {
        $suffix = uniqid();
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create(['rol_id' => $role->id, 'tienda_id' => $store->id, 'name' => 'Admin', 'email' => $suffix.'@segment.test', 'password' => 'password', 'activo' => true]);

        return [$user, $store];
    }

    private function customer(Tienda $store, string $name, array $overrides = []): Cliente
    {
        $suffix = uniqid();

        return Cliente::withoutGlobalScopes()->create(array_replace([
            'tienda_id' => $store->id, 'codigo' => 'CLI-'.$suffix, 'nombre' => $name,
            'apellido' => 'Cliente', 'email' => $suffix.'@segment.test', 'activo' => true,
        ], $overrides));
    }

    private function sale(Tienda $store, User $seller, Cliente $customer, string $number, float $total, string $status, mixed $createdAt = null): Venta
    {
        $sale = Venta::withoutGlobalScopes()->create([
            'tienda_id' => $store->id, 'numero_venta' => $number.'-'.$store->id, 'cliente_id' => $customer->id,
            'user_id' => $seller->id, 'fecha' => today(), 'subtotal' => $total, 'total' => $total,
            'monto_pagado' => $total, 'estado' => $status,
        ]);
        if ($createdAt !== null) {
            $sale->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        return $sale;
    }
}
