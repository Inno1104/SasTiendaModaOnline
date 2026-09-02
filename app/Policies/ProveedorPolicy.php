<?php

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;

class ProveedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->belongsToActiveStore($user);
    }

    public function view(User $user, Proveedor $supplier): bool
    {
        return $this->belongsToUserStore($user, $supplier);
    }

    public function create(User $user): bool
    {
        return $this->canManageSuppliers($user);
    }

    public function update(User $user, Proveedor $supplier): bool
    {
        return $this->belongsToUserStore($user, $supplier)
            && $this->canManageSuppliers($user);
    }

    public function delete(User $user, Proveedor $supplier): bool
    {
        return $this->update($user, $supplier);
    }

    private function belongsToUserStore(User $user, Proveedor $supplier): bool
    {
        return $this->belongsToActiveStore($user)
            && (int) $user->tienda_id === (int) $supplier->tienda_id;
    }

    private function canManageSuppliers(User $user): bool
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
