<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageCustomers($user);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $this->belongsToUserStore($user, $cliente);
    }

    public function create(User $user): bool
    {
        return $this->canManageCustomers($user);
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $this->belongsToUserStore($user, $cliente);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $this->changeStatus($user, $cliente);
    }

    public function changeStatus(User $user, Cliente $cliente): bool
    {
        return $this->belongsToUserStore($user, $cliente)
            && $this->roleName($user) === 'administrador';
    }

    private function belongsToUserStore(User $user, Cliente $cliente): bool
    {
        return $this->canManageCustomers($user)
            && (int) $user->tienda_id === (int) $cliente->tienda_id;
    }

    private function canManageCustomers(User $user): bool
    {
        return ! $user->es_super_admin
            && $user->activo
            && $user->tienda_id !== null
            && in_array($this->roleName($user), ['administrador', 'vendedor'], true);
    }

    private function roleName(User $user): string
    {
        return mb_strtolower(trim((string) $user->rol?->nombre));
    }
}
