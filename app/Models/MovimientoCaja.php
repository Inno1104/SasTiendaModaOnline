<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class MovimientoCaja extends Model
{
    use PerteneceTienda;

    protected $table = 'movimientos_caja';

    protected $fillable = [
        'tienda_id',
        'caja_id',
        'user_id',
        'tipo',
        'concepto',
        'monto',
        'observaciones',
        'venta_reversion_id',
    ];

    protected $casts = ['monto' => 'decimal:2'];

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
