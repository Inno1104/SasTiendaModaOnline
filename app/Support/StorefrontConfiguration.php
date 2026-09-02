<?php

namespace App\Support;

final class StorefrontConfiguration
{
    public const DAYS = [
        'lunes' => 'Lunes',
        'martes' => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves' => 'Jueves',
        'viernes' => 'Viernes',
        'sabado' => 'Sábado',
        'domingo' => 'Domingo',
    ];

    public const CONDITION_FIELDS = [
        'envios' => 'Envíos y entregas',
        'devoluciones' => 'Cambios y devoluciones',
        'terminos' => 'Términos de compra',
        'privacidad' => 'Privacidad',
    ];

    private function __construct() {}
}
