<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\PagoVenta;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistorialVentaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_isolated_by_store(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        [, $foreignStore] = $this->userWithStore('Vendedor');
        $ownSale = $this->sale($store, $seller, 'T001-00000001');
        $foreignSale = $this->sale($foreignStore, User::where('tienda_id', $foreignStore->id)->firstOrFail(), 'T002-00000001');

        $response = $this->actingAs($seller)->get(route('ventas.index'));

        $response->assertOk()->assertSee($ownSale->numero_venta)->assertDontSee($foreignSale->numero_venta);
        $this->get(route('ventas.show', $foreignSale->id))->assertNotFound();
    }

    public function test_history_rejects_invalid_filters(): void
    {
        [$seller] = $this->userWithStore('Vendedor');

        $this->actingAs($seller)->get(route('ventas.index', [
            'desde' => '2026-08-20',
            'hasta' => '2026-08-10',
            'estado' => 'eliminada',
        ]))->assertSessionHasErrors(['hasta', 'estado']);
    }

    public function test_history_rejects_advanced_filters_from_another_store(): void
    {
        [$seller] = $this->userWithStore('Vendedor');
        [$foreignSeller, $foreignStore] = $this->userWithStore('Vendedor');
        $foreignRegister = Caja::create(['tienda_id' => $foreignStore->id, 'user_id' => $foreignSeller->id, 'fecha' => '2026-08-24', 'estado' => 'abierta']);

        $this->actingAs($seller)->get(route('ventas.index', [
            'vendedor_id' => $foreignSeller->id,
            'caja_id' => $foreignRegister->id,
            'metodo' => 'criptomoneda',
        ]))->assertSessionHasErrors(['vendedor_id', 'caja_id', 'metodo']);
    }

    public function test_cancellation_requires_a_meaningful_reason(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $sale = $this->sale($store, $seller);

        $this->actingAs($seller)->post(route('ventas.anular', $sale), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame('completada', $sale->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_seller_cannot_cancel_another_sellers_sale(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $otherSeller = $this->user($store, 'Vendedor');
        $sale = $this->sale($store, $otherSeller);

        $this->actingAs($seller)->post(route('ventas.anular', $sale), ['motivo' => 'Solicitud del cliente'])
            ->assertForbidden();

        $this->assertSame('completada', $sale->fresh()->estado);
    }

    public function test_only_administrator_can_process_a_return(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $sale = $this->sale($store, $seller);

        $this->actingAs($seller)
            ->post(route('ventas.devolver', $sale), ['motivo' => 'Solicitud del cliente'])
            ->assertForbidden();

        $this->assertSame('completada', $sale->fresh()->estado);
        $this->assertDatabaseCount('venta_reversiones', 0);
    }

    public function test_administrator_can_cancel_another_sellers_sale(): void
    {
        [$administrator, $store] = $this->userWithStore('Administrador');
        $seller = $this->user($store, 'Vendedor');
        $sale = $this->sale($store, $seller);

        $this->actingAs($administrator)->post(route('ventas.anular', $sale), [
            'motivo' => 'Devolución autorizada por administración',
        ])->assertSessionHas('success');

        $sale->refresh();
        $this->assertSame('anulada', $sale->estado);
        $this->assertSame($administrator->id, $sale->anulada_por);
        $this->assertSame('Devolución autorizada por administración', $sale->motivo_anulacion);
    }

    public function test_history_searches_number_customer_document_seller_and_payment_reference(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $client = Cliente::create(['tienda_id' => $store->id, 'codigo' => 'CLI-900', 'nombre' => 'María', 'apellido' => 'Salazar', 'dni' => 'DOC-7788']);
        $sale = $this->sale($store, $seller, 'T001-00000900');
        $sale->update(['cliente_id' => $client->id]);
        PagoVenta::create(['tienda_id' => $store->id, 'venta_id' => $sale->id, 'metodo' => 'tarjeta', 'monto' => 10, 'referencia' => 'REF-UNICA-88']);
        $other = $this->sale($store, $this->user($store, 'Vendedor'), 'T001-00000901');

        foreach (['00000900', 'María', 'DOC-7788', $seller->name, 'REF-UNICA-88'] as $search) {
            $this->actingAs($seller)->get(route('ventas.index', ['q' => $search]))
                ->assertOk()->assertSee($sale->numero_venta)->assertDontSee($other->numero_venta);
        }
    }

    public function test_history_combines_seller_payment_method_and_cash_register_filters(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $otherSeller = $this->user($store, 'Vendedor');
        $register = Caja::create(['tienda_id' => $store->id, 'user_id' => $seller->id, 'fecha' => '2026-08-24', 'estado' => 'abierta', 'apertura' => now()]);
        $matching = $this->sale($store, $seller, 'T001-00000200');
        $matching->update(['caja_id' => $register->id]);
        PagoVenta::create(['tienda_id' => $store->id, 'venta_id' => $matching->id, 'metodo' => 'transferencia', 'monto' => 10]);
        $other = $this->sale($store, $otherSeller, 'T001-00000201');
        PagoVenta::create(['tienda_id' => $store->id, 'venta_id' => $other->id, 'metodo' => 'efectivo', 'monto' => 10]);

        $this->actingAs($seller)->get(route('ventas.index', [
            'vendedor_id' => $seller->id,
            'metodo' => 'transferencia',
            'caja_id' => $register->id,
        ]))->assertOk()->assertSee($matching->numero_venta)->assertDontSee($other->numero_venta);
    }

    public function test_history_filters_using_quick_periods(): void
    {
        Carbon::setTestNow('2026-08-24 12:00:00');
        [$seller, $store] = $this->userWithStore('Vendedor');
        $recent = $this->sale($store, $seller, 'T001-00000300');
        $old = $this->sale($store, $seller, 'T001-00000301');
        $old->update(['fecha' => '2026-08-10']);

        $this->actingAs($seller)->get(route('ventas.index', ['periodo' => '7_dias']))
            ->assertOk()->assertSee($recent->numero_venta)->assertDontSee($old->numero_venta);

        Carbon::setTestNow();
    }

    public function test_commercial_metrics_use_only_completed_filtered_sales(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $first = $this->sale($store, $seller, 'T001-00000400');
        $first->update(['subtotal' => 100, 'descuento' => 10, 'igv' => 20, 'total' => 120, 'monto_pagado' => 120]);
        $second = $this->sale($store, $seller, 'T001-00000401');
        $second->update(['subtotal' => 45, 'descuento' => 5, 'igv' => 10, 'total' => 60, 'monto_pagado' => 60]);
        $cancelled = $this->sale($store, $seller, 'T001-00000402');
        $cancelled->update(['subtotal' => 900, 'descuento' => 99, 'igv' => 99, 'total' => 999, 'estado' => 'anulada']);
        [$foreignSeller, $foreignStore] = $this->userWithStore('Vendedor');
        $this->sale($foreignStore, $foreignSeller, 'T900-00000400')->update(['total' => 500]);

        $response = $this->actingAs($seller)->get(route('ventas.index'));

        $response->assertOk();
        $this->assertSame([
            'results_count' => 3,
            'completed_count' => 2,
            'cancelled_count' => 1,
            'returned_count' => 0,
            'income' => 180.0,
            'discounts' => 15.0,
            'taxes' => 30.0,
            'average_ticket' => 90.0,
        ], $response->viewData('metrics'));

        $cancelledOnly = $this->get(route('ventas.index', ['estado' => 'anulada']))->viewData('metrics');
        $this->assertSame(1, $cancelledOnly['results_count']);
        $this->assertSame(1, $cancelledOnly['cancelled_count']);
        $this->assertSame(0.0, $cancelledOnly['income']);
        $this->assertSame(0.0, $cancelledOnly['average_ticket']);
    }

    public function test_history_renders_accessible_responsive_sale_information(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $sale = $this->sale($store, $seller, 'T001-00000500');

        $this->actingAs($seller)->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('sales-history-ui.css')
            ->assertSee('Operaciones registradas')
            ->assertSee('data-label="Fecha"', false)
            ->assertSee($sale->created_at->format('H:i'))
            ->assertSee('aria-label="Ver venta '.$sale->numero_venta.'"', false)
            ->assertSee('status-completada');
    }

    public function test_history_uses_the_id_as_stable_tie_breaker(): void
    {
        Carbon::setTestNow('2026-08-25 10:00:00');
        [$seller, $store] = $this->userWithStore('Vendedor');
        $first = $this->sale($store, $seller, 'T001-00000510');
        $second = $this->sale($store, $seller, 'T001-00000511');

        $sales = $this->actingAs($seller)->get(route('ventas.index'))->viewData('ventas');
        Carbon::setTestNow();

        $this->assertSame([$second->id, $first->id], $sales->pluck('id')->all());
    }

    public function test_printable_receipt_contains_fiscal_sale_variant_and_payment_data(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $store->update(['ruc' => 'RUC-179001', 'direccion' => 'Av. Principal 123', 'email' => 'ventas@tienda.test', 'moneda' => 'USD', 'simbolo_moneda' => '$']);
        $client = Cliente::create(['tienda_id' => $store->id, 'codigo' => 'CLI-700', 'nombre' => 'Ana', 'apellido' => 'Pérez', 'dni' => 'DOC-700']);
        $category = Categoria::create(['tienda_id' => $store->id, 'nombre' => 'Camisas', 'slug' => 'camisas-receipt']);
        $product = Producto::create(['tienda_id' => $store->id, 'categoria_id' => $category->id, 'codigo' => 'SKU-RECEIPT', 'nombre' => 'Camisa Premium', 'precio_venta' => 10, 'activo' => true]);
        $sale = $this->sale($store, $seller, 'T001-00000700');
        $sale->update(['cliente_id' => $client->id, 'subtotal' => 20, 'igv' => 2, 'total' => 22, 'monto_pagado' => 25, 'vuelto' => 3]);
        DetalleVenta::create(['tienda_id' => $store->id, 'venta_id' => $sale->id, 'producto_id' => $product->id, 'producto_nombre' => 'Camisa Premium', 'talla' => 'M', 'color' => 'Azul', 'cantidad' => 2, 'precio_unitario' => 10, 'descuento' => 0, 'subtotal' => 20]);
        PagoVenta::create(['tienda_id' => $store->id, 'venta_id' => $sale->id, 'metodo' => 'tarjeta', 'monto' => 22, 'referencia' => 'REF-700']);

        $this->actingAs($seller)->get(route('ventas.receipt', $sale))
            ->assertOk()
            ->assertSee('RUC-179001')->assertSee('Av. Principal 123')->assertSee('Ana Pérez')
            ->assertSee('Camisa Premium')->assertSee('M / Azul')->assertSee('REF-700')
            ->assertSee('$ 22.00')->assertSee('Factura A4')->assertSee('Ticket 80 mm');
    }

    public function test_receipt_can_be_downloaded_and_cannot_cross_store_boundaries(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $sale = $this->sale($store, $seller, 'T001-00000701');
        [, $foreignStore] = $this->userWithStore('Vendedor');
        $foreignSale = $this->sale($foreignStore, User::where('tienda_id', $foreignStore->id)->firstOrFail(), 'T900-00000701');

        $download = $this->actingAs($seller)->get(route('ventas.receipt', [$sale, 'download' => 1]));
        $download->assertOk()->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="T001-00000701.html"', (string) $download->headers->get('content-disposition'));
        $this->get(route('ventas.receipt', $foreignSale->id))->assertNotFound();
    }

    public function test_receipt_transforms_to_80mm_in_the_same_detail_view(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $sale = $this->sale($store, $seller, 'T001-00000800');

        $this->actingAs($seller)->get(route('ventas.show', $sale))
            ->assertOk()
            ->assertSee('id="toggleReceiptFormat"', false)
            ->assertSee('id="printCurrentReceipt"', false)
            ->assertSee('sale-receipt-detail.css')
            ->assertSee('Ticket 80 mm');

        // La ruta imprimible conserva la selección directa para enlaces guardados.
        $this->get(route('ventas.receipt', [$sale, 'format' => 'ticket']))
            ->assertOk()
            ->assertSee('class="receipt-print-page format-ticket"', false)
            ->assertSee('class="active" data-receipt-format="ticket"', false);
    }

    public function test_history_query_count_stays_bounded_with_many_sales(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        foreach (range(1, 35) as $sequence) {
            $this->sale($store, $seller, sprintf('T001-%08d', 9000 + $sequence));
        }

        $seller->unsetRelations();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($seller)->get(route('ventas.index'));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertSame(35, $response->viewData('ventas')->total());
        $this->assertCount(15, $response->viewData('ventas')->items());
        $this->assertLessThanOrEqual(15, $queries->count(), 'El historial presenta un posible problema N+1.');
        $this->assertFalse($queries->contains(fn (array $query) => str_contains($query['query'], 'detalle_ventas')));
    }

    public function test_sale_history_detail_receipt_and_cancellation_require_authentication(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $sale = $this->sale($store, $seller, 'T001-00001000');

        $this->get(route('ventas.index'))->assertRedirect(route('login'));
        $this->get(route('ventas.show', $sale))->assertRedirect(route('login'));
        $this->get(route('ventas.receipt', $sale))->assertRedirect(route('login'));
        $this->post(route('ventas.anular', $sale), ['motivo' => 'Acceso sin sesión'])->assertRedirect(route('login'));
        $this->assertSame('completada', $sale->fresh()->estado);
    }

    public function test_inactive_users_cannot_access_sale_history(): void
    {
        [$seller] = $this->userWithStore('Vendedor');
        $seller->update(['activo' => false]);

        $this->actingAs($seller)->get(route('ventas.index'))->assertForbidden();
    }

    public function test_cancelled_receipt_shows_watermark_reason_and_responsible_user(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $sale = $this->sale($store, $seller, 'T001-00001001');

        $this->actingAs($seller)->post(route('ventas.anular', $sale), [
            'motivo' => 'Comprobante emitido con información incorrecta',
        ])->assertSessionHas('success');

        $this->get(route('ventas.receipt', $sale->fresh()))
            ->assertOk()
            ->assertSee('ANULADA')
            ->assertSee('Comprobante emitido con información incorrecta')
            ->assertSee($seller->name);
    }

    public function test_pagination_keeps_the_active_history_filters(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        foreach (range(1, 18) as $sequence) {
            $this->sale($store, $seller, sprintf('T001-%08d', 11000 + $sequence));
        }

        $response = $this->actingAs($seller)->get(route('ventas.index', [
            'q' => $seller->name,
            'estado' => 'completada',
        ]));
        $nextPageUrl = $response->viewData('ventas')->nextPageUrl();
        parse_str((string) parse_url($nextPageUrl, PHP_URL_QUERY), $query);

        $response->assertOk();
        $this->assertSame($seller->name, $query['q'] ?? null);
        $this->assertSame('completada', $query['estado'] ?? null);
        $this->assertSame('2', $query['page'] ?? null);
    }

    public function test_unknown_receipt_format_falls_back_to_a4(): void
    {
        [$seller, $store] = $this->userWithStore('Vendedor');
        $sale = $this->sale($store, $seller, 'T001-00001002');

        $this->actingAs($seller)->get(route('ventas.receipt', [$sale, 'format' => 'unsupported']))
            ->assertOk()
            ->assertSee('class="receipt-print-page format-a4"', false);
    }

    private function userWithStore(string $roleName): array
    {
        $suffix = uniqid();
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);

        return [$this->user($store, $roleName), $store];
    }

    private function user(Tienda $store, string $roleName): User
    {
        $role = Rol::firstOrCreate(['nombre' => $roleName]);
        $suffix = uniqid();

        return User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => $roleName.' '.$suffix,
            'email' => $suffix.'@example.test',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    private function sale(Tienda $store, User $seller, string $number = 'T001-00000001'): Venta
    {
        return Venta::create([
            'tienda_id' => $store->id,
            'numero_venta' => $number,
            'user_id' => $seller->id,
            'fecha' => '2026-08-24',
            'tipo_comprobante' => 'ticket',
            'serie' => 'T001',
            'correlativo' => substr($number, -8),
            'subtotal' => 10,
            'igv' => 0,
            'total' => 10,
            'metodo_pago' => 'efectivo',
            'monto_pagado' => 10,
            'estado' => 'completada',
        ]);
    }
}
