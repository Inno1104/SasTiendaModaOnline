<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use App\Services\ComprobanteNumberGenerator;
use App\Support\DocumentNumbering;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ComprobanteNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_an_active_transaction(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('transacción activa');

        $this->generator()->next($this->store('transaction'), 'ticket');
    }

    public function test_generates_sequential_correlatives_for_the_same_store_and_type(): void
    {
        $store = $this->store('one');
        $first = DB::transaction(fn () => $this->generator()->next($store, 'boleta'));
        $second = DB::transaction(fn () => $this->generator()->next($store, 'boleta'));

        $this->assertSame('B001-00000001', $first['numero_venta']);
        $this->assertSame('00000002', $second['correlativo']);
        $this->assertDatabaseHas('comprobante_contadores', [
            'tienda_id' => $store->id, 'tipo' => 'boleta', 'serie' => 'B001', 'ultimo_numero' => 2,
        ]);
    }

    public function test_maintains_independent_sequences_for_each_receipt_type(): void
    {
        $store = $this->store('types');
        $boleta = DB::transaction(fn () => $this->generator()->next($store, 'boleta'));
        $factura = DB::transaction(fn () => $this->generator()->next($store, 'factura'));
        $ticket = DB::transaction(fn () => $this->generator()->next($store, 'ticket'));

        $this->assertSame('B001-00000001', $boleta['numero_venta']);
        $this->assertSame('F001-00000001', $factura['numero_venta']);
        $this->assertSame('T001-00000001', $ticket['numero_venta']);
    }

    public function test_allows_the_same_number_in_different_stores(): void
    {
        $first = DB::transaction(fn () => $this->generator()->next($this->store('first'), 'ticket'));
        $second = DB::transaction(fn () => $this->generator()->next($this->store('second'), 'ticket'));

        $this->assertSame('T001-00000001', $first['numero_venta']);
        $this->assertSame($first['numero_venta'], $second['numero_venta']);
        $this->assertDatabaseCount('comprobante_contadores', 2);
    }

    public function test_uses_custom_series_from_each_store(): void
    {
        $store = $this->store('custom', [
            'serie_boleta' => 'BV-02', 'serie_factura' => 'FV-09', 'serie_ticket' => 'PV-01',
        ]);

        $this->assertSame('BV-02-00000001', DB::transaction(fn () => $this->generator()->next($store, 'boleta'))['numero_venta']);
        $this->assertSame('FV-09-00000001', DB::transaction(fn () => $this->generator()->next($store, 'factura'))['numero_venta']);
        $this->assertSame('PV-01-00000001', DB::transaction(fn () => $this->generator()->next($store, 'ticket'))['numero_venta']);
    }

    public function test_rolls_back_the_counter_when_the_sale_transaction_fails(): void
    {
        $store = $this->store('rollback');

        try {
            DB::transaction(function () use ($store) {
                $this->generator()->next($store, 'boleta');
                throw new RuntimeException('Fallo simulado');
            });
        } catch (RuntimeException) {
            // La transacción debe revertir también el contador.
        }

        $number = DB::transaction(fn () => $this->generator()->next($store, 'boleta'));
        $this->assertSame('B001-00000001', $number['numero_venta']);
    }

    public function test_uses_a_continuous_sequence_when_the_year_changes(): void
    {
        $store = $this->store('continuous');
        Carbon::setTestNow('2026-12-31 23:59:59');
        $first = DB::transaction(fn () => $this->generator()->next($store, 'ticket'));
        Carbon::setTestNow('2027-01-01 00:00:01');
        $second = DB::transaction(fn () => $this->generator()->next($store, 'ticket'));
        Carbon::setTestNow();

        $this->assertSame('continuous', DocumentNumbering::POLICY);
        $this->assertSame('T001-00000001', $first['numero_venta']);
        $this->assertSame('T001-00000002', $second['numero_venta']);
    }

    public function test_rejects_correlative_overflow_without_advancing_the_counter(): void
    {
        $store = $this->store('overflow');
        DB::table('comprobante_contadores')->insert([
            'tienda_id' => $store->id,
            'tipo' => 'factura',
            'serie' => 'F001',
            'ultimo_numero' => DocumentNumbering::MAX_CORRELATIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::transaction(fn () => $this->generator()->next($store, 'factura'));
            $this->fail('El desbordamiento debió ser rechazado.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('agotó', $exception->getMessage());
        }

        $this->assertDatabaseHas('comprobante_contadores', [
            'tienda_id' => $store->id,
            'tipo' => 'factura',
            'serie' => 'F001',
            'ultimo_numero' => DocumentNumbering::MAX_CORRELATIVE,
        ]);
    }

    public function test_database_rejects_duplicate_sale_numbers_inside_the_same_store(): void
    {
        $store = $this->store('unique-number');
        $role = Rol::create(['nombre' => 'Vendedor numeración']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Vendedor',
            'email' => 'numbering@example.test',
            'password' => 'password',
            'activo' => true,
        ]);
        $attributes = [
            'tienda_id' => $store->id,
            'numero_venta' => 'T001-00000001',
            'user_id' => $user->id,
            'fecha' => today(),
            'tipo_comprobante' => 'ticket',
            'serie' => 'T001',
            'correlativo' => '00000001',
        ];

        Venta::withoutGlobalScopes()->create($attributes);

        $this->expectException(QueryException::class);
        Venta::withoutGlobalScopes()->create(array_merge($attributes, [
            'tipo_comprobante' => 'boleta',
            'serie' => 'B001',
            'correlativo' => '00000002',
        ]));
    }

    private function generator(): ComprobanteNumberGenerator
    {
        return app(ComprobanteNumberGenerator::class);
    }

    private function store(string $suffix, array $attributes = []): Tienda
    {
        return Tienda::create(array_merge([
            'nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa',
        ], $attributes));
    }
}
