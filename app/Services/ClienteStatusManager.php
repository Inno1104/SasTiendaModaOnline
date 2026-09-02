<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClienteStatusManager
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function change(Cliente $cliente, User $actor, bool $active, string $reason): Cliente
    {
        return DB::transaction(function () use ($cliente, $actor, $active, $reason): Cliente {
            $lockedCustomer = Cliente::query()->whereKey($cliente->getKey())->lockForUpdate()->firstOrFail();

            if ((bool) $lockedCustomer->activo === $active) {
                throw ValidationException::withMessages([
                    'activo' => $active ? 'El cliente ya se encuentra activo.' : 'El cliente ya se encuentra inactivo.',
                ]);
            }

            $previousStatus = (bool) $lockedCustomer->activo;
            $lockedCustomer->forceFill([
                'activo' => $active,
                'estado_cambiado_por' => $actor->id,
                'estado_cambiado_at' => now(),
                'motivo_estado' => $reason,
            ])->save();

            $this->auditLogger->record(
                $lockedCustomer,
                $active ? 'cliente.reactivado' : 'cliente.desactivado',
                $active ? 'Cliente reactivado.' : 'Cliente desactivado.',
                $actor,
                ['estado_anterior' => $previousStatus, 'estado_nuevo' => $active, 'motivo' => $reason]
            );

            return $lockedCustomer;
        }, 5);
    }
}
