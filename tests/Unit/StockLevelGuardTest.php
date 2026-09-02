<?php

namespace Tests\Unit;

use App\Models\ProductoVariante;
use App\Services\StockLevelGuard;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockLevelGuardTest extends TestCase
{
    #[DataProvider('validLevels')]
    public function test_accepts_stock_boundaries(int $stock): void
    {
        $this->assertSame($stock, (new StockLevelGuard)->ensureValid($stock));
    }

    public static function validLevels(): array
    {
        return [
            'minimum' => [ProductoVariante::MIN_STOCK],
            'maximum' => [ProductoVariante::MAX_STOCK],
        ];
    }

    public function test_rejects_overflow_before_adding(): void
    {
        $this->expectException(ValidationException::class);
        (new StockLevelGuard)->afterIncrease(ProductoVariante::MAX_STOCK, 1);
    }

    public function test_decreases_stock_at_the_exact_boundary(): void
    {
        $this->assertSame(0, (new StockLevelGuard)->afterDecrease(5, 5));
    }

    public function test_rejects_a_decrease_that_would_create_negative_stock(): void
    {
        $this->expectException(ValidationException::class);
        (new StockLevelGuard)->afterDecrease(4, 5);
    }
}
