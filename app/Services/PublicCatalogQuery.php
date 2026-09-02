<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\StockReserva;
use App\Models\Tienda;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicCatalogQuery
{
    /**
     * @param array{q?:string|null,categoria?:string|null,page?:int|null} $filters
     * @return array{products:LengthAwarePaginator,categories:Collection<int,Categoria>}
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
                'variantes' => fn ($query) => $query->withoutGlobalScopes()
                    ->select('id', 'tienda_id', 'producto_id', 'talla', 'color', 'stock', 'stock_minimo')
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
            ->orderByDesc('destacado')
            ->orderBy('nombre')
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

        return compact('products', 'categories');
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
}
