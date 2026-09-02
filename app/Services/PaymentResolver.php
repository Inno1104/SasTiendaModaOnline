<?php

namespace App\Services;

use App\Support\Money;
use App\Support\PaymentMethods;
use App\Support\SaleLimits;
use Illuminate\Validation\ValidationException;

class PaymentResolver
{
    public function resolve(array $data, float $total): array
    {
        $payments = empty($data['pagos'])
            ? collect([[
                'metodo' => $data['metodo_pago'],
                'monto' => $data['metodo_pago'] === PaymentMethods::CASH ? ($data['monto_pagado'] ?? 0) : $total,
                'referencia' => $data['referencia_pago'] ?? null,
            ]])
            : collect($data['pagos']);

        $payments = $payments->map(function (array $payment) {
            $method = $payment['metodo'];
            $reference = isset($payment['referencia']) ? trim((string) $payment['referencia']) : '';
            if (PaymentMethods::requiresReference($method) && $reference === '') {
                throw ValidationException::withMessages([
                    'pagos' => 'Los pagos electrónicos requieren una referencia.',
                ]);
            }

            return [
                'metodo' => $method,
                'monto' => Money::fromCents(Money::toCents($payment['monto'])),
                'referencia' => $method === PaymentMethods::CASH ? null : $reference,
            ];
        });

        $paidCents = (int) $payments->sum(fn ($payment) => Money::toCents($payment['monto']));
        $totalCents = Money::toCents($total);
        $cashCents = (int) $payments->where('metodo', PaymentMethods::CASH)
            ->sum(fn ($payment) => Money::toCents($payment['monto']));

        if ($paidCents > Money::toCents(SaleLimits::MAX_MONEY)) {
            throw ValidationException::withMessages([
                'pagos' => 'La suma de los pagos supera el monto máximo permitido.',
            ]);
        }

        if ($paidCents < $totalCents) {
            $field = empty($data['pagos']) ? 'monto_pagado' : 'pagos';
            throw ValidationException::withMessages([$field => 'La suma de los pagos no cubre el total de la venta.']);
        }

        $changeCents = $paidCents - $totalCents;
        if ($changeCents > $cashCents) {
            throw ValidationException::withMessages(['pagos' => 'Los pagos electrónicos no pueden generar vuelto.']);
        }

        if ($paidCents - $changeCents !== $totalCents) {
            throw ValidationException::withMessages(['pagos' => 'Los pagos no concilian con el total de la venta.']);
        }

        $mixed = $payments->pluck('metodo')->unique()->count() > 1;

        return [
            'payments' => $payments,
            'method' => $mixed ? PaymentMethods::MIXED : $payments->first()['metodo'],
            'mixed' => $mixed,
            'paid' => Money::fromCents($paidCents),
            'cash_received' => Money::fromCents($cashCents),
            'change' => Money::fromCents($changeCents),
            'net_paid' => Money::fromCents($paidCents - $changeCents),
        ];
    }
}
