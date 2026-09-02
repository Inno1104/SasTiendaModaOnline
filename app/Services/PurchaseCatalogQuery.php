<?php

namespace App\Services;

use App\Models\ProductoVariante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PurchaseCatalogQuery
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ProductoVariante::query()
            ->whereHas('producto', fn (Builder $product) => $product->where('activo', true))
            ->with('producto:id,tienda_id,categoria_id,codigo,nombre,precio_compra,activo')
            ->when($filters['categoria_id'] ?? null, fn (Builder $builder, $categoryId) => $builder->whereHas('producto', fn (Builder $product) => $product->where('categoria_id', (int) $categoryId)))
            ->when($filters['q'] ?? null, fn (Builder $builder, string $search) => $this->search($builder, $search))
            ->orderBy('producto_id')
            ->orderBy('talla')
            ->orderBy('color')
            ->orderBy('id');

        return $query->paginate(20);
    }

    private function search(Builder $query, string $search): void
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $query->where(function (Builder $builder) use ($like) {
            $builder->whereRaw("talla LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("color LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("codigo_barra LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('producto', fn (Builder $product) => $product->whereRaw("nombre LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$like])
                );
        });
    }
}
