<?php

namespace App\Services;

use App\Models\ProductoVariante;
use App\Models\StockMovimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventoryMovementService
{
    public function __construct(private readonly StockLevelGuard $stockLevels) {}

    /** @param iterable<int|string> $variantIds
     * @return Collection<int, ProductoVariante>
     */
    public function lockVariants(iterable $variantIds, int $storeId): Collection
    {
        $ids = collect($variantIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        return ProductoVariante::withoutGlobalScopes()
            ->where('tienda_id', $storeId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    public function setStock(
        ProductoVariante $variant,
        int $resultingStock,
        string $type,
        ?User $user = null,
        ?Model $reference = null,
        ?string $reason = null
    ): ?StockMovimiento {
        $this->ensureTransaction();
        $this->ensureValidType($type);
        $resultingStock = $this->stockLevels->ensureValid($resultingStock);
        $previousStock = (int) $variant->stock;
        $quantity = $resultingStock - $previousStock;

        if ($quantity === 0) {
            return null;
        }

        $this->ensureConsistentTenant($variant, $user, $reference);
        $variant->update([
            'stock' => $resultingStock,
            'lock_version' => (int) $variant->lock_version + 1,
        ]);

        return StockMovimiento::create([
            'tienda_id' => $variant->tienda_id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'user_id' => $user?->id,
            'tipo' => $type,
            'direccion' => $quantity > 0 ? 'entrada' : 'salida',
            'stock_anterior' => $previousStock,
            'cantidad' => $quantity,
            'stock_resultante' => $resultingStock,
            'referencia_type' => $reference?->getMorphClass(),
            'referencia_id' => $reference?->getKey(),
            'motivo' => $reason,
        ]);
    }

    public function recordInitialStock(
        ProductoVariante $variant,
        ?User $user = null,
        ?Model $reference = null
    ): ?StockMovimiento {
        $this->ensureTransaction();
        $stock = $this->stockLevels->ensureValid((int) $variant->stock);
        if ($stock === 0) {
            return null;
        }

        $this->ensureConsistentTenant($variant, $user, $reference);

        return StockMovimiento::create([
            'tienda_id' => $variant->tienda_id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'user_id' => $user?->id,
            'tipo' => StockMovimiento::TIPO_ENTRADA,
            'direccion' => 'entrada',
            'stock_anterior' => 0,
            'cantidad' => $stock,
            'stock_resultante' => $stock,
            'referencia_type' => $reference?->getMorphClass(),
            'referencia_id' => $reference?->getKey(),
            'motivo' => 'Stock inicial de la variante',
        ]);
    }

    private function ensureValidType(string $type): void
    {
        if (! in_array($type, [
            StockMovimiento::TIPO_ENTRADA,
            StockMovimiento::TIPO_SALIDA,
            StockMovimiento::TIPO_AJUSTE,
            StockMovimiento::TIPO_VENTA,
            StockMovimiento::TIPO_COMPRA,
            StockMovimiento::TIPO_CONCILIACION,
            StockMovimiento::TIPO_ANULACION,
            StockMovimiento::TIPO_DEVOLUCION,
        ], true)) {
            throw ValidationException::withMessages(['stock' => 'El tipo de movimiento no es válido.']);
        }
    }

    private function ensureTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Los movimientos de inventario requieren una transacción activa.');
        }
    }

    private function ensureConsistentTenant(
        ProductoVariante $variant,
        ?User $user,
        ?Model $reference
    ): void {
        if ($user !== null && (int) $user->tienda_id !== (int) $variant->tienda_id) {
            throw ValidationException::withMessages(['stock' => 'El responsable no pertenece a la tienda de la variante.']);
        }

        if ($reference !== null && isset($reference->tienda_id)
            && (int) $reference->tienda_id !== (int) $variant->tienda_id) {
            throw ValidationException::withMessages(['stock' => 'La referencia no pertenece a la tienda de la variante.']);
        }
    }
}
