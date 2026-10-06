<?php

namespace App\Services;

use App\Models\PedidoOnline;
use App\Models\ProductoVariante;
use App\Models\StockMovimiento;
use App\Models\StockReserva;
use App\Models\User;
use App\Support\OnlineOrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class StockReservationManager
{
    public const RESERVATION_MINUTES = 15;

    public function __construct(private readonly InventoryMovementService $stockMovements) {}

    /**
     * @param  Collection<int,ProductoVariante>  $lockedVariants
     * @param  Collection<int,array<string,mixed>>  $lines
     */
    public function reserve(PedidoOnline $order, Collection $lockedVariants, Collection $lines, CarbonInterface $expiresAt): void
    {
        $this->ensureTransaction();
        $linesByVariant = $lines->keyBy('variant_id');

        foreach ($lockedVariants->sortBy('id') as $variant) {
            $quantity = (int) data_get($linesByVariant->get($variant->id), 'quantity', 0);
            $reserved = (int) StockReserva::withoutGlobalScopes()
                ->where('tienda_id', $order->tienda_id)
                ->where('variante_id', $variant->id)
                ->vigentes()
                ->sum('cantidad');
            $available = max(0, (int) $variant->stock - $reserved);
            if ($quantity < 1 || $quantity > $available) {
                throw ValidationException::withMessages([
                    'pedido' => "Solo quedan {$available} unidades disponibles de {$variant->producto->nombre}.",
                ]);
            }

            StockReserva::withoutGlobalScopes()->create([
                'tienda_id' => $order->tienda_id,
                'pedido_online_id' => $order->id,
                'variante_id' => $variant->id,
                'cantidad' => $quantity,
                'estado' => StockReserva::ACTIVE,
                'expira_at' => $expiresAt,
            ]);
        }
    }

    public function releaseExpired(?CarbonInterface $cutoff = null): int
    {
        $cutoff ??= now();
        $orderIds = StockReserva::withoutGlobalScopes()
            ->where('estado', StockReserva::ACTIVE)
            ->where('expira_at', '<=', $cutoff)
            ->distinct()
            ->pluck('pedido_online_id');
        $released = 0;

        foreach ($orderIds as $orderId) {
            $released += DB::transaction(function () use ($orderId, $cutoff): int {
                $order = PedidoOnline::withoutGlobalScopes()->whereKey($orderId)->lockForUpdate()->first();
                if (! $order) {
                    return 0;
                }

                $reservations = StockReserva::withoutGlobalScopes()
                    ->where('pedido_online_id', $order->id)
                    ->where('estado', StockReserva::ACTIVE)
                    ->where('expira_at', '<=', $cutoff)
                    ->orderBy('variante_id')
                    ->lockForUpdate()
                    ->get();
                if ($reservations->isEmpty()) {
                    return 0;
                }

                StockReserva::withoutGlobalScopes()->whereKey($reservations->modelKeys())->update([
                    'estado' => StockReserva::EXPIRED,
                    'liberada_at' => $cutoff,
                    'motivo_liberacion' => 'Tiempo de pago vencido',
                    'updated_at' => $cutoff,
                ]);

                if (in_array($order->estado, [OnlineOrderStatus::PENDING, OnlineOrderStatus::AWAITING_PAYMENT], true)) {
                    $order->forceFill([
                        'estado' => OnlineOrderStatus::CANCELLED,
                        'cancelado_at' => $cutoff,
                        'motivo_cancelacion' => 'Reserva de inventario vencida',
                    ])->save();
                }

                return $reservations->count();
            }, 3);
        }

        return $released;
    }

    public function consume(PedidoOnline $order, ?User $user = null, ?Model $reference = null): void
    {
        $this->ensureTransaction();
        $reservations = StockReserva::withoutGlobalScopes()->where('pedido_online_id', $order->id)
            ->where('estado', StockReserva::ACTIVE)->orderBy('variante_id')->lockForUpdate()->get();
        if ($reservations->isEmpty()) {
            throw ValidationException::withMessages(['pago' => 'La reserva del pedido ya no está disponible.']);
        }
        foreach ($reservations as $reservation) {
            $variant = ProductoVariante::withoutGlobalScopes()->whereKey($reservation->variante_id)->lockForUpdate()->firstOrFail();
            if ($variant->stock < $reservation->cantidad) {
                throw ValidationException::withMessages(['pago' => 'El inventario físico ya no permite confirmar este pago.']);
            }
            $this->stockMovements->setStock(
                $variant,
                (int) $variant->stock - $reservation->cantidad,
                StockMovimiento::TIPO_VENTA,
                $user,
                $reference ?? $order,
                'Salida por pedido online '.$order->numero,
            );
            $reservation->forceFill(['estado' => StockReserva::CONSUMED, 'consumida_at' => now()])->save();
        }
    }

    public function cancel(PedidoOnline $order, CarbonInterface $cancelledAt): void
    {
        $this->ensureTransaction();
        $reservations = StockReserva::withoutGlobalScopes()
            ->where('pedido_online_id', $order->id)
            ->whereIn('estado', [StockReserva::ACTIVE, StockReserva::CONSUMED])
            ->orderBy('variante_id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            if ($reservation->estado === StockReserva::ACTIVE) {
                $reservation->forceFill([
                    'estado' => StockReserva::RELEASED,
                    'liberada_at' => $cancelledAt,
                    'motivo_liberacion' => 'Pedido cancelado por la tienda',
                ])->save();

                continue;
            }

            $variant = ProductoVariante::withoutGlobalScopes()
                ->where('tienda_id', $order->tienda_id)
                ->whereKey($reservation->variante_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($variant->stock > ProductoVariante::MAX_STOCK - $reservation->cantidad) {
                throw ValidationException::withMessages([
                    'pedido' => 'No se puede reponer el inventario porque superaría el límite permitido.',
                ]);
            }
            $variant->increment('stock', $reservation->cantidad);
        }
    }

    private function ensureTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Las reservas de inventario requieren una transacción activa.');
        }
    }
}
