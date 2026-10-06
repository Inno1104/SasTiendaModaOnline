<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class PedidoOnlineDetalle extends Model
{
    use PerteneceTienda;

    protected $table = 'pedido_online_detalles';

    protected $fillable = [
        'tienda_id', 'pedido_online_id', 'producto_id', 'variante_id',
        'producto_codigo', 'producto_nombre', 'talla', 'color', 'cantidad',
        'precio_unitario', 'descuento', 'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function pedido()
    {
        return $this->belongsTo(PedidoOnline::class, 'pedido_online_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
