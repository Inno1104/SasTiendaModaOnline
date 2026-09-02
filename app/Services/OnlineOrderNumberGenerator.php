<?php

namespace App\Services;

use App\Models\PedidoOnline;
use App\Models\Tienda;
use Illuminate\Support\Facades\DB;
use LogicException;

final class OnlineOrderNumberGenerator
{
    public function next(Tienda $store, int $year): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('El correlativo del pedido online requiere una transacción activa.');
        }

        DB::table('pedido_online_contadores')->insertOrIgnore([
            'tienda_id' => $store->id,
            'anio' => $year,
            'ultimo_numero' => $this->highestExistingNumber((int) $store->id, $year),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('pedido_online_contadores')
            ->where('tienda_id', $store->id)
            ->where('anio', $year)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new LogicException('No fue posible inicializar el correlativo del pedido online.');
        }

        $number = (int) $counter->ultimo_numero + 1;
        DB::table('pedido_online_contadores')->where('id', $counter->id)->update([
            'ultimo_numero' => $number,
            'updated_at' => now(),
        ]);

        return 'WEB-'.$store->id.'-'.$year.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function highestExistingNumber(int $storeId, int $year): int
    {
        $prefix = 'WEB-'.$storeId.'-'.$year.'-';

        return PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $storeId)
            ->where('numero', 'like', $prefix.'%')
            ->pluck('numero')
            ->map(function (string $orderNumber) use ($prefix): int {
                $correlative = substr($orderNumber, strlen($prefix));

                return ctype_digit($correlative) ? (int) $correlative : 0;
            })
            ->max() ?? 0;
    }
}
