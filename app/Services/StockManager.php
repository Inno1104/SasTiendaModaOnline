<?php

namespace App\Services;

use App\Models\ProductoVariante;
use App\Models\StockMovimiento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StockLevelGuard $stockLevels,
        private readonly InventoryMovementService $movements
    ) {}

    public function adjust(
        ProductoVariante $variant,
        int $stock,
        int $minimumStock,
        int $expectedVersion,
        User $user,
        ?string $reason = null
    ): ProductoVariante {
        return DB::transaction(function () use ($variant, $stock, $minimumStock, $expectedVersion, $user, $reason) {
            $lockedVariant = $this->movements
                ->lockVariants([$variant->id], (int) $user->tienda_id)
                ->get($variant->id);

            if (! $lockedVariant || ! $user->can('update', $lockedVariant)) {
                throw new AuthorizationException('No tienes permiso para ajustar esta variante.');
            }

            $productBelongsToStore = $lockedVariant->producto()
                ->withoutGlobalScopes()
                ->where('tienda_id', $lockedVariant->tienda_id)
                ->exists();
            if (! $productBelongsToStore) {
                throw ValidationException::withMessages([
                    'stock' => 'La variante no pertenece a un producto válido de la tienda.',
                ]);
            }

            if ((int) $lockedVariant->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'stock' => 'El stock cambió desde que abriste la pantalla. Actualiza la página e inténtalo nuevamente.',
                ]);
            }

            $this->stockLevels->ensureValid($stock);
            $this->stockLevels->ensureValid($minimumStock, 'stock_minimo');

            if ((int) $lockedVariant->stock === $stock
                && (int) $lockedVariant->stock_minimo === $minimumStock) {
                return $lockedVariant;
            }

            $reason = trim((string) $reason);
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'motivo' => 'Debes indicar un motivo de al menos 5 caracteres para ajustar el inventario.',
                ]);
            }

            $previous = [
                'stock' => (int) $lockedVariant->stock,
                'stock_minimo' => (int) $lockedVariant->stock_minimo,
            ];
            $stockChanged = (int) $lockedVariant->stock !== $stock;
            if ($stockChanged) {
                $this->movements->setStock(
                    $lockedVariant,
                    $stock,
                    StockMovimiento::TIPO_AJUSTE,
                    $user,
                    null,
                    $reason
                );
            }

            if ((int) $lockedVariant->stock_minimo !== $minimumStock) {
                $lockedVariant->update([
                    'stock_minimo' => $minimumStock,
                    'lock_version' => $stockChanged
                        ? $lockedVariant->lock_version
                        : $expectedVersion + 1,
                ]);
            }

            $this->audit->record(
                $lockedVariant,
                'stock.ajustado',
                'Stock de variante ajustado manualmente',
                $user,
                ['motivo' => $reason, 'anterior' => $previous, 'nuevo' => ['stock' => $stock, 'stock_minimo' => $minimumStock]]
            );

            return $lockedVariant;
        }, 3);
    }
}
