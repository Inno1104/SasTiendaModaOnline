<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockMovimiento extends Model
{
    use PerteneceTienda;

    public const UPDATED_AT = null;

    public const TIPO_AJUSTE = 'ajuste';

    public const TIPO_ANULACION = 'anulacion';

    public const TIPO_COMPRA = 'compra';

    public const TIPO_CONCILIACION = 'conciliacion';

    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_DEVOLUCION = 'devolucion';

    public const TIPO_SALIDA = 'salida';

    public const TIPO_VENTA = 'venta';

    protected $table = 'stock_movimientos';

    protected $fillable = [
        'tienda_id', 'producto_id', 'variante_id', 'user_id', 'tipo', 'direccion',
        'stock_anterior', 'cantidad', 'stock_resultante', 'referencia_type',
        'referencia_id', 'motivo',
    ];

    protected $casts = [
        'stock_anterior' => 'integer',
        'cantidad' => 'integer',
        'stock_resultante' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de stock son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de stock son inmutables.'));
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referencia()
    {
        return $this->morphTo();
    }
}
