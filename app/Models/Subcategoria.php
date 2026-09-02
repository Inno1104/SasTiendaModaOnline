<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Subcategoria extends Model
{
    use PerteneceTienda;

    protected $fillable = ['tienda_id', 'categoria_id', 'nombre', 'slug', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
