<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use PerteneceTienda;

    protected $fillable = ['tienda_id', 'nombre', 'nombre_normalizado', 'slug', 'descripcion', 'imagen', 'genero', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function subcategorias()
    {
        return $this->hasMany(Subcategoria::class);
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'auditable_id')
            ->where('auditable_type', self::class);
    }
}
