<?php

namespace App\Services;

use App\Models\Cliente;
use Carbon\CarbonImmutable;

class ClienteCommercialSummary
{
    /** @return array{compras: int, total_gastado: float, ticket_promedio: float, ultima_compra: CarbonImmutable|null} */
    public function calculate(Cliente $cliente): array
    {
        $summary = $cliente->ventas()
            ->where('estado', 'completada')
            ->selectRaw('COUNT(*) as compras, COALESCE(SUM(total), 0) as total_gastado, COALESCE(AVG(total), 0) as ticket_promedio, MAX(created_at) as ultima_compra')
            ->first();

        return [
            'compras' => (int) $summary->compras,
            'total_gastado' => round((float) $summary->total_gastado, 2),
            'ticket_promedio' => round((float) $summary->ticket_promedio, 2),
            'ultima_compra' => $summary->ultima_compra
                ? CarbonImmutable::parse($summary->ultima_compra)
                : null,
        ];
    }
}
