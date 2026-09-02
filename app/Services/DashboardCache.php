<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

final class DashboardCache
{
    public function remember(int $storeId, array $period, Closure $callback): array
    {
        $version = Cache::get($this->versionKey($storeId), 1);
        $periodKey = implode(':', [
            $period['start']->toDateString(), $period['end']->toDateString(),
            $period['previous_start']->toDateString(), $period['previous_end']->toDateString(),
        ]);
        $key = "dashboard:data:store:{$storeId}:v{$version}:" . sha1($periodKey);
        $seconds = max(1, (int) config('cache.dashboard_ttl', 120));

        return Cache::remember($key, now()->addSeconds($seconds), $callback);
    }

    public function invalidate(?int $storeId): void
    {
        if (!$storeId) {
            return;
        }
        $key = $this->versionKey($storeId);
        Cache::forever($key, ((int) Cache::get($key, 1)) + 1);
    }

    private function versionKey(int $storeId): string
    {
        return "dashboard:version:store:{$storeId}";
    }
}
