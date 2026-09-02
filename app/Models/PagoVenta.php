<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PagoVenta extends Model
{
    use PerteneceTienda;

    protected $table = 'pagos_venta';

    protected $fillable = ['tienda_id', 'venta_id', 'metodo', 'monto', 'referencia'];

    protected $casts = ['monto' => 'decimal:2'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los pagos de una venta son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los pagos de una venta son inmutables.'));
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }
}
