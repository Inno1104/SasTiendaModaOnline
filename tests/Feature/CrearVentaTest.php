<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaReversion;
use App\Services\CashRegisterSummary;
use App\Services\SaleCommercialSnapshot;
use App\Support\SaleLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrearVentaTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_pos_catalog_with_filters_variant_stock_and_local_assets(): void
    {
        [$user, $product, $variant] = $this->salesContext();
        $product->update(['imagen' => 'productos/demo.webp']);
        $variant->update(['codigo_barra' => 'BAR-12345']);

        $response = $this->actingAs($user)->get(route('ventas.create'));

        $response->assertOk();
        $response->assertSee('Historial de ventas');
        $response->assertSee('Todas las categorías');
        $response->assertSee(route('ventas.create', ['categoria_id' => $product->categoria_id]), false);
        $response->assertSee('Solo disponibles');
        $response->assertSee('bar-12345');
        $response->assertSee('M / Negro');
        $response->assertSee('storage/productos/demo.webp');
        $response->assertSee('css/pos.css');
        $response->assertSee('js/pos.js');
        $response->assertSee('Revisar y confirmar');
    }

    public function test_category_shortcut_filters_the_complete_pos_catalog_on_the_server(): void
    {
        [$user, $selectedProduct] = $this->salesContext();
        $otherCategory = Categoria::create([
            'tienda_id' => $user->tienda_id,
            'nombre' => 'Categoría no seleccionada',
            'slug' => 'categoria-no-seleccionada',
        ]);
        Producto::create([
            'tienda_id' => $user->tienda_id,
            'categoria_id' => $otherCategory->id,
            'codigo' => 'SKU-NO-SELECCIONADO',
            'nombre' => 'Producto no seleccionado',
            'precio_venta' => 20,
            'activo' => true,
        ]);

        $this->actingAs($user)->get(route('ventas.create', ['categoria_id' => $selectedProduct->categoria_id]))
            ->assertOk()
            ->assertSee($selectedProduct->nombre)
            ->assertDontSee('Producto no seleccionado')
            ->assertSee('category-shortcut active', false);
    }

    public function test_pos_catalog_exposes_the_shared_variant_stock_state(): void
    {
        [$user, , $variant] = $this->salesContext(stock: 2);
        $variant->update(['stock_minimo' => 2]);

        $this->actingAs($user)->getJson(route('ventas.catalogo'))
            ->assertOk()
            ->assertJsonPath('data.0.variantes.0.stock', 2)
            ->assertJsonPath('data.0.variantes.0.stock_minimo', 2)
            ->assertJsonPath('data.0.variantes.0.estado_stock', 'bajo')
            ->assertJsonPath('data.0.variantes.0.descripcion_stock', '2 disponibles · Stock bajo');
    }

    public function test_creates_a_sale_from_trusted_data_and_decrements_the_selected_variant(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 8);
        $payload = $this->payload($product->id, $variant->id, 2);
        $payload['items'][0] += ['precio_unitario' => 0.01, 'nombre' => 'Manipulado'];

        $response = $this->actingAs($user)->post(route('ventas.store'), $payload);

        $sale = $user->ventas()->firstOrFail();
        $this->assertDatabaseHas('pagos_venta', [
            'venta_id' => $sale->id,
            'metodo' => 'efectivo',
            'monto' => 100,
        ]);
        $this->assertSame(1, app(CashRegisterSummary::class)->calculate($sale->caja)['ventas_cantidad']);
        $response->assertRedirect(route('ventas.show', $sale));
        $this->assertEquals(20, $sale->subtotal);
        $this->assertEquals(22, $sale->total);
        $this->assertSame('T001', $sale->serie);
        $this->assertSame('00000001', $sale->correlativo);
        $this->assertSame('T001-00000001', $sale->numero_venta);
        $this->assertDatabaseHas('auditorias', [
            'tienda_id' => $user->tienda_id,
            'user_id' => $user->id,
            'accion' => 'venta.creada',
            'auditable_id' => $sale->id,
        ]);
        $this->assertSame(6, $variant->fresh()->stock);
        $this->assertSame(1, $variant->fresh()->lock_version);
        $this->assertDatabaseHas('detalle_ventas', [
            'venta_id' => $sale->id,
            'producto_nombre' => $product->nombre,
            'variante_id' => $variant->id,
            'talla' => 'M',
            'color' => 'Negro',
            'precio_unitario' => 10,
            'cantidad' => 2,
        ]);
        $movement = StockMovimiento::query()->sole();
        $this->assertSame(StockMovimiento::TIPO_VENTA, $movement->tipo);
        $this->assertSame('salida', $movement->direccion);
        $this->assertSame(-2, $movement->cantidad);
        $this->assertSame(8, $movement->stock_anterior);
        $this->assertSame(6, $movement->stock_resultante);
        $this->assertSame($sale->getMorphClass(), $movement->referencia_type);
        $this->assertSame($sale->id, $movement->referencia_id);
    }

    public function test_repeated_sale_operation_does_not_discount_stock_twice(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 8);
        $payload = $this->payload($product->id, $variant->id, 2);
        $payload['operacion_id'] = (string) Str::uuid();

        $first = $this->actingAs($user)->post(route('ventas.store'), $payload);
        $sale = $user->ventas()->sole();
        $first->assertRedirect(route('ventas.show', $sale));
        $this->post(route('ventas.store'), $payload)
            ->assertRedirect(route('ventas.show', $sale));

        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseCount('detalle_ventas', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertSame(6, $variant->fresh()->stock);
        $this->assertSame(1, $variant->fresh()->lock_version);
    }

    public function test_receipt_uses_the_immutable_commercial_snapshot_after_catalog_changes(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, 'snapshot', 5);
        $user->tienda->update([
            'nombre' => 'Tienda Original',
            'ruc' => 'RUC-ORIGINAL',
            'simbolo_moneda' => '$',
        ]);

        $this->actingAs($user)->post(
            route('ventas.store'),
            $this->payload($product->id, $variant->id)
        )->assertSessionHasNoErrors();

        $sale = Venta::query()->sole();
        $this->assertSame(1, $sale->commercial_snapshot['version']);
        $this->assertSame($product->nombre, $sale->commercial_snapshot['items'][0]['name']);
        $this->assertSame(
            app(SaleCommercialSnapshot::class)->hash($sale->commercial_snapshot),
            $sale->commercial_snapshot_hash
        );

        $originalProductName = $product->nombre;
        $product->update(['nombre' => 'Producto Modificado', 'codigo' => 'SKU-MODIFICADO']);
        $user->tienda->update(['nombre' => 'Tienda Modificada', 'ruc' => 'RUC-NUEVO', 'simbolo_moneda' => '€']);

        $this->get(route('ventas.receipt', $sale))
            ->assertOk()
            ->assertSee('TIENDA ORIGINAL')
            ->assertSee('RUC-ORIGINAL')
            ->assertSee($originalProductName)
            ->assertDontSee('Producto Modificado')
            ->assertDontSee('RUC-NUEVO')
            ->assertSee('Snapshot v1');
    }

    public function test_sale_operation_key_cannot_be_reused_with_different_items(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 8);
        $payload = $this->payload($product->id, $variant->id, 1);
        $payload['operacion_id'] = (string) Str::uuid();

        $this->actingAs($user)->post(route('ventas.store'), $payload)->assertSessionHasNoErrors();
        $payload['items'][0]['cantidad'] = 2;
        $this->post(route('ventas.store'), $payload)->assertSessionHasErrors('operacion_id');

        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_competing_sales_cannot_consume_the_same_remaining_stock(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 3);
        $first = $this->payload($product->id, $variant->id, 2);
        $first['operacion_id'] = (string) Str::uuid();
        $second = $first;
        $second['operacion_id'] = (string) Str::uuid();

        $this->actingAs($user)->post(route('ventas.store'), $first)->assertSessionHasNoErrors();
        $this->post(route('ventas.store'), $second)->assertSessionHasErrors('items');

        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseCount('stock_movimientos', 1);
        $this->assertSame(1, $variant->fresh()->stock);
    }

    public function test_rejects_a_product_or_variant_from_another_store(): void
    {
        [$user] = $this->salesContext();
        [, $foreignProduct, $foreignVariant] = $this->salesContext(10, 'foreign');

        $response = $this->actingAs($user)->post(
            route('ventas.store'),
            $this->payload($foreignProduct->id, $foreignVariant->id)
        );

        $response->assertSessionHasErrors(['items.0.producto_id', 'items.0.variante_id']);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_rejects_a_variant_that_does_not_belong_to_the_product(): void
    {
        [$user, $product] = $this->salesContext();
        $otherProduct = Producto::create([
            'tienda_id' => $user->tienda_id,
            'categoria_id' => $product->categoria_id,
            'codigo' => 'SKU-OTHER',
            'nombre' => 'Otro producto',
            'precio_venta' => 20,
            'activo' => true,
        ]);
        $otherVariant = $this->variant($otherProduct, 5);

        $response = $this->actingAs($user)->post(
            route('ventas.store'),
            $this->payload($product->id, $otherVariant->id)
        );

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(5, $otherVariant->fresh()->stock);
    }

    public function test_rejects_an_active_product_when_its_category_is_inactive(): void
    {
        [$user, $product, $variant] = $this->salesContext();
        Categoria::withoutGlobalScopes()->whereKey($product->categoria_id)->update(['activo' => false]);

        $response = $this->actingAs($user)->post(
            route('ventas.store'),
            $this->payload($product->id, $variant->id)
        );

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_rejects_insufficient_stock_without_partial_records_or_movements(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 2);

        $response = $this->actingAs($user)->post(
            route('ventas.store'),
            $this->payload($product->id, $variant->id, 3)
        );

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('detalle_ventas', 0);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
        $this->assertSame(2, $variant->fresh()->stock);
    }

    public function test_aggregates_duplicate_variant_lines_before_checking_stock(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 3);
        $payload = $this->payload($product->id, $variant->id, 2);
        $payload['items'][] = ['producto_id' => $product->id, 'variante_id' => $variant->id, 'cantidad' => 2];

        $response = $this->actingAs($user)->post(route('ventas.store'), $payload);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(3, $variant->fresh()->stock);
    }

    public function test_cancelling_a_sale_restores_stock_exactly_once(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 5);
        $this->actingAs($user)->post(route('ventas.store'), $this->payload($product->id, $variant->id, 2));
        $sale = $user->ventas()->firstOrFail();

        $first = $this->post(route('ventas.anular', $sale), ['motivo' => 'Error de digitación']);
        $first->assertSessionHas('success');

        $second = $this->post(route('ventas.anular', $sale), ['motivo' => 'Intento repetido']);
        $second->assertSessionHas('error');
        $this->assertSame('anulada', $sale->fresh()->estado);
        $this->assertSame($user->id, $sale->fresh()->anulada_por);
        $this->assertSame('Error de digitación', $sale->fresh()->motivo_anulacion);
        $this->assertNotNull($sale->fresh()->anulada_at);
        $this->assertDatabaseHas('auditorias', ['accion' => 'venta.anulada', 'user_id' => $user->id, 'auditable_id' => $sale->id]);
        $this->assertDatabaseHas('venta_reversiones', [
            'venta_id' => $sale->id,
            'tipo' => VentaReversion::TIPO_ANULACION,
            'metodo' => 'efectivo',
            'monto' => 22,
        ]);
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame(0, app(CashRegisterSummary::class)->calculate($sale->caja)['ventas_cantidad']);
        $this->assertSame(2, $variant->fresh()->lock_version);
        $movements = StockMovimiento::query()->orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertSame(StockMovimiento::TIPO_VENTA, $movements[0]->tipo);
        $this->assertSame(-2, $movements[0]->cantidad);
        $this->assertSame(StockMovimiento::TIPO_ANULACION, $movements[1]->tipo);
        $this->assertSame('entrada', $movements[1]->direccion);
        $this->assertSame(2, $movements[1]->cantidad);
        $this->assertSame(5, $movements[1]->stock_resultante);
    }

    public function test_applies_line_and_global_discounts_and_persists_reconciled_totals(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 5);
        $payload = $this->payload($product->id, $variant->id, 2);
        $payload['items'][0]['descuento'] = 2;
        $payload['descuento_global'] = 3;

        $this->actingAs($user)->post(route('ventas.store'), $payload)->assertSessionHasNoErrors();

        $sale = $user->ventas()->with('detalles')->firstOrFail();
        $this->assertEquals(15, $sale->subtotal);
        $this->assertEquals(5, $sale->descuento);
        $this->assertEquals(1.5, $sale->igv);
        $this->assertEquals(16.5, $sale->total);
        $this->assertEquals(5, $sale->detalles->first()->descuento);
        $this->assertEquals(15, $sale->detalles->first()->subtotal);
    }

    public function test_extracts_tax_when_store_prices_already_include_it(): void
    {
        [$user, $product, $variant] = $this->salesContext(110, '', 5);
        $user->tienda->update(['precios_incluyen_igv' => true]);

        $payload = $this->payload($product->id, $variant->id);
        $payload['metodo_pago'] = 'tarjeta';
        $payload['referencia_pago'] = 'CARD-TAX-TEST';
        $payload['monto_pagado'] = 1;
        $this->actingAs($user)->post(route('ventas.store'), $payload)->assertSessionHasNoErrors();

        $sale = $user->ventas()->firstOrFail();
        $this->assertEquals(100, $sale->subtotal);
        $this->assertEquals(10, $sale->igv);
        $this->assertEquals(110, $sale->total);
        $this->assertEquals(110, $sale->monto_pagado);
        $this->assertEquals(0, $sale->vuelto);
    }

    public function test_uses_the_active_offer_price_from_the_database(): void
    {
        [$user, $product, $variant] = $this->salesContext(20);
        $product->update(['precio_oferta' => 15]);

        $this->actingAs($user)->post(
            route('ventas.store'),
            $this->payload($product->id, $variant->id)
        )->assertSessionHasNoErrors();

        $sale = $user->ventas()->with('detalles')->firstOrFail();
        $this->assertEquals(15, $sale->subtotal);
        $this->assertEquals(16.5, $sale->total);
        $this->assertEquals(15, $sale->detalles->first()->precio_unitario);
    }

    public function test_rejects_a_customer_from_another_store(): void
    {
        [$user, $product, $variant] = $this->salesContext();
        [$foreignUser] = $this->salesContext(10, 'foreign');
        $payload = $this->payload($product->id, $variant->id);
        $payload['cliente_id'] = $this->customer($foreignUser->tienda_id, 'foreign')->id;

        $response = $this->actingAs($user)->post(route('ventas.store'), $payload);

        $response->assertSessionHasErrors('cliente_id');
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_rejects_invalid_payment_receipt_and_insufficient_cash(): void
    {
        [$user, $product, $variant] = $this->salesContext();
        $payload = $this->payload($product->id, $variant->id);
        $payload['metodo_pago'] = 'criptomoneda';
        $payload['tipo_comprobante'] = 'falso';

        $this->actingAs($user)->post(route('ventas.store'), $payload)
            ->assertSessionHasErrors(['metodo_pago', 'tipo_comprobante']);

        $payload = $this->payload($product->id, $variant->id);
        $payload['monto_pagado'] = 1;
        $this->post(route('ventas.store'), $payload)->assertSessionHasErrors('monto_pagado');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_requires_authentication_to_create_a_sale(): void
    {
        $this->post(route('ventas.store'), [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_inactive_store_user_cannot_open_or_submit_the_pos(): void
    {
        [$user, $product, $variant] = $this->salesContext();
        $user->update(['activo' => false]);

        $this->actingAs($user)->get(route('ventas.create'))->assertForbidden();
        $this->post(route('ventas.store'), $this->payload($product->id, $variant->id))
            ->assertRedirect(route('login'));
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_rejects_money_values_outside_database_precision(): void
    {
        [$user, $product, $variant] = $this->salesContext();
        $payload = $this->payload($product->id, $variant->id);
        $payload['monto_pagado'] = SaleLimits::MAX_MONEY + 0.01;
        $payload['descuento_global'] = -0.01;
        $payload['items'][0]['descuento'] = 1.001;

        $this->actingAs($user)->post(route('ventas.store'), $payload)
            ->assertSessionHasErrors([
                'monto_pagado',
                'descuento_global',
                'items.0.descuento',
            ]);

        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_confirmed_sales_cannot_be_updated_or_deleted_through_policy(): void
    {
        [$user, $product, $variant] = $this->salesContext();
        $this->actingAs($user)->post(
            route('ventas.store'),
            $this->payload($product->id, $variant->id)
        )->assertSessionHasNoErrors();
        $sale = Venta::query()->sole();

        $this->assertFalse($user->can('update', $sale));
        $this->assertFalse($user->can('delete', $sale));
        $this->assertTrue($user->can('cancel', $sale));
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())
            ->contains(fn ($route) => in_array($route->getName(), ['ventas.update', 'ventas.destroy'], true)));
    }

    public function test_closed_register_sale_uses_return_flow_with_exact_stock_and_cash_reversal(): void
    {
        [$user, $product, $variant] = $this->salesContext(10, '', 5);
        $user->rol->update(['nombre' => 'Administrador']);
        $this->actingAs($user)->post(route('ventas.store'), $this->payload($product->id, $variant->id, 2))
            ->assertSessionHasNoErrors();
        $sale = $user->ventas()->firstOrFail();
        $originalRegister = $sale->caja;

        $this->post(route('caja.close', $originalRegister), ['efectivo_contado' => 22])
            ->assertSessionHas('success');
        $this->post(route('ventas.anular', $sale), ['motivo' => 'Intento en caja cerrada'])
            ->assertSessionHas('error');
        $this->post(route('ventas.devolver', $sale), ['motivo' => 'Cliente devolvió las prendas'])
            ->assertSessionHas('error');
        $this->assertSame(3, $variant->fresh()->stock);

        $returnRegister = Caja::create([
            'tienda_id' => $user->tienda_id,
            'user_id' => $user->id,
            'fecha' => today(),
            'monto_inicial' => 30,
            'estado' => 'abierta',
            'apertura' => now(),
        ]);

        $this->post(route('ventas.devolver', $sale), ['motivo' => 'Cliente devolvió las prendas'])
            ->assertSessionHas('success');
        $this->post(route('ventas.devolver', $sale), ['motivo' => 'Intento repetido'])
            ->assertSessionHas('error');

        $sale->refresh();
        $this->assertSame('devuelta', $sale->estado);
        $this->assertSame($user->id, $sale->devuelta_por);
        $this->assertSame($returnRegister->id, $sale->caja_devolucion_id);
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movimientos', [
            'referencia_id' => $sale->id,
            'tipo' => StockMovimiento::TIPO_DEVOLUCION,
            'cantidad' => 2,
        ]);
        $this->assertDatabaseHas('venta_reversiones', [
            'venta_id' => $sale->id,
            'tipo' => VentaReversion::TIPO_DEVOLUCION,
            'metodo' => 'efectivo',
            'monto' => 22,
            'caja_id' => $returnRegister->id,
        ]);
        $this->assertDatabaseHas('movimientos_caja', [
            'caja_id' => $returnRegister->id,
            'tipo' => 'egreso',
            'monto' => 22,
        ]);
        $this->assertDatabaseCount('venta_reversiones', 1);
        $this->assertEquals(22, app(CashRegisterSummary::class)->calculate($originalRegister)['total_ventas']);
        $this->assertEquals(8, app(CashRegisterSummary::class)->calculate($returnRegister)['efectivo_esperado']);
    }

    public function test_pos_marks_exhausted_size_and_color_and_selects_an_available_variant(): void
    {
        [$user, $product, $exhausted] = $this->salesContext(stock: 0);
        $available = ProductoVariante::create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => 'L',
            'color' => 'Azul',
            'stock' => 3,
            'stock_minimo' => 4,
        ]);

        $response = $this->actingAs($user)->get(route('ventas.create'))->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/value="'.$available->id.'"[^>]*selected[^>]*>\s*L \/ Azul · 3 disponibles · Stock bajo/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/value="'.$exhausted->id.'"[^>]*disabled[^>]*>\s*M \/ Negro · Agotada/s',
            $html
        );
        $response->assertSee('1 con stock bajo')->assertSee('Agregar');
    }

    public function test_cannot_sell_an_exhausted_variant_when_another_variant_has_stock(): void
    {
        [$user, $product, $exhausted] = $this->salesContext(stock: 0);
        $available = ProductoVariante::create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => 'L',
            'color' => 'Azul',
            'stock' => 5,
            'stock_minimo' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('ventas.store'), $this->payload($product->id, $exhausted->id))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(0, $exhausted->fresh()->stock);
        $this->assertSame(5, $available->fresh()->stock);
    }

    private function payload(int $productId, int $variantId, int $quantity = 1): array
    {
        return [
            'tipo_comprobante' => 'ticket',
            'metodo_pago' => 'efectivo',
            'monto_pagado' => 100,
            'items' => [[
                'producto_id' => $productId,
                'variante_id' => $variantId,
                'cantidad' => $quantity,
            ]],
        ];
    }

    private function salesContext(float $price = 10, string $suffix = '', int $stock = 10): array
    {
        $suffix = $suffix ?: uniqid();
        $role = Rol::create(['nombre' => 'Vendedor '.$suffix]);
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
            'igv' => 10,
        ]);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Vendedor',
            'email' => $suffix.'@example.test',
            'password' => 'password',
            'activo' => true,
            'es_super_admin' => false,
        ]);
        Caja::create([
            'tienda_id' => $store->id,
            'user_id' => $user->id,
            'fecha' => today(),
            'monto_inicial' => 0,
            'estado' => 'abierta',
            'apertura' => now(),
        ]);
        $category = Categoria::create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría '.$suffix,
            'slug' => 'categoria-'.$suffix,
        ]);
        $product = Producto::create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'SKU-'.$suffix,
            'nombre' => 'Producto seguro '.$suffix,
            'precio_venta' => $price,
            'activo' => true,
        ]);

        return [$user, $product, $this->variant($product, $stock)];
    }

    private function variant(Producto $product, int $stock): ProductoVariante
    {
        return ProductoVariante::create([
            'tienda_id' => $product->tienda_id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => $stock,
            'stock_minimo' => 2,
        ]);
    }

    private function customer(int $storeId, string $suffix): Cliente
    {
        return Cliente::create([
            'tienda_id' => $storeId,
            'codigo' => 'CLI-'.$suffix,
            'nombre' => 'Cliente',
            'apellido' => 'Externo',
            'activo' => true,
        ]);
    }
}
