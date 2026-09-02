<?php

namespace App\Policies;

use App\Models\User;

final class UsuarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function view(User $user, User $managedUser): bool
    {
        return $this->belongsToUserStore($user, $managedUser);
    }

    public function create(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function update(User $user, User $managedUser): bool
    {
        return $this->belongsToUserStore($user, $managedUser);
    }

    public function changeStatus(User $user, User $managedUser): bool
    {
        return $this->belongsToUserStore($user, $managedUser)
            && (int) $user->id !== (int) $managedUser->id;
    }

    private function belongsToUserStore(User $user, User $managedUser): bool
    {
        return $this->canManageUsers($user)
            && ! $managedUser->es_super_admin
            && $managedUser->tienda_id !== null
            && (int) $user->tienda_id === (int) $managedUser->tienda_id;
    }

    private function canManageUsers(User $user): bool
    {
        return ! $user->es_super_admin
            && $user->activo
            && $user->tienda_id !== null
            && $user->isAdmin();
    }
}
