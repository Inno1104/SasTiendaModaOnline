<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegisterManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CashRegisterSummary $summary
    ) {}

    public function open(User $user, float $initialAmount, ?string $notes = null): Caja
    {
        return DB::transaction(function () use ($user, $initialAmount, $notes) {
            Tienda::query()->lockForUpdate()->findOrFail($user->tienda_id);

            if (Caja::query()->where('estado', 'abierta')->exists()) {
                throw ValidationException::withMessages(['caja' => 'Ya existe una caja abierta en la tienda.']);
            }

            $register = Caja::create([
                'user_id' => $user->id,
                'fecha' => today(),
                'monto_inicial' => round($initialAmount, 2),
                'estado' => 'abierta',
                'apertura' => now(),
                'observaciones' => $notes,
            ]);

            $this->audit->record($register, 'caja.abierta', 'Caja abierta', $user, [
                'monto_inicial' => (float) $register->monto_inicial,
                'observaciones' => $register->observaciones,
            ]);

            return $register;
        });
    }

    public function close(Caja $register, User $user, array $data): Caja
    {
        return DB::transaction(function () use ($register, $user, $data) {
            $register = Caja::query()->lockForUpdate()->findOrFail($register->id);
            if ($register->estado !== 'abierta') {
                throw ValidationException::withMessages(['caja' => 'La caja ya está cerrada.']);
            }

            $summary = $this->summary->calculate($register);
            $countedCents = Money::toCents($data['efectivo_contado']);
            $differenceCents = $countedCents - Money::toCents($summary['efectivo_esperado']);
            $counted = Money::fromCents($countedCents);
            $difference = Money::fromCents($differenceCents);
            $reconciliationStatus = $differenceCents === 0 ? 'exacto' : ($differenceCents > 0 ? 'sobrante' : 'faltante');
            $closingNotes = trim((string) ($data['observaciones_cierre'] ?? '')) ?: null;

            if ($differenceCents !== 0 && mb_strlen((string) $closingNotes) < 5) {
                throw ValidationException::withMessages([
                    'observaciones_cierre' => 'Debes justificar el faltante o sobrante con al menos 5 caracteres.',
                ]);
            }

            $register->update([
                'total_ventas' => $summary['total_ventas'],
                'total_ingresos' => $summary['total_ingresos'],
                'total_egresos' => $summary['total_egresos'],
                'monto_final' => $counted,
                'efectivo_esperado' => $summary['efectivo_esperado'],
                'efectivo_contado' => $counted,
                'diferencia' => $difference,
                'estado_conciliacion' => $reconciliationStatus,
                'observaciones_cierre' => $closingNotes,
                'cerrada_por' => $user->id,
                'estado' => 'cerrada',
                'cierre' => now(),
            ]);

            $this->audit->record($register, 'caja.cerrada', 'Caja cerrada', $user, [
                'total_ventas' => $summary['total_ventas'],
                'pagos' => $summary['pagos'],
                'efectivo_recibido' => $summary['efectivo_recibido'],
                'vuelto' => $summary['vuelto'],
                'total_ingresos' => $summary['total_ingresos'],
                'total_egresos' => $summary['total_egresos'],
                'efectivo_esperado' => $summary['efectivo_esperado'],
                'efectivo_contado' => $counted,
                'diferencia' => $difference,
                'estado_conciliacion' => $reconciliationStatus,
                'observaciones_cierre' => $closingNotes,
            ]);

            return $register;
        });
    }
}
