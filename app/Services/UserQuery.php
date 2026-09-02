<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class UserQuery
{
    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        $this->applySearch($query, $filters['buscar'] ?? null);
        $this->applyStatus($query, $filters['estado'] ?? null);
        $this->applyRole($query, $filters['rol_id'] ?? null);
        $this->applyOrder($query, $filters['orden'] ?? 'nombre_asc');

        return $query;
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $pattern = '%'.$this->escapeLike(mb_strtolower(Str::squish($term))).'%';
        $phone = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $query) use ($pattern, $phone): void {
            $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern]);

            if ($phone !== '') {
                $query->orWhereRaw("telefono LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($phone).'%']);
            }
        });
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if (in_array($status, ['activo', 'inactivo'], true)) {
            $query->where('activo', $status === 'activo');
        }
    }

    private function applyRole(Builder $query, mixed $roleId): void
    {
        if ($roleId !== null) {
            $query->where('rol_id', (int) $roleId);
        }
    }

    private function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'nombre_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'recientes' => $query->latest('created_at')->latest('id'),
            'antiguos' => $query->oldest('created_at')->oldest('id'),
            default => $query->orderBy('name')->orderBy('id'),
        };
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
