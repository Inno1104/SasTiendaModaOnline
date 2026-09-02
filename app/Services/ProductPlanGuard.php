<?php

namespace App\Services;

use App\Exceptions\ProductPlanLimitReached;
use App\Models\Producto;
use App\Models\Tienda;

class ProductPlanGuard
{
    public function ensureActiveSlot(Tienda $lockedStore): void
    {
        $plan = $lockedStore->plan;
        if (! $plan || $plan->esIlimitado('max_productos')) {
            return;
        }

        $limit = (int) $plan->max_productos;
        $activeProducts = Producto::withoutGlobalScopes()
            ->where('tienda_id', $lockedStore->id)
            ->where('activo', true)
            ->count();

        if ($activeProducts >= $limit) {
            throw ProductPlanLimitReached::forPlan($plan->nombre, $limit);
        }
    }
}
