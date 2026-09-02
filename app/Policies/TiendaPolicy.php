<?php

namespace App\Policies;

use App\Models\Tienda;
use App\Models\User;

final class TiendaPolicy
{
    public function view(User $user, Tienda $store): bool
    {
        return $this->belongsToStore($user, $store);
    }

    public function update(User $user, Tienda $store): bool
    {
        return $this->belongsToStore($user, $store) && $user->isAdmin();
    }

    private function belongsToStore(User $user, Tienda $store): bool
    {
        return ! $user->es_super_admin
            && $user->activo
            && $user->tienda_id !== null
            && (int) $user->tienda_id === (int) $store->id;
    }
}
