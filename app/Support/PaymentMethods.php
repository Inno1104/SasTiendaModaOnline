<?php

namespace App\Support;

final class PaymentMethods
{
    public const CASH = 'efectivo';

    public const MIXED = 'mixto';

    public const ALL = [self::CASH, 'tarjeta', 'transferencia', 'yape', 'plin'];

    private function __construct() {}

    public static function requiresReference(string $method): bool
    {
        return $method !== self::CASH && in_array($method, self::ALL, true);
    }
}
