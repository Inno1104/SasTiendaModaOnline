<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use PerteneceTienda;

    protected $fillable = ['tienda_id', 'codigo', 'nombre', 'apellido', 'dni', 'email', 'telefono', 'direccion', 'ciudad', 'fecha_nacimiento', 'genero', 'puntos', 'activo'];

    protected $casts = ['activo' => 'boolean', 'fecha_nacimiento' => 'date', 'estado_cambiado_at' => 'datetime'];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function estadoCambiadoPor()
    {
        return $this->belongsTo(User::class, 'estado_cambiado_por');
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'auditable_id')->where('auditable_type', self::class);
    }

    public function nombreCompleto()
    {
        return $this->nombre.' '.$this->apellido;
    }
}
