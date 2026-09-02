<?php

namespace App\Services;

use App\Models\Tienda;
use App\Models\Venta;
use App\Support\DocumentNumbering;
use Illuminate\Support\Facades\DB;
use LogicException;

class ComprobanteNumberGenerator
{
    public function next(Tienda $store, string $type): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('El correlativo del comprobante requiere una transacción activa.');
        }

        $series = $this->seriesFor($store, $type);
        $counterExists = DB::table('comprobante_contadores')
            ->where('tienda_id', $store->id)
            ->where('tipo', $type)
            ->where('serie', $series)
            ->exists();

        if (! $counterExists) {
            DB::table('comprobante_contadores')->insertOrIgnore([
                'tienda_id' => $store->id,
                'tipo' => $type,
                'serie' => $series,
                'ultimo_numero' => $this->highestExistingNumber($store->id, $type, $series),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $counter = DB::table('comprobante_contadores')
            ->where('tienda_id', $store->id)
            ->where('tipo', $type)
            ->where('serie', $series)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new LogicException('No fue posible inicializar el correlativo del comprobante.');
        }

        $number = (int) $counter->ultimo_numero + 1;

        if ($number > DocumentNumbering::MAX_CORRELATIVE) {
            throw new LogicException('La serie del comprobante agotó sus correlativos disponibles.');
        }

        DB::table('comprobante_contadores')->where('id', $counter->id)->update([
            'ultimo_numero' => $number,
            'updated_at' => now(),
        ]);

        $correlative = str_pad((string) $number, DocumentNumbering::CORRELATIVE_LENGTH, '0', STR_PAD_LEFT);

        return [
            'serie' => $series,
            'correlativo' => $correlative,
            'numero_venta' => $series.'-'.$correlative,
        ];
    }

    private function seriesFor(Tienda $store, string $type): string
    {
        return match ($type) {
            'boleta' => $store->serie_boleta ?: 'B001',
            'factura' => $store->serie_factura ?: 'F001',
            'ticket' => $store->serie_ticket ?: 'T001',
            default => throw new \InvalidArgumentException('Tipo de comprobante no soportado.'),
        };
    }

    private function highestExistingNumber(int $storeId, string $type, string $series): int
    {
        return Venta::withoutGlobalScope('tienda')
            ->where('tienda_id', $storeId)
            ->where('tipo_comprobante', $type)
            ->where('serie', $series)
            ->pluck('correlativo')
            ->map(fn ($value) => ctype_digit((string) $value) ? (int) $value : 0)
            ->max() ?? 0;
    }
}
