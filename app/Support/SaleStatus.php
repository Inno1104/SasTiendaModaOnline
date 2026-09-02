<?php

namespace App\Support;

final class SaleStatus
{
    public const PENDING = 'pendiente';

    public const COMPLETED = 'completada';

    public const CANCELLED = 'anulada';

    public const RETURNED = 'devuelta';

    public const ALL = [self::PENDING, self::COMPLETED, self::CANCELLED, self::RETURNED];

    private function __construct() {}

    public static function canCancel(string $status): bool
    {
        return $status === self::COMPLETED;
    }

    public static function canReturn(string $status): bool
    {
        return $status === self::COMPLETED;
    }
}
