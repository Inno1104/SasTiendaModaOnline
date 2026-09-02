<?php

namespace App\Policies;

use App\Models\Caja;
use App\Models\User;

class CajaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveStore($user);
    }

    public function open(User $user): bool
    {
        return $this->belongsToActiveStore($user) && $this->canOperateRegister($user);
    }

    public function view(User $user, Caja $caja): bool
    {
        return $this->belongsToActiveStore($user)
            && (int) $user->tienda_id === (int) $caja->tienda_id;
    }

    public function close(User $user, Caja $caja): bool
    {
        if (! $this->belongsToActiveStore($user) || (int) $user->tienda_id !== (int) $caja->tienda_id) {
            return false;
        }

        return $this->isAdministrator($user) || (int) $caja->user_id === (int) $user->id;
    }

    public function manageMovements(User $user, Caja $caja): bool
    {
        return $this->close($user, $caja);
    }

    private function belongsToActiveStore(User $user): bool
    {
        return ! $user->es_super_admin && $user->activo && $user->tienda_id !== null;
    }

    private function canOperateRegister(User $user): bool
    {
        return in_array($this->roleName($user), ['administrador', 'cajero'], true);
    }

    private function isAdministrator(User $user): bool
    {
        return $this->roleName($user) === 'administrador';
    }

    private function roleName(User $user): string
    {
        return mb_strtolower(trim((string) $user->rol?->nombre));
    }
}
