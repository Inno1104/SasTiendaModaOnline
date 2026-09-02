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
        'cliente_nombre', 'cliente_apellido', 'cliente_email', 'cliente_telefono',
        'requiere_factura', 'tipo_identificacion', 'identificacion', 'razon_social',
        'subtotal', 'descuento', 'envio', 'total', 'moneda', 'simbolo_moneda',
        'reserva_expira_at',
        'pagado_at', 'preparando_at', 'enviado_at', 'entregado_at', 'cancelado_at', 'motivo_cancelacion',
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
        'entregado_at' => 'datetime',
        'cancelado_at' => 'datetime',
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

    public function puedeTransicionarA(string $status): bool
    {
        return OnlineOrderStatus::canTransition($this->estado, $status);
    }
}
