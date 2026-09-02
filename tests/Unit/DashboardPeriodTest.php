<?php

namespace Tests\Unit;

use App\Services\DashboardPeriod;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DashboardPeriodTest extends TestCase
{
    #[DataProvider('predefinedPeriods')]
    public function test_it_resolves_predefined_periods(string $key, string $start, string $end): void
    {
        $period = (new DashboardPeriod())->resolve($key, today: Carbon::parse('2026-08-23'));

        $this->assertSame($start, $period['start']->toDateString());
        $this->assertSame($end, $period['end']->toDateString());
    }

    public static function predefinedPeriods(): array
    {
        return [
            'today' => ['hoy', '2026-08-23', '2026-08-23'],
            'seven days' => ['7_dias', '2026-08-17', '2026-08-23'],
            'thirty days' => ['30_dias', '2026-07-25', '2026-08-23'],
            'current month' => ['mes_actual', '2026-08-01', '2026-08-23'],
            'previous month' => ['mes_anterior', '2026-07-01', '2026-07-31'],
        ];
    }

    public function test_previous_period_has_the_same_number_of_days(): void
    {
        $period = (new DashboardPeriod())->resolve('7_dias', today: Carbon::parse('2026-08-23'));

        $this->assertSame('2026-08-10', $period['previous_start']->toDateString());
        $this->assertSame('2026-08-16', $period['previous_end']->toDateString());
    }

    public function test_it_resolves_a_custom_period(): void
    {
        $period = (new DashboardPeriod())->resolve('personalizado', '2026-08-05', '2026-08-12');

        $this->assertSame('2026-08-05', $period['start']->toDateString());
        $this->assertSame('2026-08-12', $period['end']->toDateString());
        $this->assertSame('05/08/2026 – 12/08/2026', $period['label']);
    }

    public function test_it_rejects_an_inverted_custom_period(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DashboardPeriod())->resolve('personalizado', '2026-08-12', '2026-08-05');
    }
}
