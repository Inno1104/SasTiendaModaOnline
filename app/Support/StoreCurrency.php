<?php

namespace App\Support;

use InvalidArgumentException;

final class StoreCurrency
{
    /** @var array<string, array{name:string, symbol:string}> */
    public const ALLOWED = [
        'USD' => ['name' => 'Dólar estadounidense', 'symbol' => '$'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'PEN' => ['name' => 'Sol peruano', 'symbol' => 'S/'],
    ];

    public static function symbol(string $code): string
    {
        $currency = self::ALLOWED[$code] ?? null;
        if ($currency === null) {
            throw new InvalidArgumentException('La moneda seleccionada no está permitida.');
        }

        return $currency['symbol'];
    }
}
