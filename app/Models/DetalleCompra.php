<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class DetalleCompra extends Model
{
    use PerteneceTienda;

    protected $table = 'detalle_compras';

    protected $fillable = ['tienda_id', 'compra_id', 'producto_id', 'variante_id', 'cantidad', 'precio_unitario', 'subtotal'];

    protected $casts = ['cantidad' => 'integer', 'precio_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
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
