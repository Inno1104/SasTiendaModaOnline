<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaReversion;
use App\Support\Money;
use App\Support\PaymentMethods;
use Illuminate\Validation\ValidationException;

class SaleFinancialReverser
{
    public function reverse(Venta $sale, User $user, string $type, string $reason, ?Caja $register = null): array
    {
        $payments = $sale->pagos()->orderBy('id')->get();
        if ($payments->isEmpty()) {
            if ($sale->metodo_pago === PaymentMethods::MIXED) {
                throw ValidationException::withMessages([
                    'venta' => 'La venta no conserva el detalle necesario para revertir un pago mixto.',
                ]);
            }

            $reversal = VentaReversion::create([
                'venta_id' => $sale->id,
                'caja_id' => $register?->id,
                'user_id' => $user->id,
                'tipo' => $type,
                'metodo' => $sale->metodo_pago,
                'monto' => $sale->total,
                'motivo' => $reason,
            ]);

            return [
                'total' => (float) $sale->total,
                'efectivo' => $sale->metodo_pago === PaymentMethods::CASH ? (float) $sale->total : 0.0,
                'reversion_ids' => [$reversal->id],
            ];
        }

        $remainingChange = Money::toCents($sale->vuelto);
        $reversedCents = 0;
        $cashCents = 0;
        $reversalIds = [];

        foreach ($payments as $payment) {
            $amountCents = Money::toCents($payment->monto);
            if ($payment->metodo === PaymentMethods::CASH && $remainingChange > 0) {
                $deduction = min($amountCents, $remainingChange);
                $amountCents -= $deduction;
                $remainingChange -= $deduction;
            }

            if ($amountCents <= 0) {
                continue;
            }

            $reversal = VentaReversion::create([
                'venta_id' => $sale->id,
                'pago_venta_id' => $payment->id,
                'caja_id' => $register?->id,
                'user_id' => $user->id,
                'tipo' => $type,
                'metodo' => $payment->metodo,
                'monto' => Money::fromCents($amountCents),
                'motivo' => $reason,
            ]);
            $reversalIds[] = $reversal->id;
            $reversedCents += $amountCents;
            if ($payment->metodo === PaymentMethods::CASH) {
                $cashCents += $amountCents;
            }
        }

        if ($remainingChange !== 0 || $reversedCents !== Money::toCents($sale->total)) {
            throw ValidationException::withMessages([
                'venta' => 'Los pagos registrados no permiten conciliar la reversión financiera.',
            ]);
        }

        return [
            'total' => Money::fromCents($reversedCents),
            'efectivo' => Money::fromCents($cashCents),
            'reversion_ids' => $reversalIds,
        ];
    }
}
