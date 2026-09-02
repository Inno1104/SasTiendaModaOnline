<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class CategoryQuery
{
    public function __construct(private readonly CategoryNameNormalizer $normalizer) {}

    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->withCount(['productos', 'subcategorias']);

        $this->applySearch($query, $filters['buscar'] ?? null);
        $this->applyStatus($query, $filters['estado'] ?? null);
        $this->applyGender($query, $filters['genero'] ?? null);
        $this->applyOrder($query, $filters['orden'] ?? 'nombre_asc');

        return $query;
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $normalizedTerm = $this->normalizer->normalize($term);
        $query->where('nombre_normalizado', 'like', '%'.$normalizedTerm.'%');
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if (in_array($status, ['activo', 'inactivo'], true)) {
            $query->where('activo', $status === 'activo');
        }
    }

    private function applyGender(Builder $query, ?string $gender): void
    {
        if (in_array($gender, ['damas', 'caballeros', 'ninos', 'unisex'], true)) {
            $query->where('genero', $gender);
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
