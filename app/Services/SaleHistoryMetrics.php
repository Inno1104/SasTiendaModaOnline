<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class SaleHistoryMetrics
{
    public function calculate(Builder $query): array
    {
        $metrics = $query->toBase()->selectRaw(<<<'SQL'
            COUNT(*) AS results_count,
            SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN estado = 'anulada' THEN 1 ELSE 0 END) AS cancelled_count,
            SUM(CASE WHEN estado = 'devuelta' THEN 1 ELSE 0 END) AS returned_count,
            COALESCE(SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN estado = 'completada' THEN descuento ELSE 0 END), 0) AS discounts,
            COALESCE(SUM(CASE WHEN estado = 'completada' THEN igv ELSE 0 END), 0) AS taxes,
            COALESCE(AVG(CASE WHEN estado = 'completada' THEN total END), 0) AS average_ticket
        SQL)->first();

        return [
            'results_count' => (int) ($metrics->results_count ?? 0),
            'completed_count' => (int) ($metrics->completed_count ?? 0),
            'cancelled_count' => (int) ($metrics->cancelled_count ?? 0),
            'returned_count' => (int) ($metrics->returned_count ?? 0),
            'income' => round((float) ($metrics->income ?? 0), 2),
            'discounts' => round((float) ($metrics->discounts ?? 0), 2),
            'taxes' => round((float) ($metrics->taxes ?? 0), 2),
            'average_ticket' => round((float) ($metrics->average_ticket ?? 0), 2),
        ];
    }
}
