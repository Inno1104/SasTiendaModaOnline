<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Str;
use RuntimeException;

class ProductCodeGenerator
{
    private const PREFIX = 'PROD-';

    private const RANDOM_LENGTH = 6;

    private const MAX_ATTEMPTS = 20;

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = self::PREFIX.Str::upper(Str::random(self::RANDOM_LENGTH));

            if (! Producto::withoutGlobalScopes()->where('codigo', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('No fue posible generar un código único para el producto.');
    }
}
