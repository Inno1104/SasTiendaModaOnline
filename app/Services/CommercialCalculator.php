<?php

namespace App\Services;

use App\Support\Money;
use App\Support\SaleLimits;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CommercialCalculator
{
    public function calculate(Collection $lines, float $globalDiscount, float $taxRate, bool $taxIncluded): array
    {
        $prepared = $lines->values()->map(function (array $line) {
            $gross = $this->toCents($line['unit_price']) * (int) $line['quantity'];
            $discount = $this->toCents($line['discount'] ?? 0);

            if ($discount > $gross) {
                throw ValidationException::withMessages([
                    'items' => 'El descuento de un producto no puede superar su importe.',
                ]);
            }

            return $line + ['gross_cents' => $gross, 'line_discount_cents' => $discount];
        });

        $discountable = (int) $prepared->sum(fn ($line) => $line['gross_cents'] - $line['line_discount_cents']);
        $globalCents = $this->toCents($globalDiscount);

        if ($globalCents > $discountable) {
            throw ValidationException::withMessages([
                'descuento_global' => 'El descuento global no puede superar el importe de la venta.',
            ]);
        }

        $remainingGlobal = $globalCents;
        $remainingBase = $discountable;
        $lastIndex = $prepared->count() - 1;
        $calculated = $prepared->map(function (array $line, int $index) use (
            &$remainingGlobal, &$remainingBase, $lastIndex, $taxRate, $taxIncluded
        ) {
            $lineBase = $line['gross_cents'] - $line['line_discount_cents'];
            $allocated = $index === $lastIndex
                ? $remainingGlobal
                : ($remainingBase > 0 ? (int) round($remainingGlobal * $lineBase / $remainingBase) : 0);
            $allocated = min($allocated, $remainingGlobal, $lineBase);
            $remainingGlobal -= $allocated;
            $remainingBase -= $lineBase;
            $charge = $lineBase - $allocated;

            if ($taxIncluded) {
                $total = $charge;
                $net = $taxRate > 0 ? (int) round($total / (1 + ($taxRate / 100))) : $total;
                $tax = $total - $net;
            } else {
                $net = $charge;
                $tax = (int) round($net * ($taxRate / 100));
                $total = $net + $tax;
            }

            return array_merge($line, [
                'discount' => $this->fromCents($line['line_discount_cents'] + $allocated),
                'subtotal' => $this->fromCents($net),
                'tax' => $this->fromCents($tax),
                'total' => $this->fromCents($total),
            ]);
        });

        $total = (int) $calculated->sum(fn ($line) => $this->toCents($line['total']));
        if ($total > $this->toCents(SaleLimits::MAX_MONEY)) {
            throw ValidationException::withMessages([
                'items' => 'El total de la venta supera el monto máximo permitido.',
            ]);
        }

        return [
            'lines' => $calculated,
            'subtotal' => round((float) $calculated->sum('subtotal'), 2),
            'discount' => $this->fromCents((int) $prepared->sum('line_discount_cents') + $globalCents),
            'tax' => round((float) $calculated->sum('tax'), 2),
            'total' => $this->fromCents($total),
        ];
    }

    private function toCents(float|int|string $amount): int
    {
        return Money::toCents($amount);
    }

    private function fromCents(int $amount): float
    {
        return Money::fromCents($amount);
    }
}
