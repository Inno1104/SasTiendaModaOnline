<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosClientesPagosCajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_searches_clients_only_inside_the_authenticated_store(): void
    {
        [$user] = $this->context('local');
        Cliente::create(['tienda_id' => $user->tienda_id, 'codigo' => 'CLI-LOCAL', 'nombre' => 'Ana', 'apellido' => 'Local', 'activo' => true]);
        [$foreignUser] = $this->context('foreign');
        Cliente::create(['tienda_id' => $foreignUser->tienda_id, 'codigo' => 'CLI-FOREIGN', 'nombre' => 'Ana', 'apellido' => 'Externa', 'activo' => true]);

        $response = $this->actingAs($user)->getJson(route('ventas.clientes.buscar', ['q' => 'Ana']));

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.codigo', 'CLI-LOCAL');
    }

    public function test_creates_and_assigns_a_quick_client_to_the_current_store(): void
    {
        [$user] = $this->context('client');

        $response = $this->actingAs($user)->postJson(route('ventas.clientes.store'), [
            'nombre' => 'María', 'apellido' => 'Pérez', 'dni' => 'DOC-100', 'telefono' => '0999999999',
        ]);

        $response->assertCreated()->assertJsonPath('data.nombre', 'María');
        $this->assertDatabaseHas('clientes', ['tienda_id' => $user->tienda_id, 'dni' => 'DOC-100', 'activo' => 1]);
    }

    public function test_catalog_api_searches_barcode_and_paginates_large_catalogs_per_store(): void
    {
        [$user, $product, $variant] = $this->context('catalog');
        $variant->update(['codigo_barra' => 'BAR-EXACT-2026']);
        foreach (range(1, 24) as $number) {
            $extra = Producto::create([
                'tienda_id' => $user->tienda_id, 'categoria_id' => $product->categoria_id,
                'codigo' => 'EXTRA-'.$number, 'nombre' => 'Extra '.$number, 'precio_venta' => 5, 'activo' => true,
            ]);
            ProductoVariante::create(['tienda_id' => $user->tienda_id, 'producto_id' => $extra->id, 'talla' => 'U', 'color' => 'Azul', 'stock' => 1]);
        }
        $this->context('catalog-foreign');

        $page = $this->actingAs($user)->getJson(route('ventas.catalogo'));
        $search = $this->getJson(route('ventas.catalogo', ['q' => 'BAR-EXACT-2026']));

        $page->assertOk()->assertJsonPath('per_page', 24)->assertJsonPath('total', 25)->assertJsonCount(24, 'data');
        $search->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public function test_persists_combined_payments_references_and_open_cash_register(): void
    {
        [$user, $product, $variant] = $this->context('mixed');
        $cash = Caja::where('estado', 'abierta')->firstOrFail();

        $response = $this->actingAs($user)->post(route('ventas.store'), $this->salePayload($product, $variant, [
            ['metodo' => 'efectivo', 'monto' => 6],
            ['metodo' => 'tarjeta', 'monto' => 5, 'referencia' => 'CARD-2026'],
        ]));

        $response->assertSessionHasNoErrors();
        $sale = $user->ventas()->firstOrFail();
        $this->assertSame('mixto', $sale->metodo_pago);
        $this->assertCount(2, $sale->pagos);
        $this->assertSame($cash->id, $sale->caja_id);
        $this->assertDatabaseHas('pagos_venta', ['venta_id' => $sale->id, 'metodo' => 'tarjeta', 'monto' => 5, 'referencia' => 'CARD-2026']);
        $this->assertDatabaseCount('pagos_venta', 2);
    }

    public function test_rejects_incomplete_combined_payment_without_changing_stock(): void
    {
        [$user, $product, $variant] = $this->context('short');

        $response = $this->actingAs($user)->post(route('ventas.store'), $this->salePayload($product, $variant, [
            ['metodo' => 'efectivo', 'monto' => 5],
        ]));

        $response->assertSessionHasErrors('pagos');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_rejects_electronic_payment_without_reference_without_partial_changes(): void
    {
        [$user, $product, $variant] = $this->context('reference');

        $response = $this->actingAs($user)->post(route('ventas.store'), $this->salePayload($product, $variant, [
            ['metodo' => 'transferencia', 'monto' => 11],
        ]));

        $response->assertSessionHasErrors('pagos.0.referencia');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('pagos_venta', 0);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_opens_only_one_register_and_closes_it_with_the_real_cash_balance(): void
    {
        [$user, $product, $variant] = $this->context('cash', 'Administrador', false);
        $this->actingAs($user)->post(route('caja.open'), ['monto_inicial' => 100])->assertSessionHas('success');
        $this->post(route('caja.open'), ['monto_inicial' => 50])->assertSessionHasErrors('caja');
        $cash = Caja::where('estado', 'abierta')->firstOrFail();

        $this->post(route('ventas.store'), $this->salePayload($product, $variant, [
            ['metodo' => 'efectivo', 'monto' => 20],
        ]))->assertSessionHasNoErrors();
        $this->post(route('caja.close', $cash), ['efectivo_contado' => 111])->assertSessionHas('success');

        $cash->refresh();
        $this->assertSame('cerrada', $cash->estado);
        $this->assertEquals(11, $cash->total_ventas);
        $this->assertEquals(111, $cash->monto_final);
    }

    public function test_does_not_cancel_a_sale_after_its_cash_register_is_closed(): void
    {
        [$user, $product, $variant] = $this->context('closed-cash', 'Administrador', false);
        $this->actingAs($user)->post(route('caja.open'), ['monto_inicial' => 0]);
        $this->post(route('ventas.store'), $this->salePayload($product, $variant, [
            ['metodo' => 'efectivo', 'monto' => 11],
        ]));
        $sale = $user->ventas()->firstOrFail();
        $this->post(route('caja.close', $sale->caja_id), ['efectivo_contado' => 11]);

        $response = $this->post(route('ventas.anular', $sale), ['motivo' => 'Solicitud de anulación']);

        $response->assertSessionHas('error');
        $this->assertSame('completada', $sale->fresh()->estado);
        $this->assertSame(9, $variant->fresh()->stock);
    }

    private function salePayload(Producto $product, ProductoVariante $variant, array $payments): array
    {
        return ['tipo_comprobante' => 'ticket', 'pagos' => $payments, 'items' => [[
            'producto_id' => $product->id, 'variante_id' => $variant->id, 'cantidad' => 1,
        ]]];
    }

    private function context(string $suffix, ?string $roleName = null, bool $openRegister = true): array
    {
        $role = Rol::create(['nombre' => $roleName ?? 'Vendedor '.$suffix]);
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa', 'igv' => 10]);
        $user = User::create(['rol_id' => $role->id, 'tienda_id' => $store->id, 'name' => 'Vendedor', 'email' => $suffix.'@test.dev', 'password' => 'password', 'activo' => true]);
        $category = Categoria::create(['tienda_id' => $store->id, 'nombre' => 'Categoría '.$suffix, 'slug' => 'cat-'.$suffix]);
        $product = Producto::create(['tienda_id' => $store->id, 'categoria_id' => $category->id, 'codigo' => 'SKU-'.$suffix, 'nombre' => 'Producto '.$suffix, 'precio_venta' => 10, 'activo' => true]);
        $variant = ProductoVariante::create(['tienda_id' => $store->id, 'producto_id' => $product->id, 'talla' => 'M', 'color' => 'Negro', 'stock' => 10]);
        if ($openRegister) {
            Caja::create([
                'tienda_id' => $store->id,
                'user_id' => $user->id,
                'fecha' => today(),
                'monto_inicial' => 0,
                'estado' => 'abierta',
                'apertura' => now(),
            ]);
        }

        return [$user, $product, $variant];
    }
}
