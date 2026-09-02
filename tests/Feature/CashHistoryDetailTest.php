<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\PagoVenta;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashHistoryDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_and_detail_require_authentication(): void
    {
        [$user] = $this->userWithStore('Administrador');
        $register = $this->closedRegister($user);

        $this->get(route('caja.index'))->assertRedirect(route('login'));
        $this->get(route('caja.show', $register))->assertRedirect(route('login'));
        $this->get(route('caja.export'))->assertRedirect(route('login'));
    }

    public function test_history_and_detail_are_isolated_by_store(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        [$foreignAdministrator] = $this->userWithStore('Administrador');
        $own = $this->closedRegister($administrator, '2026-08-24');
        $foreign = $this->closedRegister($foreignAdministrator, '2026-08-23');

        $response = $this->actingAs($administrator)->get(route('caja.index'))->assertOk();
        $ids = collect($response->viewData('historial')->items())->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($foreign->id));
        $this->get(route('caja.show', $foreign->id))->assertNotFound();
    }

    public function test_history_filters_by_identifier_dates_state_reconciliation_and_responsible(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $cashier = $this->user($store, 'Cajero');
        $matching = $this->closedRegister($cashier, '2026-08-20', 'faltante');
        $other = $this->closedRegister($administrator, '2026-08-10', 'exacto');

        $response = $this->actingAs($administrator)->get(route('caja.index', [
            'q' => '#'.$matching->id,
            'desde' => '2026-08-15',
            'hasta' => '2026-08-24',
            'estado' => 'cerrada',
            'conciliacion' => 'faltante',
            'responsable_id' => $cashier->id,
        ]));

        $response->assertOk();
        $ids = collect($response->viewData('historial')->items())->pluck('id');
        $this->assertSame([$matching->id], $ids->all());
    }

    public function test_history_searches_by_responsible_name(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $cashier = $this->user($store, 'Cajero Especial');
        $matching = $this->closedRegister($cashier);
        $other = $this->closedRegister($administrator);

        $response = $this->actingAs($administrator)->get(route('caja.index', ['q' => 'Cajero Especial']))->assertOk();
        $ids = collect($response->viewData('historial')->items())->pluck('id');
        $this->assertSame([$matching->id], $ids->all());
    }

    public function test_history_rejects_invalid_and_cross_store_filters(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        [$foreignUser] = $this->userWithStore('Cajero');

        $this->actingAs($administrator)->get(route('caja.index', [
            'desde' => '2026-08-24',
            'hasta' => '2026-08-01',
            'estado' => 'eliminada',
            'conciliacion' => 'desconocida',
            'responsable_id' => $foreignUser->id,
        ]))->assertSessionHasErrors(['hasta', 'estado', 'conciliacion', 'responsable_id']);
    }

    public function test_history_pagination_preserves_active_filters(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        foreach (range(1, 18) as $day) {
            $this->closedRegister($administrator, sprintf('2026-08-%02d', $day), 'exacto');
        }

        $response = $this->actingAs($administrator)->get(route('caja.index', ['estado' => 'cerrada', 'conciliacion' => 'exacto']));
        $next = $response->viewData('historial')->nextPageUrl();
        parse_str((string) parse_url($next, PHP_URL_QUERY), $query);

        $this->assertSame('cerrada', $query['estado'] ?? null);
        $this->assertSame('exacto', $query['conciliacion'] ?? null);
        $this->assertSame('2', $query['page'] ?? null);
    }

    public function test_detail_displays_reconciliation_sales_payments_movements_and_print_action(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        $register = $this->closedRegister($administrator, '2026-08-24', 'sobrante');
        $sale = $this->sale($administrator, $register, 'T001-00000888', 75);
        PagoVenta::create(['tienda_id' => $administrator->tienda_id, 'venta_id' => $sale->id, 'metodo' => 'tarjeta', 'monto' => 75, 'referencia' => 'CARD-888']);
        MovimientoCaja::create([
            'tienda_id' => $administrator->tienda_id,
            'caja_id' => $register->id,
            'user_id' => $administrator->id,
            'tipo' => 'egreso',
            'concepto' => 'Pago de mensajería',
            'monto' => 8,
            'observaciones' => 'Entrega del turno',
        ]);

        $this->actingAs($administrator)->get(route('caja.show', $register))
            ->assertOk()
            ->assertSee('Detalle completo del turno')
            ->assertSee('T001-00000888')
            ->assertSee('Tarjeta')
            ->assertSee('$ 75.00')
            ->assertSee('Pago de mensajería')
            ->assertSee('Entrega del turno')
            ->assertSee('Sobrante')
            ->assertSee('data-print-cash', false)
            ->assertSee('css/caja.css')
            ->assertSee('js/caja.js');
    }

    public function test_detail_paginates_sales_and_movements_independently(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        $register = $this->closedRegister($administrator);
        foreach (range(1, 17) as $number) {
            $this->sale($administrator, $register, sprintf('T001-%08d', $number), 10);
        }
        foreach (range(1, 22) as $number) {
            MovimientoCaja::create([
                'tienda_id' => $administrator->tienda_id,
                'caja_id' => $register->id,
                'user_id' => $administrator->id,
                'tipo' => 'ingreso',
                'concepto' => 'Movimiento '.$number,
                'monto' => 1,
            ]);
        }

        $response = $this->actingAs($administrator)->get(route('caja.show', $register));

        $this->assertSame(17, $response->viewData('ventas')->total());
        $this->assertCount(15, $response->viewData('ventas')->items());
        $this->assertSame(22, $response->viewData('movimientos')->total());
        $this->assertCount(20, $response->viewData('movimientos')->items());
    }

    public function test_csv_export_uses_filters_and_excludes_other_stores(): void
    {
        [$administrator] = $this->userWithStore('Administrador');
        [$foreignAdministrator] = $this->userWithStore('Administrador');
        $matching = $this->closedRegister($administrator, '2026-08-24', 'faltante');
        $excluded = $this->closedRegister($administrator, '2026-08-23', 'exacto');
        $foreign = $this->closedRegister($foreignAdministrator, '2026-08-24', 'faltante');

        $response = $this->actingAs($administrator)->get(route('caja.export', ['conciliacion' => 'faltante']));
        $content = $response->streamedContent();

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString((string) $matching->id, $content);
        $this->assertStringNotContainsString("\n{$excluded->id},", $content);
        $this->assertStringNotContainsString("\n{$foreign->id},", $content);
        $this->assertStringContainsString('Conciliación', $content);
    }

    private function userWithStore(string $role): array
    {
        $suffix = uniqid();
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);

        return [$this->user($store, $role), $store];
    }

    private function user(Tienda $store, string $role): User
    {
        $roleModel = Rol::firstOrCreate(['nombre' => $role]);
        $suffix = uniqid();

        return User::create([
            'rol_id' => $roleModel->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@caja-historial.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function closedRegister(User $user, string $date = '2026-08-24', string $reconciliation = 'exacto'): Caja
    {
        $difference = match ($reconciliation) { 'sobrante' => 5, 'faltante' => -5, default => 0 };

        return Caja::create([
            'tienda_id' => $user->tienda_id,
            'user_id' => $user->id,
            'cerrada_por' => $user->id,
            'fecha' => $date,
            'monto_inicial' => 100,
            'monto_final' => 100 + $difference,
            'efectivo_esperado' => 100,
            'efectivo_contado' => 100 + $difference,
            'diferencia' => $difference,
            'estado_conciliacion' => $reconciliation,
            'total_ventas' => 0,
            'estado' => 'cerrada',
            'apertura' => $date.' 08:00:00',
            'cierre' => $date.' 16:00:00',
            'observaciones_cierre' => $difference ? 'Diferencia justificada' : null,
        ]);
    }

    private function sale(User $user, Caja $register, string $number, float $total): Venta
    {
        return Venta::create([
            'tienda_id' => $user->tienda_id,
            'numero_venta' => $number,
            'user_id' => $user->id,
            'caja_id' => $register->id,
            'fecha' => $register->fecha,
            'tipo_comprobante' => 'ticket',
            'serie' => 'T001',
            'correlativo' => substr($number, -8),
            'subtotal' => $total,
            'igv' => 0,
            'total' => $total,
            'metodo_pago' => 'tarjeta',
            'monto_pagado' => $total,
            'vuelto' => 0,
            'estado' => 'completada',
        ]);
    }
}
