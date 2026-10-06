<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

final class ProductoImagen extends Model
{
    use PerteneceTienda;

    protected $table = 'producto_imagenes';
    protected $fillable = ['tienda_id', 'producto_id', 'ruta', 'talla', 'color', 'orden', 'principal'];
    protected $casts = ['orden' => 'integer', 'principal' => 'boolean'];

    public function producto() { return $this->belongsTo(Producto::class); }
}
