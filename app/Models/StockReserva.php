<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockReserva extends Model
{
    use PerteneceTienda;

    public const ACTIVE = 'activa';

    public const CONSUMED = 'consumida';

    public const RELEASED = 'liberada';

    public const EXPIRED = 'vencida';

    protected $table = 'stock_reservas';

    protected $fillable = [
        'tienda_id', 'pedido_online_id', 'variante_id', 'cantidad', 'estado',
        'expira_at', 'liberada_at', 'consumida_at', 'motivo_liberacion',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'expira_at' => 'datetime',
        'liberada_at' => 'datetime',
        'consumida_at' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(PedidoOnline::class, 'pedido_online_id');
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', self::ACTIVE)->where('expira_at', '>', now());
    }
}
