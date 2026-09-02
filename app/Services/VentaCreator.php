<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\DetalleVenta;
use App\Models\PagoVenta;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaCreator
{
    public function __construct(
        private readonly CommercialCalculator $calculator,
        private readonly ComprobanteNumberGenerator $numbers,
        private readonly PaymentResolver $paymentResolver,
        private readonly AuditLogger $audit,
        private readonly InventoryMovementService $stockMovements,
        private readonly OperationFingerprint $fingerprints,
        private readonly SaleCommercialSnapshot $snapshots
    ) {}

    public function create(array $data, User $user): Venta
    {
        return DB::transaction(function () use ($data, $user) {
            Tienda::query()->lockForUpdate()->findOrFail($user->tienda_id);
            $operationId = $data['operacion_id'] ?? null;
            $operationHash = $operationId ? $this->fingerprints->make($data) : null;
            if ($operationId) {
                $existingSale = Venta::query()->where('operacion_id', $operationId)->first();
                if ($existingSale) {
                    if (! hash_equals((string) $existingSale->operacion_hash, $operationHash)) {
                        throw ValidationException::withMessages([
                            'operacion_id' => 'La operación ya fue utilizada con datos diferentes.',
                        ]);
                    }

                    return $existingSale;
                }
            }

            $cashRegister = Caja::query()
                ->where('estado', 'abierta')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $cashRegister) {
                throw ValidationException::withMessages([
                    'caja' => 'Debes abrir una caja antes de registrar ventas.',
                ]);
            }

            $items = collect($data['items']);
            $products = Producto::query()
                ->whereIn('id', $items->pluck('producto_id')->unique())
                ->where('activo', true)
                ->whereHas('categoria', fn ($query) => $query->where('activo', true))
                ->get()
                ->keyBy('id');
            $variants = $this->stockMovements->lockVariants(
                $items->pluck('variante_id'),
                (int) $user->tienda_id
            );

            $details = $items->groupBy('variante_id')->map(function ($variantItems) use ($products, $variants) {
                $item = $variantItems->first();
                $product = $products->get($item['producto_id']);
                $variant = $variants->get($item['variante_id']);

                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'Uno de los productos ya no está disponible.']);
                }
                if (! $variant || (int) $variant->producto_id !== (int) $product->id) {
                    throw ValidationException::withMessages([
                        'items' => 'La talla o el color seleccionado no pertenece al producto.',
                    ]);
                }

                $quantity = (int) $variantItems->sum('cantidad');
                if ($quantity > (int) $variant->stock) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para {$product->nombre} ({$variant->talla} / {$variant->color}). Disponible: {$variant->stock}.",
                    ]);
                }

                $unitPrice = round($product->precioActual(), 2);

                return [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => round((float) $variantItems->sum(fn ($row) => $row['descuento'] ?? 0), 2),
                ];
            });

            $taxRate = (float) ($user->tienda->igv ?? 18);
            $calculation = $this->calculator->calculate(
                $details,
                (float) ($data['descuento_global'] ?? 0),
                $taxRate,
                (bool) $user->tienda->precios_incluyen_igv
            );
            $details = $calculation['lines'];
            $total = $calculation['total'];
            $payment = $this->paymentResolver->resolve($data, $total);
            $document = $this->numbers->next($user->tienda, $data['tipo_comprobante']);
            $sale = Venta::create([
                'operacion_id' => $operationId,
                'operacion_hash' => $operationHash,
                'numero_venta' => $document['numero_venta'],
                'cliente_id' => $data['cliente_id'] ?? null,
                'user_id' => $user->id,
                'caja_id' => $cashRegister->id,
                'fecha' => today(),
                'tipo_comprobante' => $data['tipo_comprobante'],
                'serie' => $document['serie'],
                'correlativo' => $document['correlativo'],
                'subtotal' => $calculation['subtotal'],
                'descuento' => $calculation['discount'],
                'igv' => $calculation['tax'],
                'total' => $total,
                'metodo_pago' => $payment['method'],
                'monto_pagado' => $payment['paid'],
                'vuelto' => $payment['change'],
                'estado' => 'completada',
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            foreach ($payment['payments'] as $salePayment) {
                PagoVenta::create(['venta_id' => $sale->id] + $salePayment);
            }

            foreach ($details as $detail) {
                DetalleVenta::create([
                    'venta_id' => $sale->id,
                    'producto_id' => $detail['product']->id,
                    'variante_id' => $detail['variant']->id,
                    'producto_nombre' => $detail['product']->nombre,
                    'talla' => $detail['variant']->talla,
                    'color' => $detail['variant']->color,
                    'cantidad' => $detail['quantity'],
                    'precio_unitario' => $detail['unit_price'],
                    'descuento' => $detail['discount'],
                    'subtotal' => $detail['subtotal'],
                ]);
                $this->stockMovements->setStock(
                    $detail['variant'],
                    (int) $detail['variant']->stock - $detail['quantity'],
                    StockMovimiento::TIPO_VENTA,
                    $user,
                    $sale,
                    'Salida por venta '.$sale->numero_venta
                );
            }

            $snapshot = $this->snapshots->capture($sale);
            $sale->forceFill([
                'commercial_snapshot' => $snapshot,
                'commercial_snapshot_hash' => $this->snapshots->hash($snapshot),
            ])->save();

            $this->audit->record($sale, 'venta.creada', 'Venta registrada', $user, [
                'numero' => $sale->numero_venta,
                'total' => $sale->total,
                'items' => $details->sum('quantity'),
                'pagos' => $payment['payments']->count(),
            ]);

            return $sale;
        }, 3);
    }
}
