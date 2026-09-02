<?php

namespace App\Policies;

use App\Models\Marca;
use App\Models\User;

class MarcaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveStore($user);
    }

    public function view(User $user, Marca $brand): bool
    {
        return $this->belongsToUserStore($user, $brand);
    }

    public function create(User $user): bool
    {
        return $this->canManageCatalog($user);
    }

    public function update(User $user, Marca $brand): bool
    {
        return $this->belongsToUserStore($user, $brand) && $this->canManageCatalog($user);
    }

    public function delete(User $user, Marca $brand): bool
    {
        return $this->update($user, $brand);
    }

    private function belongsToUserStore(User $user, Marca $brand): bool
    {
        return $this->belongsToActiveStore($user)
            && (int) $user->tienda_id === (int) $brand->tienda_id;
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
