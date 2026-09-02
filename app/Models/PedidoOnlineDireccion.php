<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class PedidoOnlineDireccion extends Model
{
    use PerteneceTienda;

    public const DELIVERY = 'entrega';

    public const FISCAL = 'fiscal';

    protected $table = 'pedido_online_direcciones';

    protected $fillable = [
        'tienda_id', 'pedido_online_id', 'tipo', 'destinatario', 'telefono',
        'pais_codigo', 'provincia', 'ciudad', 'codigo_postal', 'direccion', 'referencia',
    ];

    public function pedido()
    {
        return $this->belongsTo(PedidoOnline::class, 'pedido_online_id');
    }
}
