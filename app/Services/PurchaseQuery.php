<?php

namespace App\Services;

use App\Models\Compra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PurchaseQuery
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Compra::query()->with('proveedor:id,nombre');

        $query->when($filters['buscar'] ?? null, fn (Builder $builder, string $search) => $this->search($builder, $search))
            ->when($filters['estado'] ?? null, fn (Builder $builder, string $status) => $builder->where('estado', $status))
            ->when($filters['proveedor_id'] ?? null, fn (Builder $builder, $supplierId) => $builder->where('proveedor_id', (int) $supplierId))
            ->when($filters['desde'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('fecha', '>=', $date))
            ->when($filters['hasta'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('fecha', '<=', $date));

        $this->order($query, $filters['orden'] ?? 'recientes');

        return $query->paginate(15)->withQueryString();
    }

    private function search(Builder $query, string $search): void
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $query->where(function (Builder $builder) use ($like) {
            $builder->whereRaw("numero_compra LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('proveedor', fn (Builder $supplier) => $supplier->whereRaw("nombre LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("ruc LIKE ? ESCAPE '!'", [$like])
                );
        });
    }

    private function order(Builder $query, string $order): void
    {
        match ($order) {
            'antiguas' => $query->orderBy('fecha')->orderBy('id'),
            'mayor_total' => $query->orderByDesc('total')->orderByDesc('id'),
            'menor_total' => $query->orderBy('total')->orderBy('id'),
            default => $query->orderByDesc('fecha')->orderByDesc('id'),
        };
    }
}
