<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'nombre', 'slug', 'precio', 'max_productos', 'max_usuarios',
        'max_ventas_mes', 'descripcion', 'caracteristicas', 'activo',
    ];

    protected $casts = [
        'caracteristicas' => 'array',
        'activo'          => 'boolean',
        'precio'          => 'decimal:2',
    ];

    public function tiendas()
    {
        return $this->hasMany(Tienda::class);
    }

    public function esIlimitado(string $campo): bool
    {
        return (int) $this->{$campo} === -1;
    }
}
