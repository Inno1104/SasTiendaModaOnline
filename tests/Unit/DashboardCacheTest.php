<?php

namespace Tests\Unit;

use App\Services\DashboardCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::clear();
    }

    public function test_it_reuses_dashboard_data_for_the_same_store_and_period(): void
    {
        $calls = 0;
        $cache = app(DashboardCache::class);
        $period = $this->period();

        $first = $cache->remember(10, $period, function () use (&$calls) {
            $calls++;

            return ['sales' => 125];
        });
        $second = $cache->remember(10, $period, function () use (&$calls) {
            $calls++;

            return ['sales' => 999];
        });

        $this->assertSame(['sales' => 125], $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
    }

    public function test_invalidation_only_refreshes_the_affected_store(): void
    {
        $cache = app(DashboardCache::class);
        $period = $this->period();

        $cache->remember(10, $period, fn () => ['sales' => 100]);
        $cache->remember(20, $period, fn () => ['sales' => 200]);
        $cache->invalidate(10);

        $this->assertSame(
            ['sales' => 101],
            $cache->remember(10, $period, fn () => ['sales' => 101])
        );
        $this->assertSame(
            ['sales' => 200],
            $cache->remember(20, $period, fn () => ['sales' => 201])
        );
    }

    private function period(): array
    {
        return [
            'start' => Carbon::parse('2026-08-17')->startOfDay(),
            'end' => Carbon::parse('2026-08-23')->endOfDay(),
            'previous_start' => Carbon::parse('2026-08-10')->startOfDay(),
            'previous_end' => Carbon::parse('2026-08-16')->endOfDay(),
        ];
    }
}
