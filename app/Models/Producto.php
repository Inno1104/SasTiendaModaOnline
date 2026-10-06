<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use PerteneceTienda;

    protected $fillable = ['tienda_id', 'categoria_id', 'subcategoria_id', 'marca_id', 'proveedor_id', 'codigo', 'nombre', 'descripcion', 'genero', 'tipo', 'precio_compra', 'precio_venta', 'precio_oferta', 'imagen', 'activo', 'destacado'];

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'precio_oferta' => 'decimal:2',
        'activo' => 'boolean',
        'destacado' => 'boolean',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function variantes()
    {
        return $this->hasMany(ProductoVariante::class);
    }

    public function imagenes()
    {
        return $this->hasMany(ProductoImagen::class)->orderBy('orden');
    }

    public function stockTotal()
    {
        return $this->variantes()->sum('stock');
    }

    public function precioActual(): float
    {
        $regular = (float) $this->precio_venta;
        $offer = $this->precio_oferta !== null ? (float) $this->precio_oferta : null;

        return $offer !== null && $offer > 0 && $offer < $regular ? $offer : $regular;
    }
}
