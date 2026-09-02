<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class BrandQuery
{
    public function __construct(private readonly BrandNameNormalizer $normalizer) {}

    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->withCount('productos');

        $this->applySearch($query, $filters['buscar'] ?? null);
        $this->applyStatus($query, $filters['estado'] ?? null);
        $this->applyOrder($query, $filters['orden'] ?? 'nombre_asc');

        return $query;
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $normalized = $this->normalizer->normalize($term);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $normalized);
        $query->whereRaw("nombre_normalizado LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if (in_array($status, ['activo', 'inactivo'], true)) {
            $query->where('activo', $status === 'activo');
        }
    }

    private function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'nombre_desc' => $query->orderByDesc('nombre_normalizado')->orderByDesc('id'),
            'recientes' => $query->latest('created_at')->latest('id'),
            'antiguas' => $query->oldest('created_at')->oldest('id'),
            default => $query->orderBy('nombre_normalizado')->orderBy('id'),
        };
    }
}
