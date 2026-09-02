<?php

namespace Tests\Unit;

use App\Services\CommercialCalculator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommercialCalculatorTest extends TestCase
{
    private CommercialCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CommercialCalculator();
    }

    public function test_calculates_tax_excluded_prices(): void
    {
        $result = $this->calculator->calculate($this->lines(100, 2), 0, 18, false);

        $this->assertSame(200.0, $result['subtotal']);
        $this->assertSame(36.0, $result['tax']);
        $this->assertSame(236.0, $result['total']);
    }

    public function test_extracts_tax_from_tax_included_prices(): void
    {
        $result = $this->calculator->calculate($this->lines(118, 1), 0, 18, true);

        $this->assertSame(100.0, $result['subtotal']);
        $this->assertSame(18.0, $result['tax']);
        $this->assertSame(118.0, $result['total']);
    }

    public function test_applies_line_and_global_discounts_before_tax(): void
    {
        $result = $this->calculator->calculate($this->lines(50, 2, 10), 15, 10, false);

        $this->assertSame(25.0, $result['discount']);
        $this->assertSame(75.0, $result['subtotal']);
        $this->assertSame(7.5, $result['tax']);
        $this->assertSame(82.5, $result['total']);
        $this->assertSame(25.0, $result['lines']->first()['discount']);
    }

    public function test_allocates_global_discount_without_losing_cents(): void
    {
        $lines = collect([
            ['unit_price' => 0.05, 'quantity' => 1, 'discount' => 0],
            ['unit_price' => 0.05, 'quantity' => 1, 'discount' => 0],
            ['unit_price' => 0.05, 'quantity' => 1, 'discount' => 0],
        ]);

        $result = $this->calculator->calculate($lines, 0.10, 0, false);

        $this->assertSame(0.10, $result['discount']);
        $this->assertSame(0.05, $result['total']);
        $this->assertSame(0.10, round((float) $result['lines']->sum('discount'), 2));
    }

    public function test_rejects_discounts_greater_than_the_sale_amount(): void
    {
        $this->expectException(ValidationException::class);

        $this->calculator->calculate($this->lines(10, 1), 11, 18, false);
    }

    private function lines(float $price, int $quantity, float $discount = 0): Collection
    {
        return collect([[
            'unit_price' => $price,
            'quantity' => $quantity,
            'discount' => $discount,
        ]]);
    }
}
