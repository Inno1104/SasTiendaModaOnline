<?php

namespace Tests\Feature;

use App\Mail\OnlineOrderReceiptMail;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\PedidoOnline;
use App\Models\PedidoOnlineDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\StockReserva;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use App\Support\OnlineOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankTransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_reports_transfer_and_administrator_confirms_payment_and_inventory(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$store, $variant, $order, $administrator] = $this->context();

        $this->actingAs($administrator)
            ->get(route('pedidos-online.index'))
            ->assertOk()
            ->assertDontSee($order->numero);

        $this->post(route('storefront.transfer.store', [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]), [
            'referencia_pago' => 'TRX-987654',
            'comprobante' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertRedirect()
            ->assertSessionHas('transfer_success');

        $order->refresh();
        $this->assertSame(OnlineOrderStatus::AWAITING_PAYMENT, $order->estado);
        $this->assertSame('TRX-987654', $order->referencia_pago);
        $this->assertNotNull($order->pago_reportado_at);
        $this->assertNotNull($order->comprobante_pago_path);
        Storage::disk('local')->assertExists($order->comprobante_pago_path);
        $this->assertTrue($order->reserva_expira_at->greaterThan(now()->addHours(23)));

        $this->actingAs($administrator)
            ->get(route('pedidos-online.index'))
            ->assertOk()
            ->assertSee($order->numero)
            ->assertSee('1 pedido pendiente de verificar');

        $this->actingAs($administrator)
            ->post(route('pedidos-online.verify', $order->id), ['modo' => 'con_factura'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OnlineOrderStatus::PAID, $order->estado);
        $this->assertSame($administrator->id, $order->pago_verificado_por);
        $this->assertNotNull($order->pagado_at);
        $this->assertNotNull($order->venta_id);
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame(StockReserva::CONSUMED, StockReserva::withoutGlobalScopes()->sole()->estado);

        $this->actingAs($administrator)
            ->get(route('pedidos-online.show', $order->id))
            ->assertOk()
            ->assertSee('0 pedidos pendientes');

        $sale = Venta::withoutGlobalScopes()->with('pagos', 'detalles', 'cliente')->findOrFail($order->venta_id);
        $this->assertSame('boleta', $sale->tipo_comprobante);
        $this->assertSame('transferencia', $sale->metodo_pago);
        $this->assertSame('165.00', $sale->total);
        $this->assertSame('TRX-987654', $sale->pagos->sole()->referencia);
        $this->assertSame(3, $sale->detalles->sole()->cantidad);
        $this->assertSame('Ana', $sale->cliente->nombre);

        $this->actingAs($administrator)
            ->get(route('pedidos-online.receipt', $order->id))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=comprobante-'.$order->numero.'.'.pathinfo($order->comprobante_pago_path, PATHINFO_EXTENSION))
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->actingAs($administrator)
            ->get(route('pedidos-online.receipt', ['pedido' => $order->id, 'download' => 1]))
            ->assertOk()
            ->assertDownload('comprobante-'.$order->numero.'.'.pathinfo($order->comprobante_pago_path, PATHINFO_EXTENSION));
    }

    public function test_expired_reservation_cannot_report_a_transfer(): void
    {
        Storage::fake('local');
        [$store, , $order] = $this->context();
        StockReserva::withoutGlobalScopes()->where('pedido_online_id', $order->id)->update([
            'expira_at' => now()->subMinute(),
        ]);

        $this->post(route('storefront.transfer.store', [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]), [
            'referencia_pago' => 'TRX-EXPIRADA',
            'comprobante' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertSessionHasErrors('comprobante');

        $this->assertNull($order->fresh()->pago_reportado_at);
        Storage::disk('local')->assertDirectoryEmpty('transferencias');
    }

    public function test_requested_fiscal_document_generates_an_invoice(): void
    {
        Storage::fake('local');
        [$store, , $order, $administrator] = $this->context();
        $order->forceFill([
            'requiere_factura' => true,
            'tipo_comprobante' => 'factura',
            'tipo_identificacion' => 'ruc_nit',
            'identificacion' => '1790012345001',
            'razon_social' => 'Comercial Andina S.A.',
        ])->save();
        $this->reportTransfer($store, $order);

        $this->actingAs($administrator)
            ->post(route('pedidos-online.verify', $order->id), ['modo' => 'con_factura'])
            ->assertRedirect();

        $sale = Venta::withoutGlobalScopes()->findOrFail($order->fresh()->venta_id);
        $this->assertSame('factura', $sale->tipo_comprobante);
        $this->assertStringStartsWith('F001-', $sale->numero_venta);
        $this->assertSame('Comercial Andina S.A.', $sale->commercial_snapshot['customer']['name']);
        $this->assertSame('1790012345001', $sale->commercial_snapshot['customer']['document']);
    }

    public function test_verification_uses_document_customer_without_overwriting_an_email_owned_by_another_customer(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$store, , $order, $administrator] = $this->context();
        $order->forceFill(['cliente_dni' => '1003814660'])->save();

        $documentCustomer = Cliente::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'codigo' => 'CLI-00001',
            'nombre' => 'Cliente',
            'apellido' => 'Por cedula',
            'dni' => '1003814660',
            'email' => 'correo-anterior@example.com',
            'activo' => true,
        ]);
        Cliente::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'codigo' => 'CLI-00002',
            'nombre' => 'Cliente',
            'apellido' => 'Por correo',
            'dni' => '51692817',
            'email' => $order->cliente_email,
            'activo' => true,
        ]);
        $this->reportTransfer($store, $order);

        $this->actingAs($administrator)
            ->post(route('pedidos-online.verify', $order->id), ['modo' => 'con_factura'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sale = Venta::withoutGlobalScopes()->findOrFail($order->fresh()->venta_id);
        $this->assertSame($documentCustomer->id, $sale->cliente_id);
        $this->assertSame('correo-anterior@example.com', $documentCustomer->fresh()->email);
        $this->assertSame($order->cliente_email, $sale->commercial_snapshot['customer']['email']);
        $this->assertSame(OnlineOrderStatus::PAID, $order->fresh()->estado);
    }

    public function test_administrator_can_send_generated_receipt_to_customer_after_verifying_payment(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$store, , $order, $administrator] = $this->context();
        $this->reportTransfer($store, $order);
        $this->actingAs($administrator)->post(route('pedidos-online.verify', $order->id), ['modo' => 'con_factura'])->assertRedirect();

        $this->actingAs($administrator)
            ->post(route('pedidos-online.send-receipt', $order->id), ['modo' => 'con_factura'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(OnlineOrderReceiptMail::class, function ($mail) use ($order) {
            $mail->build();
            $this->assertSame('emails.online-order-receipt', $mail->view);

            return $mail->hasTo($order->cliente_email);
        });
        $this->assertNotNull($order->fresh()->comprobante_enviado_at);
        $this->assertSame($administrator->id, $order->fresh()->comprobante_enviado_por);
        $this->assertNotNull($order->fresh()->comprobante_publicado_at);
        $this->actingAs($administrator)
            ->get(route('pedidos-online.show', $order->id))
            ->assertOk()
            ->assertDontSee('Enviar confirmación');

        $publicReceipt = URL::temporarySignedRoute('storefront.orders.sale-receipt', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);
        $this->get($publicReceipt)
            ->assertOk()
            ->assertSee($saleNumber = $order->fresh()->venta->numero_venta)
            ->assertDontSee('Volver a mi pedido')
            ->assertDontSee('Registro')
            ->assertDontSee(route('ventas.show', $order->fresh()->venta), false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertNotEmpty($saleNumber);
        $trackingUrl = URL::temporarySignedRoute('storefront.orders.tracking', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);
        $this->get($trackingUrl)
            ->assertOk()
            ->assertSee('data-receipt-modal', false)
            ->assertSee('data-open-receipt', false)
            ->assertDontSee('target="_blank"', false);
        $this->get(route('storefront.orders.sale-receipt', [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]))->assertForbidden();
    }

    public function test_confirmation_without_invoice_does_not_publish_customer_receipt(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$store, , $order, $administrator] = $this->context();
        $this->reportTransfer($store, $order);
        $this->actingAs($administrator)->post(route('pedidos-online.verify', $order->id), ['modo' => 'sin_factura'])->assertRedirect();

        $this->actingAs($administrator)
            ->post(route('pedidos-online.send-receipt', $order->id), ['modo' => 'sin_factura'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(OnlineOrderReceiptMail::class, fn ($mail) => ! $mail->includeReceipt);
        $this->assertNotNull($order->fresh()->comprobante_enviado_at);
        $this->assertNull($order->fresh()->comprobante_publicado_at);

        $publicReceipt = URL::temporarySignedRoute('storefront.orders.sale-receipt', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);
        $this->get($publicReceipt)->assertNotFound();
    }

    public function test_receipt_cannot_be_sent_before_payment_is_verified(): void
    {
        Mail::fake();
        [, , $order, $administrator] = $this->context();

        $this->actingAs($administrator)
            ->post(route('pedidos-online.send-receipt', $order->id), ['modo' => 'con_factura'])
            ->assertStatus(409);

        Mail::assertNothingSent();
    }

    public function test_payment_is_not_confirmed_without_selecting_a_delivery_mode(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$store, $variant, $order, $administrator] = $this->context();
        $this->reportTransfer($store, $order);

        $this->actingAs($administrator)
            ->post(route('pedidos-online.verify', $order->id))
            ->assertSessionHasErrors('modo');

        $order->refresh();
        $this->assertSame(OnlineOrderStatus::AWAITING_PAYMENT, $order->estado);
        $this->assertNull($order->pago_verificado_at);
        $this->assertNull($order->venta_id);
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(StockReserva::ACTIVE, StockReserva::withoutGlobalScopes()->sole()->estado);
        Mail::assertNothingSent();
    }

    public function test_repeated_payment_verification_is_idempotent(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$store, $variant, $order, $administrator] = $this->context();
        $this->reportTransfer($store, $order);

        $this->actingAs($administrator)
            ->post(route('pedidos-online.verify', $order->id), ['modo' => 'con_factura'])
            ->assertRedirect();

        $firstSaleId = $order->fresh()->venta_id;
        $this->actingAs($administrator)
            ->post(route('pedidos-online.verify', $order->id), ['modo' => 'con_factura'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Este pago ya fue verificado. No se generó una venta duplicada.');

        $this->assertSame($firstSaleId, $order->fresh()->venta_id);
        $this->assertSame(1, Venta::withoutGlobalScopes()->where('tienda_id', $store->id)->count());
        $this->assertSame(7, $variant->fresh()->stock);
        Mail::assertSent(OnlineOrderReceiptMail::class, 1);
    }

    public function test_rejecting_unverified_transfer_releases_reserved_stock(): void
    {
        Storage::fake('local');
        [$store, $variant, $order, $administrator] = $this->context();
        $this->reportTransfer($store, $order);

        $this->assertSame(7, $variant->fresh()->stockDisponibleVenta());
        $this->actingAs($administrator)->post(route('pedidos-online.cancel', $order->id), [
            'motivo' => 'El comprobante no corresponde a la transferencia.',
        ])->assertRedirect(route('pedidos-online.show', $order->id));

        $this->assertSame(OnlineOrderStatus::CANCELLED, $order->fresh()->estado);
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(10, $variant->fresh()->stockDisponibleVenta());
        $this->assertSame(StockReserva::RELEASED, StockReserva::withoutGlobalScopes()->sole()->estado);
    }

    public function test_cancelling_paid_order_restores_physical_stock_exactly_once(): void
    {
        Storage::fake('local');
        [$store, $variant, $order, $administrator] = $this->context();
        $this->reportTransfer($store, $order);
        $this->actingAs($administrator)->post(route('pedidos-online.verify', $order->id), ['modo' => 'con_factura'])->assertRedirect();
        $this->assertSame(7, $variant->fresh()->stock);

        $payload = ['motivo' => 'El cliente solicitó cancelar el pedido pagado.'];
        $this->actingAs($administrator)
            ->post(route('pedidos-online.cancel', $order->id), $payload)
            ->assertRedirect(route('pedidos-online.show', $order->id));
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(OnlineOrderStatus::CANCELLED, $order->fresh()->estado);
        $this->assertSame('anulada', Venta::withoutGlobalScopes()->findOrFail($order->fresh()->venta_id)->estado);

        $this->actingAs($administrator)
            ->post(route('pedidos-online.cancel', $order->id), $payload)
            ->assertStatus(409);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    private function reportTransfer(Tienda $store, PedidoOnline $order): void
    {
        $this->post(route('storefront.transfer.store', [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]), [
            'referencia_pago' => 'TRX-CANCELACION',
            'comprobante' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertSessionHas('transfer_success');
    }

    private function context(): array
    {
        $token = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Moda transferencia',
            'slug' => 'moda-'.$token,
            'estado' => 'activa',
            'publicada' => true,
            'pais_codigo' => 'EC',
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'transferencia_activa' => true,
            'transferencia_banco' => 'Banco de prueba',
            'transferencia_tipo_cuenta' => 'Ahorros',
            'transferencia_numero_cuenta' => '1234567890',
            'transferencia_titular' => 'Moda Transferencia S.A.',
            'transferencia_identificacion' => '1791234567001',
        ]);
        $category = Categoria::create([
            'tienda_id' => $store->id,
            'nombre' => 'Chaquetas',
            'nombre_normalizado' => 'chaquetas',
            'slug' => 'chaquetas-'.$token,
            'activo' => true,
        ]);
        $product = Producto::create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'TRF-'.Str::upper($token),
            'nombre' => 'Chaqueta transferencia',
            'precio_venta' => 55,
            'activo' => true,
        ]);
        $variant = ProductoVariante::create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => 10,
            'stock_minimo' => 1,
        ]);
        $operation = (string) Str::uuid();
        $order = PedidoOnline::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'operacion_id' => $operation,
            'operacion_hash' => hash('sha256', $operation),
            'numero' => 'WEB-'.$store->id.'-2026-000001',
            'estado' => OnlineOrderStatus::PENDING,
            'metodo_pago' => 'transferencia',
            'cliente_nombre' => 'Ana',
            'cliente_apellido' => 'Paredes',
            'cliente_email' => 'ana@example.com',
            'cliente_telefono' => '+593991234567',
            'requiere_factura' => false,
            'subtotal' => 165,
            'descuento' => 0,
            'envio' => 0,
            'total' => 165,
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'reserva_expira_at' => now()->addMinutes(15),
        ]);
        PedidoOnlineDetalle::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'pedido_online_id' => $order->id,
            'producto_id' => $product->id,
            'variante_id' => $variant->id,
            'producto_codigo' => $product->codigo,
            'producto_nombre' => $product->nombre,
            'talla' => $variant->talla,
            'color' => $variant->color,
            'cantidad' => 3,
            'precio_unitario' => 55,
            'descuento' => 0,
            'subtotal' => 165,
        ]);
        StockReserva::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'pedido_online_id' => $order->id,
            'variante_id' => $variant->id,
            'cantidad' => 3,
            'estado' => StockReserva::ACTIVE,
            'expira_at' => now()->addMinutes(15),
        ]);
        $role = Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR]);
        $administrator = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => 'admin-'.$token.'@example.com',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$store, $variant, $order, $administrator];
    }
}
