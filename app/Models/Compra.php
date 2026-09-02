<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    use PerteneceTienda;

    protected $table = 'compras';

    protected $fillable = [
        'tienda_id', 'operacion_id', 'operacion_hash', 'numero_compra', 'proveedor_id', 'user_id', 'fecha', 'total',
        'estado', 'anulada_por', 'anulada_at', 'motivo_anulacion', 'observaciones',
    ];

    protected $casts = ['fecha' => 'date', 'total' => 'decimal:2', 'anulada_at' => 'datetime'];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function anuladaPor()
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'auditable_id')
            ->where('auditable_type', self::class);
    }
}
