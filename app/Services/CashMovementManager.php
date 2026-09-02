<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashMovementManager
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function create(Caja $register, User $user, array $data): MovimientoCaja
    {
        return DB::transaction(function () use ($register, $user, $data) {
            $register = Caja::query()->lockForUpdate()->findOrFail($register->id);

            if ($register->estado !== 'abierta') {
                throw ValidationException::withMessages([
                    'caja' => 'No se pueden registrar movimientos en una caja cerrada.',
                ]);
            }

            $amount = round((float) $data['monto'], 2);
            $movement = MovimientoCaja::create([
                'caja_id' => $register->id,
                'user_id' => $user->id,
                'tipo' => $data['tipo'],
                'concepto' => trim($data['concepto']),
                'monto' => $amount,
                'observaciones' => isset($data['observaciones']) ? trim($data['observaciones']) ?: null : null,
            ]);

            $totalField = $movement->tipo === 'ingreso' ? 'total_ingresos' : 'total_egresos';
            $register->update([
                $totalField => round((float) $register->{$totalField} + $amount, 2),
            ]);

            $this->audit->record($movement, 'caja.movimiento_creado', 'Movimiento de caja registrado', $user, [
                'caja_id' => $register->id,
                'tipo' => $movement->tipo,
                'concepto' => $movement->concepto,
                'monto' => (float) $movement->monto,
            ]);

            return $movement;
        });
    }
}
