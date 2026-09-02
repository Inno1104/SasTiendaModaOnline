<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly StockLevelGuard $stockLevels,
        private readonly InventoryMovementService $stockMovements,
        private readonly OperationFingerprint $fingerprints,
        private readonly PurchaseNumberGenerator $numbers,
        private readonly PurchaseCalculator $calculator,
        private readonly AuditLogger $audit
    ) {}

    /** @param array<string, mixed> $data */
    public function receive(array $data, User $user): Compra
    {
        $storeId = (int) $user->tienda_id;
        if ($storeId < 1 || $user->es_super_admin || ! $user->activo) {
            throw ValidationException::withMessages(['compra' => 'El usuario no pertenece a una tienda activa.']);
        }

        return DB::transaction(function () use ($data, $user, $storeId) {
            $store = Tienda::query()->lockForUpdate()->findOrFail($storeId);

            $items = $this->calculator->calculate($data['items']);
            $normalizedData = array_replace($data, ['items' => $items->all()]);
            $operationId = $normalizedData['operacion_id'] ?? null;
            $operationHash = $operationId ? $this->fingerprints->make($normalizedData) : null;
            if ($existing = $this->existingOperation($storeId, $operationId, $operationHash)) {
                return $existing;
            }

            $this->lockActiveSupplier((int) $data['proveedor_id'], $storeId);

            $variants = $this->stockMovements->lockVariants($items->pluck('variante_id'), $storeId);
            $products = Producto::withoutGlobalScopes()
                ->where('tienda_id', $storeId)
                ->where('activo', true)
                ->whereIn('id', $variants->pluck('producto_id'))
                ->get()
                ->keyBy('id');

            $purchase = Compra::withoutGlobalScopes()->create([
                'tienda_id' => $storeId,
                'operacion_id' => $operationId,
                'operacion_hash' => $operationHash,
                'numero_compra' => $this->numbers->next(
                    $store,
                    CarbonImmutable::createFromFormat('!Y-m-d', $data['fecha'])->year
                ),
                'proveedor_id' => $data['proveedor_id'],
                'user_id' => $user->id,
                'fecha' => $data['fecha'],
                'estado' => 'recibido',
                'observaciones' => $data['observaciones'] ?? null,
                'total' => 0,
            ]);

            foreach ($items as $index => $item) {
                $variant = $variants->get((int) $item['variante_id']);
                $product = $variant ? $products->get((int) $variant->producto_id) : null;
                if (! $variant || ! $product) {
                    throw ValidationException::withMessages([
                        "items.$index.variante_id" => 'La variante ya no está activa o no pertenece a esta tienda.',
                    ]);
                }

                $quantity = (int) $item['cantidad'];

                DetalleCompra::withoutGlobalScopes()->create([
                    'tienda_id' => $storeId,
                    'compra_id' => $purchase->id,
                    'producto_id' => $product->id,
                    'variante_id' => $variant->id,
                    'cantidad' => $quantity,
                    'precio_unitario' => $item['precio'],
                    'subtotal' => $item['subtotal'],
                ]);

                $newStock = $this->stockLevels->afterIncrease((int) $variant->stock, $quantity, 'items');
                $this->stockMovements->setStock(
                    $variant,
                    $newStock,
                    StockMovimiento::TIPO_COMPRA,
                    $user,
                    $purchase,
                    'Recepción de compra '.$purchase->numero_compra
                );
            }

            $purchase->update(['total' => $this->calculator->total($items)]);

            $this->audit->record(
                $purchase,
                'compra.recibida',
                'Compra '.$purchase->numero_compra.' recibida e ingresada al inventario.',
                $user,
                [
                    'numero_compra' => $purchase->numero_compra,
                    'proveedor_id' => (int) $purchase->proveedor_id,
                    'estado' => 'recibido',
                    'lineas' => $items->count(),
                    'unidades' => (int) $items->sum('cantidad'),
                    'total' => $purchase->fresh()->total,
                ]
            );

            return $purchase->refresh();
        });
    }

    private function lockActiveSupplier(int $supplierId, int $storeId): void
    {
        $exists = Proveedor::withoutGlobalScopes()
            ->whereKey($supplierId)
            ->where('tienda_id', $storeId)
            ->where('activo', true)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'Selecciona un proveedor activo de esta tienda.',
            ]);
        }
    }

    private function existingOperation(int $storeId, ?string $operationId, ?string $operationHash): ?Compra
    {
        if (! $operationId) {
            return null;
        }

        $purchase = Compra::withoutGlobalScopes()
            ->where('tienda_id', $storeId)
            ->where('operacion_id', $operationId)
            ->first();

        if ($purchase && ! hash_equals((string) $purchase->operacion_hash, (string) $operationHash)) {
            throw ValidationException::withMessages([
                'operacion_id' => 'La operación ya fue utilizada con datos diferentes.',
            ]);
        }

        return $purchase;
    }
}
