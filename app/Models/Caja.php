<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    use PerteneceTienda;

    protected $table = 'caja';
    protected $fillable = ['tienda_id', 'user_id', 'cerrada_por', 'fecha', 'monto_inicial', 'monto_final', 'efectivo_esperado', 'efectivo_contado', 'diferencia', 'estado_conciliacion', 'total_ventas', 'total_ingresos', 'total_egresos', 'estado', 'apertura', 'cierre', 'observaciones', 'observaciones_cierre'];
    protected $casts = [
        'fecha' => 'date', 'apertura' => 'datetime', 'cierre' => 'datetime',
        'monto_inicial' => 'decimal:2', 'monto_final' => 'decimal:2',
        'efectivo_esperado' => 'decimal:2', 'efectivo_contado' => 'decimal:2', 'diferencia' => 'decimal:2',
        'total_ventas' => 'decimal:2', 'total_ingresos' => 'decimal:2', 'total_egresos' => 'decimal:2',
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'auditable_id')->where('auditable_type', self::class);
    }
}
