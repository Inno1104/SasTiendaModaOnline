<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseCalculator
{
    public const MAX_QUANTITY = 999_999;

    private const MAX_AMOUNT_CENTS = 9_999_999_999;

    /**
     * @param  iterable<array<string, mixed>>  $items
     * @return Collection<int, array{variante_id: int, cantidad: int, precio: string, subtotal: string}>
     */
    public function calculate(iterable $items): Collection
    {
        $source = collect($items);
        if ($source->isEmpty() || $source->count() > 100) {
            throw ValidationException::withMessages([
                'items' => 'La compra debe contener entre 1 y 100 líneas.',
            ]);
        }

        $lines = collect();

        foreach ($source as $index => $item) {
            $variantId = (int) ($item['variante_id'] ?? 0);
            $quantity = (int) ($item['cantidad'] ?? 0);
            $unitPriceCents = $this->toCents($item['precio'] ?? null, "items.$index.precio");

            if ($variantId < 1 || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
                throw ValidationException::withMessages([
                    "items.$index.cantidad" => 'La cantidad de la línea no es válida.',
                ]);
            }

            if ($lines->has($variantId)) {
                $line = $lines->get($variantId);
                if ($line['unit_price_cents'] !== $unitPriceCents) {
                    throw ValidationException::withMessages([
                        "items.$index.precio" => 'Una variante repetida debe conservar el mismo precio de compra.',
                    ]);
                }
                $quantity += $line['cantidad'];
                if ($quantity > self::MAX_QUANTITY) {
                    throw ValidationException::withMessages([
                        "items.$index.cantidad" => 'La cantidad acumulada de la variante excede el límite permitido.',
                    ]);
                }
            }

            $subtotalCents = $unitPriceCents * $quantity;
            if ($subtotalCents > self::MAX_AMOUNT_CENTS) {
                throw ValidationException::withMessages([
                    "items.$index.precio" => 'El subtotal de la línea excede el límite permitido.',
                ]);
            }

            $lines->put($variantId, [
                'variante_id' => $variantId,
                'cantidad' => $quantity,
                'unit_price_cents' => $unitPriceCents,
                'subtotal_cents' => $subtotalCents,
            ]);
        }

        $totalCents = $lines->sum('subtotal_cents');
        if ($totalCents > self::MAX_AMOUNT_CENTS) {
            throw ValidationException::withMessages([
                'items' => 'El total de la compra excede el límite permitido.',
            ]);
        }

        return $lines->sortKeys()->values()->map(fn ($line) => [
            'variante_id' => $line['variante_id'],
            'cantidad' => $line['cantidad'],
            'precio' => $this->formatCents($line['unit_price_cents']),
            'subtotal' => $this->formatCents($line['subtotal_cents']),
        ]);
    }

    /** @param Collection<int, array{subtotal: string}> $lines */
    public function total(Collection $lines): string
    {
        $totalCents = $lines->sum(fn ($line) => $this->toCents($line['subtotal'], 'items'));

        if ($totalCents > self::MAX_AMOUNT_CENTS) {
            throw ValidationException::withMessages(['items' => 'El total de la compra excede el límite permitido.']);
        }

        return $this->formatCents($totalCents);
    }

    private function toCents(mixed $amount, string $field): int
    {
        $normalized = trim((string) $amount);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw ValidationException::withMessages([$field => 'El importe debe ser un número con máximo dos decimales.']);
        }

        $whole = (int) $matches[1];
        $decimals = str_pad($matches[2] ?? '', 2, '0');
        if ($whole > intdiv(self::MAX_AMOUNT_CENTS, 100)) {
            throw ValidationException::withMessages([$field => 'El importe excede el límite permitido.']);
        }

        $cents = ($whole * 100) + (int) $decimals;
        if ($cents > self::MAX_AMOUNT_CENTS) {
            throw ValidationException::withMessages([$field => 'El importe excede el límite permitido.']);
        }

        return $cents;
    }

    private function formatCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
