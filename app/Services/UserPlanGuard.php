<?php

namespace App\Services;

use App\Exceptions\UserPlanLimitReached;
use App\Models\Tienda;
use App\Models\User;

final class UserPlanGuard
{
    public function ensureSlot(Tienda $lockedStore): void
    {
        $plan = $lockedStore->plan;
        if (! $plan || $plan->esIlimitado('max_usuarios')) {
            return;
        }

        $limit = (int) $plan->max_usuarios;
        $registeredUsers = User::query()
            ->where('tienda_id', $lockedStore->id)
            ->where('es_super_admin', false)
            ->count();

        if ($registeredUsers >= $limit) {
            throw UserPlanLimitReached::forPlan($plan->nombre, $limit);
        }
    }
}
