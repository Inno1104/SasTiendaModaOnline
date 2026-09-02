<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class SaleHistoryQuery
{
    public function apply(Builder $query, array $filters): Builder
    {
        [$from, $to] = $this->dateRange($filters);

        return $query
            ->when($from, fn (Builder $builder) => $builder->whereDate('fecha', '>=', $from))
            ->when($to, fn (Builder $builder) => $builder->whereDate('fecha', '<=', $to))
            ->when($filters['estado'] ?? null, fn (Builder $builder, string $state) => $builder->where('estado', $state))
            ->when($filters['vendedor_id'] ?? null, fn (Builder $builder, int|string $seller) => $builder->where('user_id', $seller))
            ->when($filters['caja_id'] ?? null, fn (Builder $builder, int|string $register) => $builder->where('caja_id', $register))
            ->when($filters['metodo'] ?? null, fn (Builder $builder, string $method) => $builder->whereHas(
                'pagos', fn (Builder $payments) => $payments->where('metodo', $method)
            ))
            ->when($filters['q'] ?? null, fn (Builder $builder, string $search) => $this->search($builder, $search));
    }

    private function dateRange(array $filters): array
    {
        if (! empty($filters['desde']) || ! empty($filters['hasta'])) {
            return [$filters['desde'] ?? null, $filters['hasta'] ?? null];
        }

        $today = CarbonImmutable::today();

        return match ($filters['periodo'] ?? null) {
            'hoy' => [$today->toDateString(), $today->toDateString()],
            '7_dias' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            '30_dias' => [$today->subDays(29)->toDateString(), $today->toDateString()],
            'mes_actual' => [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()],
            default => [null, null],
        };
    }

    private function search(Builder $query, string $search): void
    {
        $term = '%'.addcslashes(trim($search), '%_\\').'%';

        $query->where(function (Builder $builder) use ($term) {
            $builder->where('numero_venta', 'like', $term)
                ->orWhere('serie', 'like', $term)
                ->orWhere('correlativo', 'like', $term)
                ->orWhereHas('cliente', function (Builder $clients) use ($term) {
                    $clients->where('codigo', 'like', $term)
                        ->orWhere('nombre', 'like', $term)
                        ->orWhere('apellido', 'like', $term)
                        ->orWhere('dni', 'like', $term);
                })
                ->orWhereHas('vendedor', fn (Builder $sellers) => $sellers->where('name', 'like', $term))
                ->orWhereHas('pagos', fn (Builder $payments) => $payments->where('referencia', 'like', $term));
        });
    }
}
