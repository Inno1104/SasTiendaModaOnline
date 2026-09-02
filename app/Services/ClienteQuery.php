<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class ClienteQuery
{
    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->withCount([
            'ventas as compras_completadas' => fn (Builder $sales) => $sales->where('estado', 'completada'),
        ])->withSum([
            'ventas as total_gastado' => fn (Builder $sales) => $sales->where('estado', 'completada'),
        ], 'total')->withMax([
            'ventas as ultima_compra' => fn (Builder $sales) => $sales->where('estado', 'completada'),
        ], 'created_at');

        $this->applySearch($query, $filters['buscar'] ?? null);
        $this->applyStatus($query, $filters['estado'] ?? null);
        $this->applyCity($query, $filters['ciudad'] ?? null);
        $this->applyActivity($query, $filters['actividad'] ?? null);
        $this->applyRegistrationDates($query, $filters);
        $this->applyLastPurchaseDates($query, $filters);
        $this->applyOrder($query, $filters['orden'] ?? 'recientes');

        return $query;
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $query->where(function (Builder $customers) use ($term) {
            $like = '%'.$term.'%';
            $customers->where('codigo', 'like', $like)
                ->orWhere('nombre', 'like', $like)
                ->orWhere('apellido', 'like', $like)
                ->orWhere('dni', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('telefono', 'like', $like);
        });
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if (in_array($status, ['activo', 'inactivo'], true)) {
            $query->where('activo', $status === 'activo');
        }
    }

    private function applyCity(Builder $query, ?string $city): void
    {
        if ($city !== null && $city !== '') {
            $query->where('ciudad', $city);
        }
    }

    private function applyActivity(Builder $query, ?string $activity): void
    {
        $completedSales = fn (Builder $sales) => $sales->where('estado', 'completada');

        if ($activity === 'con_compras') {
            $query->whereHas('ventas', $completedSales);
        } elseif ($activity === 'sin_compras') {
            $query->whereDoesntHave('ventas', $completedSales);
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyRegistrationDates(Builder $query, array $filters): void
    {
        if (! empty($filters['registrado_desde'])) {
            $query->whereDate('clientes.created_at', '>=', $filters['registrado_desde']);
        }
        if (! empty($filters['registrado_hasta'])) {
            $query->whereDate('clientes.created_at', '<=', $filters['registrado_hasta']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyLastPurchaseDates(Builder $query, array $filters): void
    {
        if (! empty($filters['ultima_compra_desde'])) {
            $query->whereHas('ventas', fn (Builder $sales) => $sales
                ->where('estado', 'completada')
                ->whereDate('created_at', '>=', $filters['ultima_compra_desde']));
        }

        if (! empty($filters['ultima_compra_hasta'])) {
            $query->whereHas('ventas', fn (Builder $sales) => $sales->where('estado', 'completada'))
                ->whereDoesntHave('ventas', fn (Builder $sales) => $sales
                    ->where('estado', 'completada')
                    ->whereDate('created_at', '>', $filters['ultima_compra_hasta']));
        }
    }

    private function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'antiguos' => $query->oldest('clientes.created_at'),
            'nombre_asc' => $query->orderBy('nombre')->orderBy('apellido'),
            'nombre_desc' => $query->orderByDesc('nombre')->orderByDesc('apellido'),
            'compras_desc' => $query->orderByDesc('compras_completadas')->orderByDesc('clientes.id'),
            'gasto_desc' => $query->orderByDesc('total_gastado')->orderByDesc('clientes.id'),
            default => $query->latest('clientes.created_at'),
        };
    }
}
