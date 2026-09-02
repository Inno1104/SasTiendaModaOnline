<?php

namespace App\Exceptions;

use RuntimeException;

class ProductPlanLimitReached extends RuntimeException
{
    public static function forPlan(?string $planName, int $limit): self
    {
        return new self(
            'Has alcanzado el límite de productos activos de tu plan '.($planName ?: 'actual').
            " ($limit). Desactiva un producto o mejora tu plan para continuar."
        );
    }
}
