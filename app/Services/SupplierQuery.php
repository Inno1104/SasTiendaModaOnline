<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SupplierQuery
{
    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->withCount(['productos', 'compras']);

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

        $text = mb_strtolower(Str::squish($term));
        $textPattern = '%'.$this->escapeLike($text).'%';
        $taxId = mb_strtoupper((string) preg_replace('/[^\pL\pN]/u', '', $term));
        $phone = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $query) use ($textPattern, $taxId, $phone): void {
            $query->whereRaw("LOWER(nombre) LIKE ? ESCAPE '!'", [$textPattern])
                ->orWhereRaw("LOWER(contacto) LIKE ? ESCAPE '!'", [$textPattern])
                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$textPattern]);

            if ($taxId !== '') {
                $query->orWhereRaw("ruc LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($taxId).'%']);
            }
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

    private function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'nombre_desc' => $query->orderByDesc('nombre')->orderByDesc('id'),
            'recientes' => $query->latest('created_at')->latest('id'),
            'antiguos' => $query->oldest('created_at')->oldest('id'),
            default => $query->orderBy('nombre')->orderBy('id'),
        };
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
