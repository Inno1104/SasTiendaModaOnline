<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class InventoryReportQuery
{
    /** @param array<string, mixed> $filters */
    public function build(array $filters): Builder
    {
        $query = Producto::query()
            ->where('activo', true)
            ->with('categoria:id,nombre')
            ->withCount([
                'variantes as variantes_total',
                'variantes as variantes_bajas' => fn (Builder $variants) => $variants->conStockBajo(),
                'variantes as variantes_agotadas' => fn (Builder $variants) => $variants->agotadas(),
            ])
            ->withSum('variantes as stock_total', 'stock');

        $this->applyFilters($query, $filters);
        $this->applyOrder($query, $filters['orden'] ?? 'producto_asc');

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return array{productos: int, productos_disponibles: int, productos_bajo: int, productos_agotados: int, unidades: int, valor: float}
     */
    public function summary(array $filters): array
    {
        $query = Producto::query()->where('activo', true)
            ->withCount([
                'variantes as variantes_total',
                'variantes as variantes_bajas' => fn (Builder $variants) => $variants->conStockBajo(),
                'variantes as variantes_agotadas' => fn (Builder $variants) => $variants->agotadas(),
            ])
            ->withSum('variantes as stock_total', 'stock');
        $this->applyFilters($query, $filters);

        $summary = DB::query()->fromSub($query->reorder(), 'inventory')
            ->selectRaw('COUNT(*) AS products_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN variantes_bajas > 0 THEN 1 ELSE 0 END), 0) AS low_products')
            ->selectRaw('COALESCE(SUM(CASE WHEN variantes_agotadas > 0 THEN 1 ELSE 0 END), 0) AS exhausted_products')
            ->selectRaw('COALESCE(SUM(CASE WHEN variantes_total > 0 AND variantes_bajas = 0 AND variantes_agotadas = 0 THEN 1 ELSE 0 END), 0) AS available_products')
            ->selectRaw('COALESCE(SUM(stock_total), 0) AS units_count')
            ->selectRaw('COALESCE(SUM(stock_total * precio_compra), 0) AS inventory_value')
            ->first();

        return [
            'productos' => (int) $summary->products_count,
            'productos_disponibles' => (int) $summary->available_products,
            'productos_bajo' => (int) $summary->low_products,
            'productos_agotados' => (int) $summary->exhausted_products,
            'unidades' => (int) $summary->units_count,
            'valor' => (float) $summary->inventory_value,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query->when($filters['categoria_id'] ?? null, fn (Builder $products, int $id) => $products->where('categoria_id', $id));
        $this->applySearch($query, $filters['buscar'] ?? null);
        $this->applyState($query, $filters['estado'] ?? 'todos');
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $pattern = '%'.$escaped.'%';
        $query->where(function (Builder $products) use ($pattern): void {
            $products->whereRaw("nombre LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('variantes', fn (Builder $variants) => $variants
                    ->whereRaw("codigo_barra LIKE ? ESCAPE '!'", [$pattern]));
        });
    }

    private function applyState(Builder $query, string $state): void
    {
        match ($state) {
            'bajo' => $query->whereHas('variantes', fn (Builder $variants) => $variants->conStockBajo()),
            'agotado' => $query->whereHas('variantes', fn (Builder $variants) => $variants->agotadas()),
            'atencion' => $query->whereHas('variantes', fn (Builder $variants) => $variants
                ->where(fn (Builder $states) => $states->agotadas()
                    ->orWhere(fn (Builder $low) => $low->conStockBajo()))),
            'disponible' => $query->whereHas('variantes')
                ->whereDoesntHave('variantes', fn (Builder $variants) => $variants
                    ->where(fn (Builder $states) => $states->agotadas()
                        ->orWhere(fn (Builder $low) => $low->conStockBajo()))),
            default => null,
        };
    }

    private function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'stock_asc' => $query->orderBy('stock_total')->orderBy('productos.id'),
            'stock_desc' => $query->orderByDesc('stock_total')->orderByDesc('productos.id'),
            'valor_desc' => $query->orderByRaw('(COALESCE(stock_total, 0) * precio_compra) DESC')->orderByDesc('productos.id'),
            default => $query->orderBy('productos.nombre')->orderBy('productos.id'),
        };
    }
}
