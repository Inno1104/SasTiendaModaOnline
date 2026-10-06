<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use App\Support\OnlineOrderStatus;
use Illuminate\Database\Eloquent\Model;

class PedidoOnline extends Model
{
    use PerteneceTienda;

    protected $table = 'pedidos_online';

    protected $fillable = [
        'tienda_id', 'operacion_id', 'operacion_hash', 'numero', 'estado',
        'metodo_pago', 'referencia_pago', 'comprobante_pago_path', 'pago_reportado_at',
        'pago_verificado_por', 'pago_verificado_at',
        'venta_id', 'comprobante_enviado_at', 'comprobante_enviado_por', 'comprobante_publicado_at',
        'cliente_nombre', 'cliente_apellido', 'cliente_dni', 'cliente_email', 'cliente_telefono',
        'requiere_factura', 'tipo_comprobante', 'tipo_identificacion', 'identificacion', 'razon_social',
        'subtotal', 'descuento', 'envio', 'total', 'moneda', 'simbolo_moneda',
        'reserva_expira_at',
        'pagado_at', 'preparando_at', 'enviado_at', 'guia_envio_path', 'guia_envio_subida_at', 'entregado_at',
        'calificacion', 'comentario_calificacion', 'calificado_at', 'cancelado_at', 'motivo_cancelacion',
    ];

    protected $casts = [
        'requiere_factura' => 'boolean',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'envio' => 'decimal:2',
        'total' => 'decimal:2',
        'reserva_expira_at' => 'datetime',
        'pagado_at' => 'datetime',
        'preparando_at' => 'datetime',
        'enviado_at' => 'datetime',
        'guia_envio_subida_at' => 'datetime',
        'entregado_at' => 'datetime',
        'calificacion' => 'integer',
        'calificado_at' => 'datetime',
        'cancelado_at' => 'datetime',
        'pago_reportado_at' => 'datetime',
        'pago_verificado_at' => 'datetime',
        'comprobante_enviado_at' => 'datetime',
        'comprobante_publicado_at' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(PedidoOnlineDetalle::class);
    }

    public function direcciones()
    {
        return $this->hasMany(PedidoOnlineDireccion::class);
    }

    public function reservas()
    {
        return $this->hasMany(StockReserva::class, 'pedido_online_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function puedeTransicionarA(string $status): bool
    {
        return OnlineOrderStatus::canTransition($this->estado, $status);
    }

    public function seguimientoClienteDisponible(): bool
    {
        if ($this->calificado_at) {
            return false;
        }

        if ($this->estado !== OnlineOrderStatus::DELIVERED) {
            return true;
        }

        return $this->entregado_at !== null
            && now()->lt($this->entregado_at->copy()->addHours(24));
    }
}
