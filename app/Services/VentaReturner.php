<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaReversion;
use App\Support\Money;
use App\Support\SaleStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaReturner
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SaleStockRestorer $stockRestorer,
        private readonly SaleFinancialReverser $financialReverser,
        private readonly CashRegisterSummary $cashSummary
    ) {}

    public function return(Venta $sale, User $user, string $reason): Venta
    {
        return DB::transaction(function () use ($sale, $user, $reason) {
            $lockedSale = Venta::query()->lockForUpdate()->findOrFail($sale->id);
            if (! SaleStatus::canReturn($lockedSale->estado)) {
                throw ValidationException::withMessages(['venta' => 'Solo una venta completada puede devolverse.']);
            }

            $originalRegister = $lockedSale->caja_id
                ? Caja::query()->lockForUpdate()->find($lockedSale->caja_id)
                : null;
            if (! $originalRegister || $originalRegister->estado !== 'cerrada') {
                throw ValidationException::withMessages([
                    'venta' => 'La caja original sigue abierta; corresponde anular la venta.',
                ]);
            }

            $returnRegister = Caja::query()
                ->where('estado', 'abierta')
                ->where('id', '<>', $originalRegister->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if (! $returnRegister) {
                throw ValidationException::withMessages([
                    'caja' => 'Abre una nueva caja antes de procesar la devolución.',
                ]);
            }

            $stockRestored = $this->stockRestorer->restore(
                $lockedSale,
                $user,
                StockMovimiento::TIPO_DEVOLUCION,
                $reason
            );
            $reversal = $this->financialReverser->reverse(
                $lockedSale,
                $user,
                VentaReversion::TIPO_DEVOLUCION,
                $reason,
                $returnRegister
            );

            if (Money::toCents($reversal['efectivo']) > 0) {
                $availableCash = Money::toCents($this->cashSummary->calculate($returnRegister)['efectivo_esperado']);
                if (Money::toCents($reversal['efectivo']) > $availableCash) {
                    throw ValidationException::withMessages([
                        'caja' => 'La caja abierta no dispone de efectivo suficiente para la devolución.',
                    ]);
                }

                MovimientoCaja::create([
                    'caja_id' => $returnRegister->id,
                    'user_id' => $user->id,
                    'venta_reversion_id' => $reversal['reversion_ids'][0] ?? null,
                    'tipo' => 'egreso',
                    'concepto' => 'Devolución de venta '.$lockedSale->numero_venta,
                    'monto' => $reversal['efectivo'],
                    'observaciones' => $reason,
                ]);
            }

            $lockedSale->update([
                'estado' => SaleStatus::RETURNED,
                'devuelta_por' => $user->id,
                'devuelta_at' => now(),
                'motivo_devolucion' => $reason,
                'caja_devolucion_id' => $returnRegister->id,
            ]);
            $this->audit->record($lockedSale, 'venta.devuelta', 'Venta devuelta', $user, [
                'numero' => $lockedSale->numero_venta,
                'motivo' => $reason,
                'stock_repuesto' => $stockRestored,
                'reversion_financiera' => $reversal,
                'caja_devolucion_id' => $returnRegister->id,
            ]);

            return $lockedSale;
        });
    }
}
