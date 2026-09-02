<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\StockMovimiento;
use Illuminate\Support\Collection;

class PurchaseTraceService
{
    /**
     * Concilia las cantidades documentadas con los movimientos inmutables del kardex.
     *
     * @return array{conciliado: bool, esperado_recibido: int, registrado_recibido: int,
     *     esperado_revertido: int, registrado_revertido: int, neto_esperado: int,
     *     neto_registrado: int, variantes: Collection, movimientos: Collection}
     */
    public function trace(Compra $purchase): array
    {
        $details = $purchase->relationLoaded('detalles')
            ? $purchase->detalles
            : $purchase->detalles()->with(['producto', 'variante'])->get();

        $movements = StockMovimiento::withoutGlobalScopes()
            ->where('tienda_id', $purchase->tienda_id)
            ->where('referencia_type', $purchase->getMorphClass())
            ->where('referencia_id', $purchase->id)
            ->with(['responsable', 'producto', 'variante'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $documented = $details->groupBy('variante_id')
            ->map(fn ($rows) => (int) $rows->sum('cantidad'));
        $variantIds = $documented->keys()->merge($movements->pluck('variante_id'))->unique();

        $variants = $variantIds->map(function ($variantId) use ($documented, $movements, $purchase) {
            $variantMovements = $movements->where('variante_id', (int) $variantId);
            $received = (int) $variantMovements->where('tipo', StockMovimiento::TIPO_COMPRA)->sum('cantidad');
            $reversed = abs((int) $variantMovements->where('tipo', StockMovimiento::TIPO_ANULACION)->sum('cantidad'));
            $expectedReceived = (int) ($documented->get($variantId) ?? 0);
            $expectedReversed = $purchase->estado === 'anulado' ? $expectedReceived : 0;
            $arithmeticIsValid = $variantMovements->every(fn (StockMovimiento $movement) => $movement->stock_resultante === $movement->stock_anterior + $movement->cantidad
                && $movement->direccion === ($movement->cantidad > 0 ? 'entrada' : 'salida')
            );

            $sample = $variantMovements->first();
            $detail = $purchase->detalles->firstWhere('variante_id', (int) $variantId);

            return [
                'variante_id' => (int) $variantId,
                'producto' => $detail?->producto?->nombre ?? $sample?->producto?->nombre ?? 'Producto no disponible',
                'variante' => $detail?->variante
                    ? $detail->variante->talla.' / '.$detail->variante->color
                    : ($sample?->variante ? $sample->variante->talla.' / '.$sample->variante->color : 'Variante no disponible'),
                'esperado_recibido' => $expectedReceived,
                'registrado_recibido' => $received,
                'esperado_revertido' => $expectedReversed,
                'registrado_revertido' => $reversed,
                'conciliado' => $arithmeticIsValid
                    && $received === $expectedReceived
                    && $reversed === $expectedReversed,
            ];
        })->values();

        $expectedReceived = (int) $documented->sum();
        $registeredReceived = (int) $movements->where('tipo', StockMovimiento::TIPO_COMPRA)->sum('cantidad');
        $expectedReversed = $purchase->estado === 'anulado' ? $expectedReceived : 0;
        $registeredReversed = abs((int) $movements->where('tipo', StockMovimiento::TIPO_ANULACION)->sum('cantidad'));

        return [
            'conciliado' => $variants->every(fn (array $variant) => $variant['conciliado']),
            'esperado_recibido' => $expectedReceived,
            'registrado_recibido' => $registeredReceived,
            'esperado_revertido' => $expectedReversed,
            'registrado_revertido' => $registeredReversed,
            'neto_esperado' => $expectedReceived - $expectedReversed,
            'neto_registrado' => $registeredReceived - $registeredReversed,
            'variantes' => $variants,
            'movimientos' => $movements,
        ];
    }
}
