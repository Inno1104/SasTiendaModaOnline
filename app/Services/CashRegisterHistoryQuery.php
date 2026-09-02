<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class CashRegisterHistoryQuery
{
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['desde'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('fecha', '>=', $date))
            ->when($filters['hasta'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('fecha', '<=', $date))
            ->when($filters['estado'] ?? null, fn (Builder $builder, string $state) => $builder->where('estado', $state))
            ->when($filters['conciliacion'] ?? null, fn (Builder $builder, string $state) => $builder->where('estado_conciliacion', $state))
            ->when($filters['responsable_id'] ?? null, fn (Builder $builder, int|string $user) => $builder->where('user_id', $user))
            ->when($filters['q'] ?? null, fn (Builder $builder, string $search) => $this->search($builder, $search));
    }

    private function search(Builder $query, string $search): void
    {
        $value = trim($search);
        $id = filter_var(ltrim($value, '#'), FILTER_VALIDATE_INT);
        $term = '%'.addcslashes($value, '%_\\').'%';

        $query->where(function (Builder $builder) use ($id, $term) {
            if ($id !== false) {
                $builder->orWhere($builder->getModel()->qualifyColumn('id'), $id);
            }

            $builder->orWhereHas('usuario', fn (Builder $users) => $users->where('name', 'like', $term));
        });
    }
}
