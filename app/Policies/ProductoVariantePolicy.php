<?php

namespace App\Policies;

use App\Models\ProductoVariante;
use App\Models\User;

class ProductoVariantePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasActiveStore($user);
    }

    public function update(User $user, ProductoVariante $variant): bool
    {
        return $this->hasActiveStore($user)
            && (int) $user->tienda_id === (int) $variant->tienda_id
            && in_array($this->roleName($user), ['administrador', 'almacén', 'almacen'], true);
    }

    public function reconcileStock(User $user, ProductoVariante $variant): bool
    {
        return $this->hasActiveStore($user)
            && (int) $user->tienda_id === (int) $variant->tienda_id
            && $this->roleName($user) === 'administrador';
    }

    private function hasActiveStore(User $user): bool
    {
        return ! $user->es_super_admin && $user->activo && $user->tienda_id !== null;
    }

    private function roleName(User $user): string
    {
        return mb_strtolower(trim((string) $user->rol?->nombre));
    }
}
