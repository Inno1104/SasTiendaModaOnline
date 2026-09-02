<?php

namespace Tests\Unit;

use App\Services\KpiComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KpiComparisonTest extends TestCase
{
    #[DataProvider('comparisonProvider')]
    public function test_it_formats_real_comparisons(
        float $current,
        float $previous,
        string $direction,
        ?float $percentage,
        string $text
    ): void {
        $result = (new KpiComparison())->between($current, $previous, 'ayer');

        $this->assertSame($direction, $result['direction']);
        $this->assertSame($percentage, $result['percentage']);
        $this->assertSame($text, $result['text']);
    }

    public static function comparisonProvider(): array
    {
        return [
            'increase' => [120, 100, 'up', 20.0, '20% vs ayer'],
            'decrease' => [75, 100, 'down', -25.0, '25% vs ayer'],
            'unchanged' => [100, 100, 'neutral', 0.0, '0% vs ayer'],
            'both zero' => [0, 0, 'neutral', null, 'Sin cambios vs ayer'],
            'previous zero' => [50, 0, 'up', null, 'No hubo ventas ayer'],
            'decimal rounding' => [110, 90, 'up', 22.2, '22.2% vs ayer'],
        ];
    }
}
