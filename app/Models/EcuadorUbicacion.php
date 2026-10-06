<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EcuadorUbicacion extends Model
{
    protected $table = 'ecuador_ubicaciones';

    public $timestamps = false;

    protected $guarded = [];

    public static function catalogo(): array
    {
        return self::query()
            ->orderBy('orden_provincia')
            ->orderBy('orden_canton')
            ->get(['provincia', 'canton'])
            ->groupBy('provincia')
            ->map(fn ($locations) => $locations->pluck('canton')->values()->all())
            ->all();
    }
}
