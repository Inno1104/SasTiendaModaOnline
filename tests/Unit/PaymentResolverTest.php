<?php

namespace Tests\Unit;

use App\Services\PaymentResolver;
use App\Support\SaleLimits;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentResolverTest extends TestCase
{
    public function test_resolves_an_exact_electronic_payment(): void
    {
        $result = app(PaymentResolver::class)->resolve(['pagos' => [[
            'metodo' => 'tarjeta', 'monto' => 25, 'referencia' => 'OP-100',
        ]]], 25);

        $this->assertSame('tarjeta', $result['method']);
        $this->assertEquals(25, $result['paid']);
        $this->assertEquals(0, $result['change']);
        $this->assertSame('OP-100', $result['payments']->first()['referencia']);
    }

    public function test_resolves_combined_payments(): void
    {
        $result = app(PaymentResolver::class)->resolve(['pagos' => [
            ['metodo' => 'efectivo', 'monto' => 10],
            ['metodo' => 'tarjeta', 'monto' => 15, 'referencia' => 'CARD-1'],
        ]], 25);

        $this->assertSame('mixto', $result['method']);
        $this->assertTrue($result['mixed']);
        $this->assertEquals(25, $result['paid']);
        $this->assertEquals(25, $result['net_paid']);
        $this->assertEquals(10, $result['cash_received']);
    }

    public function test_allows_change_only_when_covered_by_cash(): void
    {
        $result = app(PaymentResolver::class)->resolve(['pagos' => [
            ['metodo' => 'efectivo', 'monto' => 15],
            ['metodo' => 'tarjeta', 'monto' => 10, 'referencia' => 'CARD-CHANGE'],
        ]], 20);

        $this->assertEquals(5, $result['change']);
    }

    public function test_rejects_incomplete_payments(): void
    {
        $this->expectException(ValidationException::class);
        app(PaymentResolver::class)->resolve(['pagos' => [['metodo' => 'efectivo', 'monto' => 9]]], 10);
    }

    public function test_rejects_change_generated_only_by_electronic_payments(): void
    {
        $this->expectException(ValidationException::class);
        app(PaymentResolver::class)->resolve(['pagos' => [['metodo' => 'tarjeta', 'monto' => 11]]], 10);
    }

    public function test_rejects_payment_sum_above_supported_database_amount(): void
    {
        $this->expectException(ValidationException::class);

        app(PaymentResolver::class)->resolve(['pagos' => [
            ['metodo' => 'efectivo', 'monto' => SaleLimits::MAX_MONEY],
            ['metodo' => 'efectivo', 'monto' => 0.01],
        ]], SaleLimits::MAX_MONEY);
    }

    public function test_electronic_payment_requires_reference_even_outside_http(): void
    {
        $this->expectException(ValidationException::class);

        app(PaymentResolver::class)->resolve(['pagos' => [
            ['metodo' => 'transferencia', 'monto' => 10],
        ]], 10);
    }

    public function test_cash_reference_is_discarded_and_payment_reconciles_with_change(): void
    {
        $result = app(PaymentResolver::class)->resolve(['pagos' => [
            ['metodo' => 'efectivo', 'monto' => 20, 'referencia' => 'NO-DEBE-GUARDARSE'],
        ]], 15);

        $this->assertNull($result['payments']->first()['referencia']);
        $this->assertEquals(20, $result['paid']);
        $this->assertEquals(5, $result['change']);
        $this->assertEquals(15, $result['net_paid']);
    }
}
