<?php

namespace App\Exceptions;

use RuntimeException;

final class UserPlanLimitReached extends RuntimeException
{
    public static function forPlan(?string $planName, int $limit): self
    {
        return new self(
            'Alcanzaste el límite de usuarios de tu plan '.($planName ?: 'actual').
            " ($limit). Mejora tu plan para agregar más."
        );
    }
}
