<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

final class DashboardPeriod
{
    /**
     * @return array{key:string, start:Carbon, end:Carbon, previous_start:Carbon, previous_end:Carbon, label:string}
     */
    public function resolve(
        string $key = '7_dias',
        ?string $customStart = null,
        ?string $customEnd = null,
        ?Carbon $today = null
    ): array {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        [$start, $end, $label] = match ($key) {
            'hoy' => [$today->copy(), $today->copy(), 'Hoy'],
            '7_dias' => [$today->copy()->subDays(6), $today->copy(), 'Últimos 7 días'],
            '30_dias' => [$today->copy()->subDays(29), $today->copy(), 'Últimos 30 días'],
            'mes_actual' => [$today->copy()->startOfMonth(), $today->copy(), 'Mes actual'],
            'mes_anterior' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
                'Mes anterior',
            ],
            'personalizado' => $this->customRange($customStart, $customEnd),
            default => throw new InvalidArgumentException('El periodo seleccionado no es válido.'),
        };

        $days = $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [
            'key' => $key,
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
            'previous_start' => $previousStart->startOfDay(),
            'previous_end' => $previousEnd->endOfDay(),
            'label' => $label,
        ];
    }

    private function customRange(?string $start, ?string $end): array
    {
        if (!$start || !$end) {
            throw new InvalidArgumentException('Debes indicar las dos fechas del periodo personalizado.');
        }

        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();

        if ($from->isAfter($to)) {
            throw new InvalidArgumentException('La fecha inicial debe ser anterior a la fecha final.');
        }

        return [$from, $to, $from->format('d/m/Y') . ' – ' . $to->format('d/m/Y')];
    }
}
