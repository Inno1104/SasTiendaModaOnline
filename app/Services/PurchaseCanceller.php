<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseCanceller
{
    public function __construct(
        private readonly StockLevelGuard $stockLevels,
        private readonly InventoryMovementService $stockMovements,
        private readonly AuditLogger $audit
    ) {}

    public function cancel(Compra $purchase, User $user, string $reason): Compra
    {
        $storeId = (int) $user->tienda_id;

        return DB::transaction(function () use ($purchase, $user, $reason, $storeId) {
            Tienda::query()->lockForUpdate()->findOrFail($storeId);
            $lockedPurchase = Compra::withoutGlobalScopes()
                ->whereKey($purchase->id)
                ->where('tienda_id', $storeId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPurchase->estado !== 'recibido') {
                throw ValidationException::withMessages([
                    'compra' => $lockedPurchase->estado === 'anulado'
                        ? 'La compra ya está anulada.'
                        : 'Solo se puede anular una compra recibida.',
                ]);
            }

            $details = $lockedPurchase->detalles()->get();
            if ($details->isEmpty() || $details->contains(fn ($detail) => $detail->variante_id === null)) {
                throw ValidationException::withMessages([
                    'compra' => 'La compra no tiene variantes completas para revertir el inventario.',
                ]);
            }

            $quantities = $details->groupBy('variante_id')
                ->map(fn ($variantDetails) => (int) $variantDetails->sum('cantidad'));
            $variants = $this->stockMovements->lockVariants($quantities->keys(), $storeId);

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get((int) $variantId);
                if (! $variant) {
                    throw ValidationException::withMessages([
                        'compra' => 'Una variante de la compra ya no está disponible.',
                    ]);
                }
                if ((int) $variant->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'compra' => "No existe stock suficiente para revertir {$variant->talla} / {$variant->color}. Disponible: {$variant->stock}; requerido: {$quantity}.",
                    ]);
                }
            }

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get((int) $variantId);
                $newStock = $this->stockLevels->afterDecrease((int) $variant->stock, $quantity, 'compra');
                $this->stockMovements->setStock(
                    $variant,
                    $newStock,
                    StockMovimiento::TIPO_ANULACION,
                    $user,
                    $lockedPurchase,
                    'Anulación de compra '.$lockedPurchase->numero_compra.': '.$reason
                );
            }

            $lockedPurchase->update([
                'estado' => 'anulado',
                'anulada_por' => $user->id,
                'anulada_at' => now(),
                'motivo_anulacion' => $reason,
            ]);

            $this->audit->record(
                $lockedPurchase,
                'compra.anulada',
                'Compra '.$lockedPurchase->numero_compra.' anulada y retirada del inventario.',
                $user,
                [
                    'numero_compra' => $lockedPurchase->numero_compra,
                    'estado_anterior' => 'recibido',
                    'estado_nuevo' => 'anulado',
                    'unidades_revertidas' => (int) $quantities->sum(),
                    'motivo' => $reason,
                ]
            );

            return $lockedPurchase->refresh();
        });
    }
}
