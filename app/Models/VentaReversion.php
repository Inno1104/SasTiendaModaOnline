<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class VentaReversion extends Model
{
    use PerteneceTienda;

    public const TIPO_ANULACION = 'anulacion';

    public const TIPO_DEVOLUCION = 'devolucion';

    protected $table = 'venta_reversiones';

    protected $fillable = [
        'tienda_id', 'venta_id', 'pago_venta_id', 'caja_id', 'user_id',
        'tipo', 'metodo', 'monto', 'motivo',
    ];

    protected $casts = ['monto' => 'decimal:2'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las reversiones financieras son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las reversiones financieras son inmutables.'));
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pago()
    {
        return $this->belongsTo(PagoVenta::class, 'pago_venta_id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }
}
