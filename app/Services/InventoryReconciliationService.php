<?php

namespace App\Services;

use App\Models\ProductoVariante;
use App\Models\StockConteo;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Support\StockCountState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryReconciliationService
{
    public function __construct(
        private readonly InventoryMovementService $movements,
        private readonly AuditLogger $audit,
        private readonly StockLevelGuard $stockLevels
    ) {}

    public function count(
        ProductoVariante $variant,
        int $physicalStock,
        int $expectedVersion,
        User $user,
        ?string $reason,
        string $operationId
    ): StockConteo {
        return DB::transaction(function () use ($variant, $physicalStock, $expectedVersion, $user, $reason, $operationId) {
            $this->stockLevels->ensureValid($physicalStock);
            $reason = trim((string) $reason) ?: null;
            $operationId = mb_strtolower(trim($operationId));
            $operationHash = $this->operationHash($variant->id, $physicalStock, $expectedVersion, $reason);
            $lockedVariant = $this->movements->lockVariants([$variant->id], (int) $user->tienda_id)->get($variant->id);

            if (! $lockedVariant || ! $user->can('update', $lockedVariant)) {
                throw new AuthorizationException('No tienes permiso para contar esta variante.');
            }

            $existing = StockConteo::withoutGlobalScopes()
                ->where('tienda_id', $user->tienda_id)
                ->where('operacion_id', $operationId)
                ->first();
            if ($existing) {
                if (! hash_equals((string) $existing->operacion_hash, $operationHash)) {
                    throw ValidationException::withMessages([
                        'operacion_id' => 'La operación ya fue utilizada con datos diferentes.',
                    ]);
                }

                return $existing;
            }

            if ((int) $lockedVariant->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'stock_fisico' => 'El stock cambió. Actualiza el historial y repite el conteo físico.',
                ]);
            }

            $pendingCounts = StockConteo::query()
                ->where('variante_id', $lockedVariant->id)
                ->where('estado', StockConteo::ESTADO_PENDIENTE)
                ->lockForUpdate()
                ->get();
            $difference = $physicalStock - (int) $lockedVariant->stock;
            $autoApproved = $difference === 0;
            $count = StockConteo::create([
                'tienda_id' => $lockedVariant->tienda_id,
                'producto_id' => $lockedVariant->producto_id,
                'variante_id' => $lockedVariant->id,
                'contado_por' => $user->id,
                'stock_sistema' => $lockedVariant->stock,
                'stock_fisico' => $physicalStock,
                'diferencia' => $difference,
                'version_stock' => $lockedVariant->lock_version,
                'estado' => $autoApproved ? StockConteo::ESTADO_CONCILIADO : StockConteo::ESTADO_PENDIENTE,
                'motivo' => $reason,
                'operacion_id' => $operationId,
                'operacion_hash' => $operationHash,
                'autorizado_por' => $autoApproved ? $user->id : null,
                'conciliado_at' => $autoApproved ? now() : null,
            ]);

            foreach ($pendingCounts as $pendingCount) {
                $this->replace($pendingCount, $count, $user);
            }

            $action = $autoApproved ? 'stock.conteo_autoaprobado' : 'stock.conteo_registrado';
            $description = $autoApproved
                ? 'Conteo físico sin diferencias autoaprobado'
                : 'Conteo físico registrado';
            $this->audit->record($count, $action, $description, $user, [
                'stock_sistema' => $count->stock_sistema,
                'stock_fisico' => $count->stock_fisico,
                'diferencia' => $count->diferencia,
                'autoaprobado' => $autoApproved,
            ]);

            return $count;
        }, 3);
    }

    public function reconcile(StockConteo $count, User $user, string $reason): StockConteo
    {
        return DB::transaction(function () use ($count, $user, $reason) {
            $lockedCount = StockConteo::withoutGlobalScopes()
                ->where('id', $count->id)
                ->where('tienda_id', $user->tienda_id)
                ->lockForUpdate()
                ->first();
            if (! $lockedCount) {
                throw new AuthorizationException('No tienes permiso para conciliar este conteo.');
            }

            $variant = $this->movements->lockVariants([$lockedCount->variante_id], (int) $user->tienda_id)
                ->get($lockedCount->variante_id);
            if (! $variant || ! $user->can('reconcileStock', $variant)) {
                throw new AuthorizationException('No tienes permiso para conciliar este conteo.');
            }
            if ($lockedCount->estado === StockConteo::ESTADO_CONCILIADO) {
                return $lockedCount;
            }
            if (! StockCountState::canTransition($lockedCount->estado, StockConteo::ESTADO_CONCILIADO)) {
                throw ValidationException::withMessages(['conteo' => 'Este conteo ya no está pendiente.']);
            }
            if ((int) $variant->lock_version !== (int) $lockedCount->version_stock
                || (int) $variant->stock !== (int) $lockedCount->stock_sistema) {
                throw ValidationException::withMessages([
                    'conteo' => 'El inventario cambió después del conteo. Registra un nuevo conteo físico.',
                ]);
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages(['motivo' => 'Debes indicar un motivo de al menos 5 caracteres.']);
            }

            $this->movements->setStock(
                $variant,
                $lockedCount->stock_fisico,
                StockMovimiento::TIPO_CONCILIACION,
                $user,
                $lockedCount,
                $reason
            );
            $lockedCount->update([
                'estado' => StockConteo::ESTADO_CONCILIADO,
                'autorizado_por' => $user->id,
                'motivo' => $reason,
                'conciliado_at' => now(),
            ]);
            $this->audit->record($lockedCount, 'stock.conciliado', 'Inventario conciliado contra conteo físico', $user, [
                'stock_sistema' => $lockedCount->stock_sistema,
                'stock_fisico' => $lockedCount->stock_fisico,
                'diferencia' => $lockedCount->diferencia,
                'motivo' => $reason,
            ]);

            return $lockedCount;
        }, 3);
    }

    private function replace(StockConteo $pending, StockConteo $replacement, User $user): void
    {
        if (! StockCountState::canTransition($pending->estado, StockConteo::ESTADO_REEMPLAZADO)) {
            throw ValidationException::withMessages(['conteo' => 'El conteo anterior ya no puede reemplazarse.']);
        }

        $pending->update([
            'estado' => StockConteo::ESTADO_REEMPLAZADO,
            'reemplazado_por_conteo_id' => $replacement->id,
            'reemplazado_por_usuario_id' => $user->id,
            'reemplazado_at' => now(),
        ]);
        $this->audit->record($pending, 'stock.conteo_reemplazado', 'Conteo físico reemplazado', $user, [
            'reemplazado_por_conteo_id' => $replacement->id,
        ]);
    }

    private function operationHash(int $variantId, int $physicalStock, int $expectedVersion, ?string $reason): string
    {
        return hash('sha256', json_encode([
            'variante_id' => $variantId,
            'stock_fisico' => $physicalStock,
            'version_stock' => $expectedVersion,
            'motivo' => $reason,
        ], JSON_THROW_ON_ERROR));
    }
}
