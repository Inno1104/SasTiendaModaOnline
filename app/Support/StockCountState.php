<?php

namespace App\Support;

final class StockCountState
{
    public const PENDING = 'pendiente';

    public const RECONCILED = 'conciliado';

    public const REPLACED = 'reemplazado';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::PENDING, self::RECONCILED, self::REPLACED];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return $from === self::PENDING
            && in_array($to, [self::RECONCILED, self::REPLACED], true);
    }

    public static function isTerminal(string $state): bool
    {
        return in_array($state, [self::RECONCILED, self::REPLACED], true);
    }
}
