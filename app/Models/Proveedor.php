<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use PerteneceTienda;

    protected $table = 'proveedores';

    protected $fillable = ['tienda_id', 'nombre', 'ruc', 'email', 'telefono', 'direccion', 'ciudad', 'pais', 'contacto', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function compras()
    {
        return $this->hasMany(Compra::class);
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'auditable_id')
            ->where('auditable_type', self::class);
    }
}
