<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UserAdministrationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UserPlanGuard $planGuard
    ) {}

    public function create(int $storeId, array $data, User $actor): User
    {
        return DB::transaction(function () use ($storeId, $data, $actor): User {
            $store = Tienda::query()->with('plan')->lockForUpdate()->findOrFail($storeId);
            $this->planGuard->ensureSlot($store);

            $user = User::create([
                'tienda_id' => $storeId,
                'name' => $data['name'],
                'email' => $data['email'],
                'rol_id' => $data['rol_id'],
                'telefono' => $data['telefono'] ?? null,
                'password' => $data['password'],
                'activo' => true,
            ]);

            $this->audit->record(
                $user,
                'usuario.creado',
                'Usuario creado.',
                $actor,
                ['nombre' => $user->name, 'email' => $user->email, 'rol_id' => $user->rol_id]
            );

            return $user;
        }, 5);
    }

    public function update(User $managedUser, array $data, User $actor): User
    {
        return DB::transaction(function () use ($managedUser, $data, $actor): User {
            $this->lockStore($managedUser);
            $user = $this->lockedUser($managedUser);
            $previous = $user->only(['name', 'email', 'rol_id', 'telefono']);

            $newRole = Rol::query()->findOrFail($data['rol_id']);
            $removesActiveAdministrator = $user->activo
                && $user->isAdmin()
                && ! $this->isAdministratorRole($newRole);

            if ($removesActiveAdministrator) {
                $this->ensureAnotherActiveAdministratorExists($user);
            }

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'rol_id' => $newRole->id,
                'telefono' => $data['telefono'] ?? null,
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            if (! $user->isDirty()) {
                return $user;
            }

            $passwordChanged = $user->isDirty('password');
            $user->save();
            $changes = array_intersect_key($user->only(['name', 'email', 'rol_id', 'telefono']), $user->getChanges());

            $this->audit->record(
                $user,
                'usuario.actualizado',
                'Datos del usuario actualizados.',
                $actor,
                ['anterior' => array_intersect_key($previous, $changes), 'nuevo' => $changes, 'contrasena_cambiada' => $passwordChanged]
            );

            return $user->refresh();
        }, 5);
    }

    public function changeStatus(User $managedUser, bool $active, string $reason, User $actor): User
    {
        $user = DB::transaction(function () use ($managedUser, $active, $reason, $actor): User {
            $this->lockStore($managedUser);
            $user = $this->lockedUser($managedUser);

            if ($user->activo === $active) {
                throw ValidationException::withMessages([
                    'activo' => $active ? 'El usuario ya se encuentra activo.' : 'El usuario ya se encuentra inactivo.',
                ]);
            }

            if (! $active && $user->isAdmin()) {
                $this->ensureAnotherActiveAdministratorExists($user, 'activo');
            }

            $previousStatus = $user->activo;
            $user->activo = $active;
            if (! $active) {
                $user->setRememberToken(Str::random(60));
            }
            $user->save();

            $this->audit->record(
                $user,
                $active ? 'usuario.activado' : 'usuario.desactivado',
                $active ? 'Usuario activado.' : 'Usuario desactivado.',
                $actor,
                ['estado_anterior' => $previousStatus, 'estado_nuevo' => $active, 'motivo' => $reason]
            );

            return $user->refresh();
        }, 5);

        if (! $active) {
            $this->revokeDatabaseSessions($user);
        }

        return $user;
    }

    private function lockStore(User $user): void
    {
        Tienda::query()->whereKey($user->tienda_id)->lockForUpdate()->firstOrFail();
    }

    private function lockedUser(User $user): User
    {
        return User::query()
            ->with('rol')
            ->where('tienda_id', $user->tienda_id)
            ->where('es_super_admin', false)
            ->lockForUpdate()
            ->findOrFail($user->id);
    }

    private function ensureAnotherActiveAdministratorExists(User $user, string $field = 'rol_id'): void
    {
        $hasAnotherAdministrator = User::query()
            ->where('tienda_id', $user->tienda_id)
            ->where('es_super_admin', false)
            ->where('activo', true)
            ->whereKeyNot($user->id)
            ->whereHas('rol', fn ($query) => $query->where('nombre', Rol::ADMINISTRATOR))
            ->exists();

        if (! $hasAnotherAdministrator) {
            throw ValidationException::withMessages([
                $field => 'La tienda debe conservar al menos un administrador activo.',
            ]);
        }
    }

    private function isAdministratorRole(Rol $role): bool
    {
        return mb_strtolower(trim($role->nombre)) === mb_strtolower(Rol::ADMINISTRATOR);
    }

    private function revokeDatabaseSessions(User $user): void
    {
        $table = (string) config('session.table', 'sessions');

        if (config('session.driver') === 'database' && Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $user->id)->delete();
        }
    }
}
