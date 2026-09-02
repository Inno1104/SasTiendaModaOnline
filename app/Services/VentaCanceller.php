<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaReversion;
use App\Support\SaleStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaCanceller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SaleStockRestorer $stockRestorer,
        private readonly SaleFinancialReverser $financialReverser
    ) {}

    public function cancel(Venta $sale, ?User $user = null, ?string $reason = null): Venta
    {
        return DB::transaction(function () use ($sale, $user, $reason) {
            $lockedSale = Venta::query()->lockForUpdate()->findOrFail($sale->id);

            if (! SaleStatus::canCancel($lockedSale->estado)) {
                throw ValidationException::withMessages(['venta' => 'Solo una venta completada puede anularse.']);
            }

            if ($lockedSale->caja_id) {
                $cashRegister = Caja::query()->lockForUpdate()->find($lockedSale->caja_id);
                if ($cashRegister?->estado === 'cerrada') {
                    throw ValidationException::withMessages([
                        'venta' => 'No se puede anular una venta perteneciente a una caja cerrada.',
                    ]);
                }
            }

            $stockRestored = $this->stockRestorer->restore(
                $lockedSale,
                $user,
                StockMovimiento::TIPO_ANULACION,
                $reason
            );
            $reversal = $this->financialReverser->reverse(
                $lockedSale,
                $user,
                VentaReversion::TIPO_ANULACION,
                $reason
            );

            $lockedSale->update([
                'estado' => SaleStatus::CANCELLED,
                'anulada_por' => $user?->id,
                'anulada_at' => now(),
                'motivo_anulacion' => $reason,
            ]);
            $this->audit->record($lockedSale, 'venta.anulada', 'Venta anulada', $user, [
                'numero' => $lockedSale->numero_venta,
                'motivo' => $reason,
                'stock_repuesto' => $stockRestored,
                'reversion_financiera' => $reversal,
            ]);

            return $lockedSale;
        });
    }
}
