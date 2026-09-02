<?php

namespace App\Support;

final class OnlineOrderStatus
{
    public const PENDING = 'pendiente';

    public const AWAITING_PAYMENT = 'esperando_pago';

    public const PAID = 'pagado';

    public const PREPARING = 'preparando';

    public const SHIPPED = 'enviado';

    public const DELIVERED = 'entregado';

    public const CANCELLED = 'cancelado';

    public const ALL = [
        self::PENDING,
        self::AWAITING_PAYMENT,
        self::PAID,
        self::PREPARING,
        self::SHIPPED,
        self::DELIVERED,
        self::CANCELLED,
    ];

    private const TRANSITIONS = [
        self::PENDING => [self::AWAITING_PAYMENT, self::CANCELLED],
        self::AWAITING_PAYMENT => [self::PAID, self::CANCELLED],
        self::PAID => [self::PREPARING, self::CANCELLED],
        self::PREPARING => [self::SHIPPED, self::CANCELLED],
        self::SHIPPED => [self::DELIVERED],
        self::DELIVERED => [],
        self::CANCELLED => [],
    ];

    public static function canTransition(string $current, string $next): bool
    {
        return in_array($next, self::TRANSITIONS[$current] ?? [], true);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pendiente',
            self::AWAITING_PAYMENT => 'Esperando pago',
            self::PAID => 'Pagado',
            self::PREPARING => 'Preparando',
            self::SHIPPED => 'Enviado',
            self::DELIVERED => 'Entregado',
            self::CANCELLED => 'Cancelado',
            default => 'Desconocido',
        };
    }

    private function __construct() {}
}
