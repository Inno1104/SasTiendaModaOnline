<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Tienda;
use Illuminate\Support\Facades\DB;
use LogicException;

class PurchaseNumberGenerator
{
    public function next(Tienda $store, int $year): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('El correlativo de compra requiere una transacción activa.');
        }

        DB::table('compra_contadores')->insertOrIgnore([
            'tienda_id' => $store->id,
            'anio' => $year,
            'ultimo_numero' => $this->highestExistingNumber((int) $store->id, $year),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('compra_contadores')
            ->where('tienda_id', $store->id)
            ->where('anio', $year)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new LogicException('No fue posible inicializar el correlativo de compra.');
        }

        $number = (int) $counter->ultimo_numero + 1;
        DB::table('compra_contadores')->where('id', $counter->id)->update([
            'ultimo_numero' => $number,
            'updated_at' => now(),
        ]);

        return 'C-'.$store->id.'-'.$year.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    private function highestExistingNumber(int $storeId, int $year): int
    {
        $prefix = 'C-'.$storeId.'-'.$year.'-';

        return Compra::withoutGlobalScopes()
            ->where('tienda_id', $storeId)
            ->whereYear('fecha', $year)
            ->where('numero_compra', 'like', $prefix.'%')
            ->pluck('numero_compra')
            ->map(function ($purchaseNumber) use ($prefix) {
                $correlative = substr((string) $purchaseNumber, strlen($prefix));

                return ctype_digit($correlative) ? (int) $correlative : 0;
            })
            ->max() ?? 0;
    }
}
