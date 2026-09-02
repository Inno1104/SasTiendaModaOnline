<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

class VentaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveStore($user);
    }

    public function view(User $user, Venta $venta): bool
    {
        return $this->belongsToActiveStore($user)
            && (int) $user->tienda_id === (int) $venta->tienda_id;
    }

    public function create(User $user): bool
    {
        return $this->belongsToActiveStore($user);
    }

    /** Confirmed sales are immutable; cancellation is the only supported transition. */
    public function update(User $user, Venta $venta): bool
    {
        return false;
    }

    public function delete(User $user, Venta $venta): bool
    {
        return false;
    }

    public function restore(User $user, Venta $venta): bool
    {
        return false;
    }

    public function forceDelete(User $user, Venta $venta): bool
    {
        return false;
    }

    public function cancel(User $user, Venta $venta): bool
    {
        if (! $this->view($user, $venta)) {
            return false;
        }

        return $this->isAdministrator($user) || (int) $venta->user_id === (int) $user->id;
    }

    public function return(User $user, Venta $venta): bool
    {
        return $this->view($user, $venta) && $this->isAdministrator($user);
    }

    private function belongsToActiveStore(User $user): bool
    {
        return ! $user->es_super_admin && $user->activo && $user->tienda_id !== null;
    }

    private function isAdministrator(User $user): bool
    {
        return mb_strtolower((string) $user->rol?->nombre) === 'administrador';
    }
}
