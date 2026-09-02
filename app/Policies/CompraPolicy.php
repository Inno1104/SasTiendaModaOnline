<?php

namespace App\Policies;

use App\Models\Compra;
use App\Models\User;

class CompraPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManagePurchases($user);
    }

    public function view(User $user, Compra $purchase): bool
    {
        return $this->canManagePurchases($user)
            && (int) $user->tienda_id === (int) $purchase->tienda_id;
    }

    public function create(User $user): bool
    {
        return $this->canManagePurchases($user);
    }

    public function cancel(User $user, Compra $purchase): bool
    {
        return $this->view($user, $purchase)
            && $this->roleName($user) === 'administrador';
    }

    private function canManagePurchases(User $user): bool
    {
        return ! $user->es_super_admin
            && $user->activo
            && $user->tienda_id !== null
            && in_array($this->roleName($user), ['administrador', 'almacén', 'almacen'], true);
    }

    private function roleName(User $user): string
    {
        return mb_strtolower(trim((string) $user->rol?->nombre));
    }
}
