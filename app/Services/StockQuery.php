<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class StockQuery
{
    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->with('producto.categoria');
        $this->applyProductFilters(
            $query,
            $filters['producto_estado'] ?? 'activo',
            $filters['categoria_id'] ?? null
        );
        $this->applySearch($query, $filters['buscar'] ?? null);
        $this->applyState($query, $filters['filtro'] ?? null);
        $this->applyOrder($query, $filters['orden'] ?? 'stock_asc');

        return $query;
    }

    private function applyProductFilters(Builder $query, string $state, ?int $categoryId): void
    {
        if ($state === 'todos' && $categoryId === null) {
            return;
        }

        $query->whereHas('producto', function (Builder $product) use ($state, $categoryId): void {
            match ($state) {
                'inactivo' => $product->where('activo', false),
                'todos' => null,
                default => $product->where('activo', true),
            };

            $product->when($categoryId, fn (Builder $query, int $id) => $query->where('categoria_id', $id));
        });
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $pattern = '%'.$escaped.'%';
        $query->where(function (Builder $query) use ($pattern): void {
            $query->whereRaw("color LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("talla LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("codigo_barra LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('producto', function (Builder $product) use ($pattern): void {
                    $product->whereRaw("nombre LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$pattern]);
                });
        });
    }

    private function applyState(Builder $query, ?string $state): void
    {
        match ($state) {
            'bajo' => $query->conStockBajo(),
            'agotado' => $query->agotadas(),
            'disponible' => $query->disponibles(),
            default => null,
        };
    }

    private function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'stock_desc' => $query->orderByDesc('stock')->orderByDesc('id'),
            'producto_asc' => $query->orderBy(
                fn ($product) => $product->select('nombre')->from('productos')
                    ->whereColumn('productos.id', 'producto_variantes.producto_id')
                    ->limit(1)
            )->orderBy('id'),
            default => $query->orderBy('stock')->orderBy('id'),
        };
    }
}
