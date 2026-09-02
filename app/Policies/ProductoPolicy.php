<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;

class ProductoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveStore($user);
    }

    public function view(User $user, Producto $product): bool
    {
        return $this->belongsToUserStore($user, $product);
    }

    public function create(User $user): bool
    {
        return $this->canManageCatalog($user);
    }

    public function update(User $user, Producto $product): bool
    {
        return $this->belongsToUserStore($user, $product) && $this->canManageCatalog($user);
    }

    public function delete(User $user, Producto $product): bool
    {
        return $this->update($user, $product);
    }

    private function belongsToUserStore(User $user, Producto $product): bool
    {
        return $this->belongsToActiveStore($user)
            && (int) $user->tienda_id === (int) $product->tienda_id;
    }

    private function canManageCatalog(User $user): bool
    {
        return $this->belongsToActiveStore($user)
            && in_array($this->roleName($user), ['administrador', 'almacén', 'almacen'], true);
    }

    private function belongsToActiveStore(User $user): bool
    {
        return ! $user->es_super_admin && $user->activo && $user->tienda_id !== null;
    }

    private function roleName(User $user): string
    {
        return mb_strtolower(trim((string) $user->rol?->nombre));
    }
}
