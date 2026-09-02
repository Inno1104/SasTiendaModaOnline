@php
    $snapshot = $venta->commercial_snapshot ?? [];
    $storeData = $snapshot['store'] ?? [];
    $customerData = $snapshot['customer'] ?? [];
    $sellerData = $snapshot['seller'] ?? [];
    $currencyData = $snapshot['currency'] ?? [];
    $totals = $snapshot['totals'] ?? [];
    $document = $snapshot['document'] ?? [];
    $receiptSymbol = $currencyData['symbol'] ?? ($venta->tienda?->simbolo_moneda ?: '$');
    $receiptCurrency = $currencyData['code'] ?? ($venta->tienda?->moneda ?: 'USD');
    $issuedAt = ! empty($snapshot['issued_at']) ? \Carbon\CarbonImmutable::parse($snapshot['issued_at']) : $venta->created_at;
    $items = collect($snapshot['items'] ?? $venta->detalles->map(fn ($detail) => [
        'name' => $detail->producto_nombre, 'code' => $detail->producto?->codigo,
        'size' => $detail->talla, 'color' => $detail->color, 'quantity' => $detail->cantidad,
        'unit_price' => $detail->precio_unitario, 'discount' => $detail->descuento, 'subtotal' => $detail->subtotal,
    ])->all());
    $paymentMethods = $venta->pagos->pluck('metodo')->unique();
    $paymentLabel = $paymentMethods->count() > 1 ? 'Pago combinado' : ucfirst($paymentMethods->first() ?? $venta->metodo_pago);
@endphp
<article class="sale-receipt card {{ ($standalone ?? false) ? 'is-standalone' : '' }} {{ in_array($venta->estado, ['anulada', 'devuelta'], true) ? 'is-cancelled' : '' }}">
    @if($venta->estado === 'anulada')<div class="cancelled-watermark">ANULADA</div>@elseif($venta->estado === 'devuelta')<div class="cancelled-watermark">DEVUELTA</div>@endif
    <header class="receipt-brand">
        <div class="receipt-store">
            @php($receiptLogo = $storeData['logo'] ?? $venta->tienda?->logo)
            <div class="receipt-logo">@if($receiptLogo)<img src="{{ asset('storage/'.$receiptLogo) }}" alt="Logo de {{ $storeData['name'] ?? $venta->tienda?->nombre }}">@else<i class="fa-solid fa-shirt"></i>@endif</div>
            <div>
                <h2>{{ mb_strtoupper($storeData['name'] ?? $venta->tienda?->nombre ?? 'Tienda') }}</h2>
                @if($storeData['tax_id'] ?? $venta->tienda?->ruc)<p><b>RUC:</b> {{ $storeData['tax_id'] ?? $venta->tienda?->ruc }}</p>@endif
                <p>{{ $storeData['address'] ?? $venta->tienda?->direccion ?? 'Dirección no registrada' }}</p>
                @if($storeData['phone'] ?? $venta->tienda?->telefono)<p><b>Tel.:</b> {{ $storeData['phone'] ?? $venta->tienda?->telefono }}</p>@endif
                @if($storeData['email'] ?? $venta->tienda?->email)<p>{{ $storeData['email'] ?? $venta->tienda?->email }}</p>@endif
            </div>
        </div>
        <div class="receipt-document"><small><i class="fa-regular fa-file-lines"></i> {{ mb_strtoupper($document['type'] ?? $venta->tipo_comprobante) }} DE VENTA</small><strong>{{ $document['number'] ?? $venta->numero_venta }}</strong><span>{{ $issuedAt->format('d/m/Y') }} a las {{ $issuedAt->format('H:i:s') }}</span></div>
    </header>
    <section class="receipt-information">
        <div class="billing-data"><h3>DATOS DE FACTURACIÓN</h3>
            <p><b>Cliente:</b> {{ $customerData['name'] ?? ($venta->cliente?->nombreCompleto() ?? 'Cliente directo') }}</p>
            @if($customerData['document'] ?? $venta->cliente?->dni)<p><b>Documento:</b> {{ $customerData['document'] ?? $venta->cliente?->dni }}</p>@endif
            @if($customerData['phone'] ?? $venta->cliente?->telefono)<p><b>Teléfono:</b> {{ $customerData['phone'] ?? $venta->cliente?->telefono }}</p>@endif
            @if($customerData['email'] ?? $venta->cliente?->email)<p><b>Correo:</b> {{ $customerData['email'] ?? $venta->cliente?->email }}</p>@endif
            @if($customerData['address'] ?? $venta->cliente?->direccion)<p><b>Dirección:</b> {{ $customerData['address'] ?? $venta->cliente?->direccion }}</p>@endif
        </div>
        <div class="sale-data"><h3>DATOS DE LA VENTA</h3><p><b>Canal:</b> Punto de venta</p><p><b>Atendido por:</b> {{ $sellerData['name'] ?? $venta->vendedor?->name ?? 'No disponible' }}</p><p><b>Pago:</b> {{ $paymentLabel }}</p><p><b>Estado:</b> {{ ucfirst($venta->estado) }}</p><p><b>Moneda:</b> {{ $receiptCurrency }}</p></div>
    </section>
    @if($venta->estado === 'anulada')<div class="receipt-cancelled"><i class="fa-solid fa-circle-xmark"></i><div><strong>Comprobante anulado</strong><p>{{ $venta->motivo_anulacion }}</p><small>{{ $venta->anuladaPor?->name ?? 'Usuario no disponible' }}@if($venta->anulada_at) · {{ $venta->anulada_at->format('d/m/Y H:i:s') }}@endif</small></div></div>@endif
    @if($venta->estado === 'devuelta')<div class="receipt-cancelled"><i class="fa-solid fa-rotate-left"></i><div><strong>Venta devuelta</strong><p>{{ $venta->motivo_devolucion }}</p><small>{{ $venta->devueltaPor?->name ?? 'Usuario no disponible' }}@if($venta->devuelta_at) · {{ $venta->devuelta_at->format('d/m/Y H:i:s') }}@endif</small></div></div>@endif
    <div class="receipt-products-wrap"><table class="receipt-products"><thead><tr><th>Producto</th><th>Cant.</th><th>P. unit.</th><th>Desc.</th><th>Importe</th></tr></thead><tbody>@foreach($items as $item)<tr><td><strong>{{ $item['name'] }}</strong><small>{{ $item['code'] ?: 'Producto' }}@if($item['size'] || $item['color']) · {{ $item['size'] ?: 'Única' }} / {{ $item['color'] ?: 'Sin color' }}@endif</small></td><td>{{ $item['quantity'] }}</td><td>{{ $receiptSymbol }} {{ number_format((float) $item['unit_price'], 2) }}</td><td>{{ $receiptSymbol }} {{ number_format((float) $item['discount'], 2) }}</td><td><strong>{{ $receiptSymbol }} {{ number_format((float) $item['subtotal'], 2) }}</strong></td></tr>@endforeach</tbody></table></div>
    <div class="receipt-bottom">
        <section class="receipt-payments"><h3>PAGOS Y REVERSIONES</h3>@forelse($venta->pagos as $payment)<div><span>{{ ucfirst($payment->metodo) }}@if($payment->referencia)<small>Ref. {{ $payment->referencia }}</small>@endif</span><strong>{{ $receiptSymbol }} {{ number_format($payment->monto, 2) }}</strong></div>@empty<div><span>{{ ucfirst($venta->metodo_pago) }}</span><strong>{{ $receiptSymbol }} {{ number_format($totals['paid'] ?? $venta->monto_pagado, 2) }}</strong></div>@endforelse
        @foreach($venta->reversiones as $reversal)<div class="discount"><span>{{ ucfirst($reversal->tipo) }} · {{ ucfirst($reversal->metodo) }}<small>{{ $reversal->created_at->format('d/m/Y H:i:s') }}</small></span><strong>- {{ $receiptSymbol }} {{ number_format($reversal->monto, 2) }}</strong></div>@endforeach</section>
        <section class="receipt-totals"><div><span>Subtotal</span><span>{{ $receiptSymbol }} {{ number_format($totals['subtotal'] ?? $venta->subtotal, 2) }}</span></div>@if(($totals['discount'] ?? $venta->descuento) > 0)<div class="discount"><span>Descuento</span><span>- {{ $receiptSymbol }} {{ number_format($totals['discount'] ?? $venta->descuento, 2) }}</span></div>@endif<div><span>Impuestos</span><span>{{ $receiptSymbol }} {{ number_format($totals['tax'] ?? $venta->igv, 2) }}</span></div><div class="total"><span>TOTAL</span><strong>{{ $receiptSymbol }} {{ number_format($totals['total'] ?? $venta->total, 2) }}</strong></div><div><span>Pagado</span><span>{{ $receiptSymbol }} {{ number_format($totals['paid'] ?? $venta->monto_pagado, 2) }}</span></div>@if(($totals['change'] ?? $venta->vuelto) > 0)<div class="change"><span>Vuelto</span><span>{{ $receiptSymbol }} {{ number_format($totals['change'] ?? $venta->vuelto, 2) }}</span></div>@endif</section>
    </div>
    @if($snapshot['notes'] ?? $venta->observaciones)<div class="receipt-notes"><strong>OBSERVACIONES</strong><p>{{ $snapshot['notes'] ?? $venta->observaciones }}</p></div>@endif
    <footer class="receipt-footer"><span><i class="fa-solid fa-shield-halved"></i> Comprobante generado por el sistema · Snapshot v{{ $snapshot['version'] ?? 'legacy' }}</span><span>Serie {{ $document['series'] ?? $venta->serie }} · ID {{ $venta->id }}@if($venta->commercial_snapshot_hash) · Ref. {{ substr($venta->commercial_snapshot_hash, 0, 12) }}@endif</span></footer>
</article>
