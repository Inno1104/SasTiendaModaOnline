<?php

namespace App\Observers;

use App\Services\DashboardCache;
use Illuminate\Database\Eloquent\Model;

class DashboardCacheObserver
{
    public bool $afterCommit = true;

    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        app(DashboardCache::class)->invalidate((int) $model->getAttribute('tienda_id'));
    }
}
