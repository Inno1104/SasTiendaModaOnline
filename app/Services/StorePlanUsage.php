<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Tienda;
use App\Models\User;

final class StorePlanUsage
{
    private const WARNING_PERCENTAGE = 80;

    /** @return array<string, mixed> */
    public function for(Tienda $store): array
    {
        $store->loadMissing('plan');

        return [
            'plan' => $store->plan?->nombre ?? 'Sin plan',
            'status' => $store->estadoBadge(),
            'expires_at' => $store->fecha_vencimiento,
            'remaining_days' => $store->diasRestantes(),
            'products' => $this->resource(
                Producto::withoutGlobalScopes()->where('tienda_id', $store->id)->where('activo', true)->count(),
                $store->plan?->max_productos
            ),
            'users' => $this->resource(
                User::query()->where('tienda_id', $store->id)->where('es_super_admin', false)->count(),
                $store->plan?->max_usuarios
            ),
        ];
    }

    /** @return array{used:int, limit:?int, unlimited:bool, percentage:int, remaining:?int, state:string} */
    private function resource(int $used, mixed $configuredLimit): array
    {
        if ($configuredLimit === null) {
            return compact('used') + ['limit' => null, 'unlimited' => false, 'percentage' => 0, 'remaining' => null, 'state' => 'unavailable'];
        }

        $limit = (int) $configuredLimit;
        if ($limit === -1) {
            return compact('used', 'limit') + ['unlimited' => true, 'percentage' => 0, 'remaining' => null, 'state' => 'unlimited'];
        }

        $percentage = $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 100;

        return compact('used', 'limit', 'percentage') + [
            'unlimited' => false,
            'remaining' => max(0, $limit - $used),
            'state' => $used >= $limit ? 'reached' : ($percentage >= self::WARNING_PERCENTAGE ? 'warning' : 'available'),
        ];
    }
}
