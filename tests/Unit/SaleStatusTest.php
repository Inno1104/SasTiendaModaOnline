<?php

namespace Tests\Unit;

use App\Support\SaleStatus;
use PHPUnit\Framework\TestCase;

class SaleStatusTest extends TestCase
{
    public function test_only_completed_sales_can_transition_to_cancelled_or_returned(): void
    {
        $this->assertTrue(SaleStatus::canCancel(SaleStatus::COMPLETED));
        $this->assertTrue(SaleStatus::canReturn(SaleStatus::COMPLETED));

        foreach ([SaleStatus::PENDING, SaleStatus::CANCELLED, SaleStatus::RETURNED] as $state) {
            $this->assertFalse(SaleStatus::canCancel($state));
            $this->assertFalse(SaleStatus::canReturn($state));
        }
    }
}
