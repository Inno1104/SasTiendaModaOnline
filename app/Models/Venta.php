<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use PerteneceTienda;

    protected $fillable = ['tienda_id', 'operacion_id', 'operacion_hash', 'numero_venta', 'cliente_id', 'user_id', 'caja_id', 'fecha', 'tipo_comprobante', 'serie', 'correlativo', 'subtotal', 'descuento', 'igv', 'total', 'metodo_pago', 'monto_pagado', 'vuelto', 'estado', 'anulada_por', 'anulada_at', 'motivo_anulacion', 'devuelta_por', 'devuelta_at', 'motivo_devolucion', 'caja_devolucion_id', 'commercial_snapshot', 'commercial_snapshot_hash', 'observaciones'];

    protected $casts = [
        'fecha' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'vuelto' => 'decimal:2',
        'anulada_at' => 'datetime',
        'devuelta_at' => 'datetime',
        'commercial_snapshot' => 'array',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class)->orderBy('id');
    }

    public function pagos()
    {
        return $this->hasMany(PagoVenta::class)->orderBy('id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }

    public function anuladaPor()
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function devueltaPor()
    {
        return $this->belongsTo(User::class, 'devuelta_por');
    }

    public function reversiones()
    {
        return $this->hasMany(VentaReversion::class)->orderBy('id');
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'auditable_id')->where('auditable_type', self::class);
    }
}
