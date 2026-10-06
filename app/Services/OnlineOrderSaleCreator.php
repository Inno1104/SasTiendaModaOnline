<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\PagoVenta;
use App\Models\PedidoOnline;
use App\Models\User;
use App\Models\Venta;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OnlineOrderSaleCreator
{
    public function __construct(
        private readonly ClienteCreator $customers,
        private readonly CommercialCalculator $calculator,
        private readonly ComprobanteNumberGenerator $numbers,
        private readonly SaleCommercialSnapshot $snapshots,
        private readonly AuditLogger $audit,
    ) {}

    public function create(PedidoOnline $order, User $user): Venta
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('La facturación del pedido requiere una transacción activa.');
        }
        if ((int) $order->tienda_id !== (int) $user->tienda_id) {
            throw ValidationException::withMessages(['pedido' => 'El pedido no pertenece a la tienda del responsable.']);
        }

        if ($order->venta_id) {
            return Venta::withoutGlobalScopes()->where('tienda_id', $order->tienda_id)->findOrFail($order->venta_id);
        }

        $order->loadMissing(['tienda', 'detalles', 'direcciones']);
        $customer = $this->customerFor($order);
        $type = in_array($order->tipo_comprobante, ['ticket', 'boleta', 'factura'], true)
            ? $order->tipo_comprobante
            : ($order->requiere_factura ? 'factura' : 'boleta');
        $lines = $order->detalles->map(fn ($detail) => [
            'detail' => $detail,
            'quantity' => (int) $detail->cantidad,
            'unit_price' => (float) $detail->precio_unitario,
            'discount' => (float) $detail->descuento,
        ]);
        $calculation = $this->calculator->calculate(
            $lines,
            0,
            (float) ($order->tienda->igv ?? 0),
            true,
        );
        if (Money::toCents($calculation['total']) !== Money::toCents($order->total)) {
            throw ValidationException::withMessages([
                'pedido' => 'Los importes del pedido no concilian con el comprobante de venta.',
            ]);
        }

        $document = $this->numbers->next($order->tienda, $type);
        $sale = Venta::withoutGlobalScopes()->create([
            'tienda_id' => $order->tienda_id,
            'operacion_id' => $order->operacion_id,
            'operacion_hash' => hash('sha256', 'pedido-online:'.$order->id),
            'numero_venta' => $document['numero_venta'],
            'cliente_id' => $customer->id,
            'user_id' => $user->id,
            'caja_id' => null,
            'fecha' => today(),
            'tipo_comprobante' => $type,
            'serie' => $document['serie'],
            'correlativo' => $document['correlativo'],
            'subtotal' => $calculation['subtotal'],
            'descuento' => $calculation['discount'],
            'igv' => $calculation['tax'],
            'total' => $calculation['total'],
            'metodo_pago' => 'transferencia',
            'monto_pagado' => $calculation['total'],
            'vuelto' => 0,
            'estado' => 'completada',
            'observaciones' => 'Generada automáticamente desde el pedido online '.$order->numero,
        ]);

        foreach ($calculation['lines'] as $line) {
            $detail = $line['detail'];
            DetalleVenta::withoutGlobalScopes()->create([
                'tienda_id' => $order->tienda_id,
                'venta_id' => $sale->id,
                'producto_id' => $detail->producto_id,
                'variante_id' => $detail->variante_id,
                'producto_nombre' => $detail->producto_nombre,
                'talla' => $detail->talla,
                'color' => $detail->color,
                'cantidad' => $detail->cantidad,
                'precio_unitario' => $detail->precio_unitario,
                'descuento' => $line['discount'],
                'subtotal' => $line['subtotal'],
            ]);
        }

        PagoVenta::withoutGlobalScopes()->create([
            'tienda_id' => $order->tienda_id,
            'venta_id' => $sale->id,
            'metodo' => 'transferencia',
            'monto' => $calculation['total'],
            'referencia' => $order->referencia_pago,
        ]);

        $order->forceFill(['venta_id' => $sale->id])->save();
        $snapshot = $this->snapshots->capture($sale);
        $billing = $order->direcciones->firstWhere('tipo', 'fiscal');
        $snapshot['customer'] = [
            'name' => $order->razon_social ?: trim($order->cliente_nombre.' '.$order->cliente_apellido),
            'document' => $order->identificacion ?: $order->cliente_dni,
            'phone' => $order->cliente_telefono,
            'email' => $order->cliente_email,
            'address' => $billing?->direccion ?: $order->direcciones->firstWhere('tipo', 'entrega')?->direccion,
        ];
        $sale->forceFill([
            'commercial_snapshot' => $snapshot,
            'commercial_snapshot_hash' => $this->snapshots->hash($snapshot),
        ])->save();
        $this->audit->record($sale, 'venta.online_creada', 'Venta generada desde pedido online', $user, [
            'pedido' => $order->numero,
            'comprobante' => $sale->numero_venta,
            'metodo_pago' => 'transferencia',
        ]);

        return $sale;
    }

    private function customerFor(PedidoOnline $order): Cliente
    {
        $delivery = $order->direcciones->firstWhere('tipo', 'entrega');
        $customerByDocument = Cliente::withoutGlobalScopes()
            ->where('tienda_id', $order->tienda_id)
            ->where('dni', $order->cliente_dni)
            ->lockForUpdate()
            ->first();

        $customerByEmail = Cliente::withoutGlobalScopes()
            ->where('tienda_id', $order->tienda_id)
            ->where('email', $order->cliente_email)
            ->lockForUpdate()
            ->first();

        if (! $customerByDocument && $customerByEmail?->dni && $customerByEmail->dni !== $order->cliente_dni) {
            throw ValidationException::withMessages([
                'pedido' => 'El correo del pedido pertenece a otro cliente registrado.',
            ]);
        }

        // La identificacion es la fuente principal de identidad. Si existen dos
        // fichas historicas y el correo ya pertenece a otra, no intentamos
        // fusionarlas ni violamos el indice unico; el pedido conserva su correo
        // en el snapshot comercial de la venta.
        $customer = $customerByDocument ?: $customerByEmail;
        $emailBelongsToAnotherCustomer = $customerByDocument
            && $customerByEmail
            && ! $customerByDocument->is($customerByEmail);

        $data = [
            'nombre' => $order->cliente_nombre,
            'apellido' => $order->cliente_apellido,
            'dni' => $order->cliente_dni,
            'email' => $order->cliente_email,
            'telefono' => $order->cliente_telefono,
            'direccion' => $delivery?->direccion,
            'ciudad' => $delivery?->ciudad,
            'activo' => true,
        ];
        if ($emailBelongsToAnotherCustomer) {
            unset($data['email']);
        }
        if ($customer) {
            $customer->forceFill($data)->save();

            return $customer;
        }

        return $this->customers->create($data, (int) $order->tienda_id);
    }
}
