<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    use PerteneceTienda;

    protected $fillable = ['tienda_id', 'nombre', 'nombre_normalizado', 'logo', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'auditable_id')
            ->where('auditable_type', self::class);
    }
}
