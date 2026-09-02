<?php

namespace Tests\Unit;

use App\Services\PurchaseCalculator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseCalculatorTest extends TestCase
{
    public function test_calculates_money_using_exact_cents(): void
    {
        $lines = $this->calculator()->calculate([
            ['variante_id' => 1, 'cantidad' => 3, 'precio' => '0.10'],
            ['variante_id' => 2, 'cantidad' => 3, 'precio' => '0.20'],
        ]);

        $this->assertSame('0.30', $lines[0]['subtotal']);
        $this->assertSame('0.60', $lines[1]['subtotal']);
        $this->assertSame('0.90', $this->calculator()->total($lines));
    }

    public function test_consolidates_equivalent_duplicate_lines(): void
    {
        $lines = $this->calculator()->calculate([
            ['variante_id' => 7, 'cantidad' => 2, 'precio' => '10'],
            ['variante_id' => 7, 'cantidad' => 3, 'precio' => '10.00'],
        ]);

        $this->assertCount(1, $lines);
        $this->assertSame([
            'variante_id' => 7,
            'cantidad' => 5,
            'precio' => '10.00',
            'subtotal' => '50.00',
        ], $lines->first());
    }

    public function test_output_order_is_stable_by_variant(): void
    {
        $lines = $this->calculator()->calculate([
            ['variante_id' => 9, 'cantidad' => 1, 'precio' => '1.00'],
            ['variante_id' => 2, 'cantidad' => 1, 'precio' => '1.00'],
        ]);

        $this->assertSame([2, 9], $lines->pluck('variante_id')->all());
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_invalid_monetary_formats(mixed $amount): void
    {
        $this->expectException(ValidationException::class);
        $this->calculator()->calculate([
            ['variante_id' => 1, 'cantidad' => 1, 'precio' => $amount],
        ]);
    }

    public static function invalidAmounts(): array
    {
        return [
            'negative' => ['-1.00'],
            'excess precision' => ['1.001'],
            'scientific notation' => ['1e2'],
            'comma decimal' => ['1,25'],
            'empty' => [''],
        ];
    }

    public function test_rejects_different_prices_for_the_same_variant(): void
    {
        $this->expectException(ValidationException::class);
        $this->calculator()->calculate([
            ['variante_id' => 1, 'cantidad' => 1, 'precio' => '10.00'],
            ['variante_id' => 1, 'cantidad' => 1, 'precio' => '10.01'],
        ]);
    }

    public function test_rejects_an_empty_purchase_when_service_is_called_directly(): void
    {
        $this->expectException(ValidationException::class);
        $this->calculator()->calculate([]);
    }

    private function calculator(): PurchaseCalculator
    {
        return app(PurchaseCalculator::class);
    }
}
