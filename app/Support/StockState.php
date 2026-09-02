<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class StockState
{
    public const EXHAUSTED = 'agotada';

    public const LOW = 'bajo';

    public const AVAILABLE = 'disponible';

    public static function resolve(int $stock, int $minimum): string
    {
        return match (true) {
            $stock <= 0 => self::EXHAUSTED,
            $stock <= $minimum => self::LOW,
            default => self::AVAILABLE,
        };
    }

    public static function description(int $stock, int $minimum): string
    {
        return match (self::resolve($stock, $minimum)) {
            self::EXHAUSTED => 'Agotada',
            self::LOW => "{$stock} disponibles · Stock bajo",
            self::AVAILABLE => "{$stock} disponibles",
        };
    }

    public static function applyExhausted(Builder $query): Builder
    {
        return $query->where('stock', '<=', 0);
    }

    public static function applyLow(Builder $query): Builder
    {
        return $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'stock_minimo');
    }

    public static function applyAvailable(Builder $query): Builder
    {
        return $query->whereColumn('stock', '>', 'stock_minimo');
    }

    public static function applyInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public static function exhaustedSql(string $stock = 'stock'): string
    {
        return "{$stock} <= 0";
    }

    public static function lowSql(string $stock = 'stock', string $minimum = 'stock_minimo'): string
    {
        return "{$stock} > 0 AND {$stock} <= {$minimum}";
    }

    public static function availableSql(string $stock = 'stock', string $minimum = 'stock_minimo'): string
    {
        return "{$stock} > {$minimum}";
    }
}
