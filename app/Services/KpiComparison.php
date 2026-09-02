<?php

namespace App\Services;

final class KpiComparison
{
    /**
     * Compara dos importes sin producir porcentajes infinitos cuando el
     * periodo anterior no tuvo actividad.
     *
     * @return array{direction:string, percentage:?float, text:string}
     */
    public function between(float $current, float $previous, string $periodLabel): array
    {
        if ($previous == 0.0) {
            if ($current == 0.0) {
                return [
                    'direction' => 'neutral',
                    'percentage' => null,
                    'text' => "Sin cambios vs {$periodLabel}",
                ];
            }

            return [
                'direction' => 'up',
                'percentage' => null,
                'text' => "No hubo ventas {$periodLabel}",
            ];
        }

        $percentage = round((($current - $previous) / abs($previous)) * 100, 1);

        return [
            'direction' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'neutral'),
            'percentage' => $percentage,
            'text' => $this->formatPercentage($percentage) . " vs {$periodLabel}",
        ];
    }

    private function formatPercentage(float $percentage): string
    {
        $formatted = number_format(abs($percentage), 1, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . '%';
    }
}
