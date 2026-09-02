<?php

namespace App\Services;

use App\Models\Venta;

class SaleCommercialSnapshot
{
    public const VERSION = 1;

    public function __construct(private readonly StoreLogoStorage $logos) {}

    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    public function capture(Venta $sale): array
    {
        $sale->loadMissing(['tienda', 'cliente', 'vendedor', 'detalles.producto', 'pagos']);
        $store = $sale->tienda;
        $customer = $sale->cliente;

        return [
            'version' => self::VERSION,
            'issued_at' => $sale->created_at?->toISOString(),
            'document' => [
                'type' => $sale->tipo_comprobante,
                'series' => $sale->serie,
                'correlative' => $sale->correlativo,
                'number' => $sale->numero_venta,
            ],
            'store' => [
                'name' => $store?->nombre,
                'tax_id' => $store?->ruc,
                'address' => $store?->direccion,
                'phone' => $store?->telefono,
                'email' => $store?->email,
                'logo' => $store ? $this->logos->snapshot($store->logo, (int) $store->id) : null,
            ],
            'customer' => [
                'name' => $customer?->nombreCompleto() ?? 'Cliente directo',
                'document' => $customer?->dni,
                'phone' => $customer?->telefono,
                'email' => $customer?->email,
                'address' => $customer?->direccion,
            ],
            'seller' => ['name' => $sale->vendedor?->name ?? 'No disponible'],
            'currency' => [
                'code' => $store?->moneda ?: 'USD',
                'symbol' => $store?->simbolo_moneda ?: '$',
            ],
            'tax' => [
                'rate' => (float) ($store?->igv ?? 0),
                'included' => (bool) ($store?->precios_incluyen_igv ?? false),
            ],
            'totals' => [
                'subtotal' => (string) $sale->subtotal,
                'discount' => (string) $sale->descuento,
                'tax' => (string) $sale->igv,
                'total' => (string) $sale->total,
                'paid' => (string) $sale->monto_pagado,
                'change' => (string) $sale->vuelto,
            ],
            'items' => $sale->detalles->map(fn ($detail) => [
                'name' => $detail->producto_nombre,
                'code' => $detail->producto?->codigo,
                'size' => $detail->talla,
                'color' => $detail->color,
                'quantity' => (int) $detail->cantidad,
                'unit_price' => (string) $detail->precio_unitario,
                'discount' => (string) $detail->descuento,
                'subtotal' => (string) $detail->subtotal,
            ])->values()->all(),
            'payments' => $sale->pagos->map(fn ($payment) => [
                'method' => $payment->metodo,
                'amount' => (string) $payment->monto,
                'reference' => $payment->referencia,
            ])->values()->all(),
            'notes' => $sale->observaciones,
        ];
    }
}
