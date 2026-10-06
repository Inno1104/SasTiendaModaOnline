<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\StockReserva;
use App\Models\Tienda;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicCatalogQuery
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array{products:LengthAwarePaginator,categories:Collection<int,Categoria>,sizes:Collection<int,string>,colors:Collection<int,string>,priceLimit:float}
     */
    public function execute(Tienda $store, array $filters): array
    {
        $storeId = (int) $store->id;
        $products = Producto::withoutGlobalScopes()
            ->select([
                'id', 'tienda_id', 'categoria_id', 'marca_id', 'codigo', 'nombre',
                'descripcion', 'precio_venta', 'precio_oferta', 'imagen', 'destacado',
            ])
            ->where('tienda_id', $storeId)
            ->where('activo', true)
            ->whereHas('categoria', fn (Builder $query) => $query
                ->withoutGlobalScopes()
                ->where('tienda_id', $storeId)
                ->where('activo', true))
            ->with([
                'categoria' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'tienda_id', 'nombre', 'slug'),
                'marca' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'tienda_id', 'nombre'),
                'imagenes' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'tienda_id', 'producto_id', 'ruta', 'talla', 'color', 'orden', 'principal')->orderBy('orden'),
                'variantes' => fn ($query) => $query->withoutGlobalScopes()
                    ->select('id', 'tienda_id', 'producto_id', 'talla', 'detalle_talla', 'color', 'stock', 'stock_minimo')
                    ->withSum(['reservas as stock_reservado' => fn ($reservations) => $reservations
                        ->withoutGlobalScopes()
                        ->where('estado', StockReserva::ACTIVE)
                        ->where('expira_at', '>', now())], 'cantidad')
                    ->where('tienda_id', $storeId)
                    ->orderBy('talla')
                    ->orderBy('color'),
            ])
            ->when($filters['categoria'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereHas('categoria', fn (Builder $categories) => $categories
                    ->withoutGlobalScopes()
                    ->where('tienda_id', $storeId)
                    ->where('activo', true)
                    ->where('slug', $slug)))
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, $storeId))
            ->when($filters['tallas'] ?? [], fn (Builder $query, array $sizes) => $query
                ->whereHas('variantes', fn (Builder $variants) => $variants->withoutGlobalScopes()
                    ->where('tienda_id', $storeId)->whereIn('talla', $sizes)))
            ->when($filters['colores'] ?? [], fn (Builder $query, array $colors) => $query
                ->whereHas('variantes', fn (Builder $variants) => $variants->withoutGlobalScopes()
                    ->where('tienda_id', $storeId)->whereIn('color', $colors)))
            ->when(isset($filters['precio_min']), fn (Builder $query) => $query->whereRaw($this->priceExpression().' >= ?', [(float) $filters['precio_min']]))
            ->when(isset($filters['precio_max']), fn (Builder $query) => $query->whereRaw($this->priceExpression().' <= ?', [(float) $filters['precio_max']]))
            ->tap(fn (Builder $query) => $this->applyOrder($query, (string) ($filters['orden'] ?? 'destacados')))
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $categories = Categoria::withoutGlobalScopes()
            ->select('id', 'tienda_id', 'nombre', 'slug')
            ->where('tienda_id', $storeId)
            ->where('activo', true)
            ->whereHas('productos', fn (Builder $query) => $query
                ->withoutGlobalScopes()
                ->where('tienda_id', $storeId)
                ->where('activo', true))
            ->withCount(['productos as productos_publicos_count' => fn (Builder $query) => $query
                ->withoutGlobalScopes()
                ->where('tienda_id', $storeId)
                ->where('activo', true)])
            ->orderBy('nombre')
            ->get();

        $variants = ProductoVariante::withoutGlobalScopes()
            ->where('tienda_id', $storeId)
            ->whereHas('producto', fn (Builder $query) => $query->withoutGlobalScopes()->where('tienda_id', $storeId)->where('activo', true));
        $sizes = (clone $variants)->whereNotNull('talla')->distinct()->orderBy('talla')->pluck('talla');
        $colors = (clone $variants)->whereNotNull('color')->distinct()->orderBy('color')->pluck('color');
        $priceLimit = (float) Producto::withoutGlobalScopes()->where('tienda_id', $storeId)->where('activo', true)
            ->selectRaw('MAX('.$this->priceExpression().') as max_price')->value('max_price');

        return compact('products', 'categories', 'sizes', 'colors', 'priceLimit');
    }

    private function search(Builder $query, string $search, int $storeId): void
    {
        $term = '%'.addcslashes($search, '%_\\').'%';
        $query->where(function (Builder $builder) use ($term, $storeId): void {
            $builder->where('nombre', 'like', $term)
                ->orWhere('descripcion', 'like', $term)
                ->orWhereHas('categoria', fn (Builder $categories) => $categories
                    ->withoutGlobalScopes()
                    ->where('tienda_id', $storeId)
                    ->where('activo', true)
                    ->where('nombre', 'like', $term))
                ->orWhereHas('marca', fn (Builder $brands) => $brands
                    ->withoutGlobalScopes()
                    ->where('tienda_id', $storeId)
                    ->where('nombre', 'like', $term));
        });
    }

    private function priceExpression(): string
    {
        return 'CAST(CASE WHEN precio_oferta IS NOT NULL AND precio_oferta > 0 AND precio_oferta < precio_venta THEN precio_oferta ELSE precio_venta END AS DECIMAL(14,2))';
    }

    private function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'recientes' => $query->orderByDesc('id'),
            'nombre' => $query->orderBy('nombre'),
            'precio_asc' => $query->orderByRaw($this->priceExpression().' ASC')->orderBy('nombre'),
            'precio_desc' => $query->orderByRaw($this->priceExpression().' DESC')->orderBy('nombre'),
            default => $query->orderByDesc('destacado')->orderBy('nombre'),
        };
    }
}
