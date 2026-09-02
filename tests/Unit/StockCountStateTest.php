<?php

namespace Tests\Unit;

use App\Support\StockCountState;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockCountStateTest extends TestCase
{
    #[DataProvider('transitions')]
    public function test_only_pending_counts_can_reach_a_terminal_state(string $from, string $to, bool $expected): void
    {
        $this->assertSame($expected, StockCountState::canTransition($from, $to));
    }

    public static function transitions(): array
    {
        return [
            'pending to reconciled' => [StockCountState::PENDING, StockCountState::RECONCILED, true],
            'pending to replaced' => [StockCountState::PENDING, StockCountState::REPLACED, true],
            'reconciled is terminal' => [StockCountState::RECONCILED, StockCountState::REPLACED, false],
            'replaced is terminal' => [StockCountState::REPLACED, StockCountState::RECONCILED, false],
        ];
    }
}
