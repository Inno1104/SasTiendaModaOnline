<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use App\Support\StockCountState;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockConteo extends Model
{
    use PerteneceTienda;

    public const ESTADO_CONCILIADO = StockCountState::RECONCILED;

    public const ESTADO_PENDIENTE = StockCountState::PENDING;

    public const ESTADO_REEMPLAZADO = StockCountState::REPLACED;

    protected $table = 'stock_conteos';

    protected $fillable = [
        'tienda_id', 'producto_id', 'variante_id', 'contado_por', 'autorizado_por',
        'stock_sistema', 'stock_fisico', 'diferencia', 'version_stock', 'estado',
        'motivo', 'conciliado_at', 'operacion_id', 'operacion_hash',
        'reemplazado_por_conteo_id', 'reemplazado_por_usuario_id', 'reemplazado_at',
    ];

    protected $casts = [
        'stock_sistema' => 'integer',
        'stock_fisico' => 'integer',
        'diferencia' => 'integer',
        'version_stock' => 'integer',
        'conciliado_at' => 'datetime',
        'reemplazado_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $count): void {
            if (StockCountState::isTerminal((string) $count->getOriginal('estado'))) {
                throw new LogicException('Los conteos finalizados son inmutables.');
            }
        });
        static::deleting(function (self $count): void {
            if (StockCountState::isTerminal((string) $count->estado)) {
                throw new LogicException('Los conteos finalizados son inmutables.');
            }
        });
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'contado_por');
    }

    public function autorizador()
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    public function reemplazo()
    {
        return $this->belongsTo(self::class, 'reemplazado_por_conteo_id');
    }

    public function reemplazadoPor()
    {
        return $this->belongsTo(User::class, 'reemplazado_por_usuario_id');
    }
}
