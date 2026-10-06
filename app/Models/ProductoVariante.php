<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use App\Support\StockState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductoVariante extends Model
{
    use PerteneceTienda;

    public const MAX_STOCK = 2_000_000_000;

    public const MIN_STOCK = 0;

    protected $table = 'producto_variantes';

    protected $fillable = ['tienda_id', 'producto_id', 'talla', 'detalle_talla', 'color', 'codigo_barra', 'precio_compra', 'precio_venta', 'precio_oferta', 'stock', 'stock_minimo', 'lock_version'];

    protected $casts = ['precio_compra' => 'decimal:2', 'precio_venta' => 'decimal:2', 'precio_oferta' => 'decimal:2', 'stock' => 'integer', 'stock_minimo' => 'integer', 'lock_version' => 'integer'];

    public function precioActual(): float
    {
        if ($this->precio_venta === null && $this->precio_oferta === null) {
            return $this->producto->precioActual();
        }

        $regular = $this->precio_venta !== null ? (float) $this->precio_venta : (float) $this->producto->precio_venta;
        $offer = $this->precio_oferta !== null ? (float) $this->precio_oferta : null;

        return $offer !== null && $offer > 0 && $offer < $regular ? $offer : $regular;
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function movimientos()
    {
        return $this->hasMany(StockMovimiento::class, 'variante_id');
    }

    public function conteos()
    {
        return $this->hasMany(StockConteo::class, 'variante_id');
    }

    public function reservas()
    {
        return $this->hasMany(StockReserva::class, 'variante_id');
    }

    public function stockDisponibleVenta(): int
    {
        $reserved = array_key_exists('stock_reservado', $this->getAttributes())
            ? (int) $this->getAttribute('stock_reservado')
            : (int) $this->reservas()->vigentes()->sum('cantidad');

        return max(0, (int) $this->stock - $reserved);
    }

    public function scopeOperativas(Builder $query): Builder
    {
        return $query->whereHas('producto', fn (Builder $product) => $product->where('activo', true));
    }

    public function scopeDeProductosInactivos(Builder $query): Builder
    {
        return $query->whereHas('producto', fn (Builder $product) => $product->where('activo', false));
    }

    public function scopeAgotadas(Builder $query): Builder
    {
        return StockState::applyExhausted($query);
    }

    public function scopeConStockBajo(Builder $query): Builder
    {
        return StockState::applyLow($query);
    }

    public function scopeDisponibles(Builder $query): Builder
    {
        return StockState::applyAvailable($query);
    }

    public function scopeConExistencias(Builder $query): Builder
    {
        return StockState::applyInStock($query);
    }

    public function estaAgotada(): bool
    {
        return $this->estadoStock() === StockState::EXHAUSTED;
    }

    public function tieneStockBajo(): bool
    {
        return $this->estadoStock() === StockState::LOW;
    }

    public function estadoStock(): string
    {
        return StockState::resolve($this->stock, $this->stock_minimo);
    }

    public function descripcionStock(): string
    {
        return StockState::description($this->stock, $this->stock_minimo);
    }
}
