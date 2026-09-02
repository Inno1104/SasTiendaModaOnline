<?php

namespace App\Services;

use App\Models\ProductoVariante;
use Illuminate\Validation\ValidationException;

class StockLevelGuard
{
    public function ensureValid(int $value, string $field = 'stock'): int
    {
        if ($value < ProductoVariante::MIN_STOCK || $value > ProductoVariante::MAX_STOCK) {
            throw ValidationException::withMessages([
                $field => 'La cantidad de inventario está fuera del rango permitido.',
            ]);
        }

        return $value;
    }

    public function afterIncrease(int $current, int $quantity, string $field = 'stock'): int
    {
        if ($quantity < 0 || $current > ProductoVariante::MAX_STOCK - $quantity) {
            throw ValidationException::withMessages([
                $field => 'La operación supera el máximo de inventario permitido.',
            ]);
        }

        return $this->ensureValid($current + $quantity, $field);
    }

    public function afterDecrease(int $current, int $quantity, string $field = 'stock'): int
    {
        if ($quantity < 0 || $current < $quantity) {
            throw ValidationException::withMessages([
                $field => 'La operación produciría inventario negativo.',
            ]);
        }

        return $this->ensureValid($current - $quantity, $field);
    }
}
