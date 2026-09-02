<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    private function __construct() {}

    public static function toCents(float|int|string $amount): int
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('El importe monetario no es válido.');
        }

        return (int) round((float) $amount * 100);
    }

    public static function fromCents(int $amount): float
    {
        return $amount / 100;
    }
}
