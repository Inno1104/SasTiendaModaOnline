<?php

namespace App\Services;

use App\Models\PedidoOnline;
use App\Models\PedidoOnlineDetalle;
use App\Models\PedidoOnlineDireccion;
use App\Models\ProductoVariante;
use App\Models\Tienda;
use App\Support\OnlineOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OnlineOrderCreator
{
    public function __construct(
        private readonly OnlineOrderNumberGenerator $numbers,
        private readonly StockReservationManager $reservations,
    ) {}

    /**
     * @param array{items:\Illuminate\Support\Collection,units:int,subtotal:float,adjusted:bool} $cart
     * @param array<string,mixed> $checkout
     */
    public function create(Tienda $store, array $cart, array $checkout, string $operationId): PedidoOnline
    {
        return DB::transaction(function () use ($store, $cart, $checkout, $operationId): PedidoOnline {
            $lockedStore = Tienda::query()->lockForUpdate()->findOrFail($store->id);
            $existing = PedidoOnline::withoutGlobalScopes()
                ->where('tienda_id', $lockedStore->id)
                ->where('operacion_id', $operationId)
                ->first();
            if ($existing) {
                return $existing;
            }

            if (! $lockedStore->estaPublicada() || $cart['items']->isEmpty() || empty($checkout['updated_at'])) {
                throw ValidationException::withMessages([
                    'pedido' => 'El carrito o los datos de entrega ya no están disponibles.',
                ]);
            }
            if ($cart['adjusted']) {
                throw ValidationException::withMessages([
                    'pedido' => 'La disponibilidad cambió y ajustamos el carrito. Revísalo antes de confirmar.',
                ]);
            }

            $requested = $cart['items']->keyBy('variant_id');
            $variants = $this->lockVariants($lockedStore, $requested->keys()->map(fn ($id) => (int) $id)->all());
            if ($variants->count() !== $requested->count()) {
                throw ValidationException::withMessages([
                    'pedido' => 'Uno de los productos dejó de estar disponible.',
                ]);
            }

            $lines = $variants->map(function (ProductoVariante $variant) use ($requested): array {
                $cartItem = $requested->get($variant->id);
                $quantity = (int) $cartItem['quantity'];
                if ($quantity < 1 || $quantity > $variant->stock || $quantity > PublicCartManager::MAX_QUANTITY_PER_LINE) {
                    throw ValidationException::withMessages([
                        'pedido' => "El stock de {$variant->producto->nombre} cambió. Revisa el carrito.",
                    ]);
                }

                $unitPrice = round($variant->producto->precioActual(), 2);

                return [
                    'product_id' => (int) $variant->producto->id,
                    'variant_id' => (int) $variant->id,
                    'code' => $variant->producto->codigo,
                    'name' => $variant->producto->nombre,
                    'size' => $variant->talla,
                    'color' => $variant->color,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => 0.0,
                    'subtotal' => round($unitPrice * $quantity, 2),
                ];
            })->values();

            $subtotal = round((float) $lines->sum('subtotal'), 2);
            $reservationExpiresAt = now()->addMinutes(StockReservationManager::RESERVATION_MINUTES);
            $operationHash = hash('sha256', json_encode([
                'store_id' => (int) $lockedStore->id,
                'checkout' => $checkout,
                'lines' => $lines->all(),
            ], JSON_THROW_ON_ERROR));

            $order = PedidoOnline::withoutGlobalScopes()->create([
                'tienda_id' => $lockedStore->id,
                'operacion_id' => $operationId,
                'operacion_hash' => $operationHash,
                'numero' => $this->numbers->next($lockedStore, (int) now()->year),
                'estado' => OnlineOrderStatus::AWAITING_PAYMENT,
                'cliente_nombre' => data_get($checkout, 'cliente.nombre'),
                'cliente_apellido' => data_get($checkout, 'cliente.apellido'),
                'cliente_email' => data_get($checkout, 'cliente.email'),
                'cliente_telefono' => data_get($checkout, 'cliente.telefono'),
                'requiere_factura' => (bool) data_get($checkout, 'fiscal.requiere_factura'),
                'tipo_identificacion' => data_get($checkout, 'fiscal.tipo_identificacion'),
                'identificacion' => data_get($checkout, 'fiscal.identificacion'),
                'razon_social' => data_get($checkout, 'fiscal.razon_social'),
                'subtotal' => $subtotal,
                'descuento' => 0,
                'envio' => 0,
                'total' => $subtotal,
                'moneda' => $lockedStore->moneda ?: 'USD',
                'simbolo_moneda' => $lockedStore->simbolo_moneda ?: '$',
                'reserva_expira_at' => $reservationExpiresAt,
            ]);

            foreach ($lines as $line) {
                PedidoOnlineDetalle::withoutGlobalScopes()->create([
                    'tienda_id' => $lockedStore->id,
                    'pedido_online_id' => $order->id,
                    'producto_id' => $line['product_id'],
                    'variante_id' => $line['variant_id'],
                    'producto_codigo' => $line['code'],
                    'producto_nombre' => $line['name'],
                    'talla' => $line['size'],
                    'color' => $line['color'],
                    'cantidad' => $line['quantity'],
                    'precio_unitario' => $line['unit_price'],
                    'descuento' => $line['discount'],
                    'subtotal' => $line['subtotal'],
                ]);
            }

            $this->createAddresses($order, $checkout);
            $this->reservations->reserve($order, $variants, $lines, $reservationExpiresAt);

            return $order->load('detalles', 'direcciones', 'reservas');
        }, 3);
    }

    /** @param list<int> $variantIds */
    private function lockVariants(Tienda $store, array $variantIds)
    {
        return ProductoVariante::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->whereIn('id', $variantIds)
            ->whereHas('producto', fn (Builder $products) => $products
                ->withoutGlobalScopes()
                ->where('tienda_id', $store->id)
                ->where('activo', true)
                ->whereHas('categoria', fn (Builder $categories) => $categories
                    ->withoutGlobalScopes()
                    ->where('tienda_id', $store->id)
                    ->where('activo', true)))
            ->with(['producto' => fn ($products) => $products
                ->withoutGlobalScopes()
                ->select(['id', 'tienda_id', 'categoria_id', 'codigo', 'nombre', 'precio_venta', 'precio_oferta'])])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @param array<string,mixed> $checkout */
    private function createAddresses(PedidoOnline $order, array $checkout): void
    {
        $delivery = $checkout['entrega'];
        $recipient = trim($order->cliente_nombre.' '.$order->cliente_apellido);
        PedidoOnlineDireccion::withoutGlobalScopes()->create([
            'tienda_id' => $order->tienda_id,
            'pedido_online_id' => $order->id,
            'tipo' => PedidoOnlineDireccion::DELIVERY,
            'destinatario' => $recipient,
            'telefono' => $order->cliente_telefono,
            'pais_codigo' => $delivery['pais_codigo'],
            'provincia' => $delivery['provincia'],
            'ciudad' => $delivery['ciudad'],
            'codigo_postal' => $delivery['codigo_postal'],
            'direccion' => $delivery['direccion'],
            'referencia' => $delivery['referencia'],
        ]);

        if (! $order->requiere_factura) {
            return;
        }

        PedidoOnlineDireccion::withoutGlobalScopes()->create([
            'tienda_id' => $order->tienda_id,
            'pedido_online_id' => $order->id,
            'tipo' => PedidoOnlineDireccion::FISCAL,
            'destinatario' => $order->razon_social,
            'telefono' => $order->cliente_telefono,
            'pais_codigo' => $delivery['pais_codigo'],
            'provincia' => $delivery['provincia'],
            'ciudad' => $delivery['ciudad'],
            'codigo_postal' => $delivery['codigo_postal'],
            'direccion' => data_get($checkout, 'fiscal.direccion_fiscal') ?: $delivery['direccion'],
            'referencia' => null,
        ]);
    }
}
