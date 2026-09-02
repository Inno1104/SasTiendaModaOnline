<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venta;

class SaleStockRestorer
{
    public function __construct(
        private readonly StockLevelGuard $stockLevels,
        private readonly InventoryMovementService $stockMovements
    ) {}

    public function restore(Venta $sale, ?User $user, string $movementType, string $reason): int
    {
        $details = $sale->detalles()->whereNotNull('variante_id')->get();
        $variants = $this->stockMovements->lockVariants(
            $details->pluck('variante_id'),
            (int) $sale->tienda_id
        );

        foreach ($details->groupBy('variante_id') as $variantId => $variantDetails) {
            if (! $variant = $variants->get($variantId)) {
                continue;
            }

            $newStock = $this->stockLevels->afterIncrease(
                (int) $variant->stock,
                (int) $variantDetails->sum('cantidad'),
                'venta'
            );
            $this->stockMovements->setStock(
                $variant,
                $newStock,
                $movementType,
                $user,
                $sale,
                $reason
            );
        }

        return (int) $details->sum('cantidad');
    }
}
