<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    use PerteneceTienda;

    protected $table = 'detalle_ventas';

    protected $fillable = ['tienda_id', 'venta_id', 'producto_id', 'variante_id', 'producto_nombre', 'talla', 'color', 'cantidad', 'precio_unitario', 'descuento', 'subtotal'];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
