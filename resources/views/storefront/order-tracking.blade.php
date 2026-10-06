<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seguimiento {{ $order->numero }} · {{ $store->nombre }}</title><link rel="stylesheet" href="{{ asset('css/order-tracking.css') }}?v={{ filemtime(public_path('css/order-tracking.css')) }}"></head>
@php
    $steps = [
        \App\Support\OnlineOrderStatus::PAID => ['label' => 'Pago confirmado', 'description' => 'La tienda verificó correctamente tu pago.', 'symbol' => '✓', 'time' => $order->pagado_at],
        \App\Support\OnlineOrderStatus::PREPARING => ['label' => 'Preparando pedido', 'description' => 'Estamos preparando y revisando tus productos.', 'symbol' => '□', 'time' => $order->preparando_at],
        \App\Support\OnlineOrderStatus::SHIPPED => ['label' => 'Pedido enviado', 'description' => 'Tu pedido salió de la tienda y está en camino.', 'symbol' => '➜', 'time' => $order->enviado_at],
        \App\Support\OnlineOrderStatus::DELIVERED => ['label' => 'Pedido entregado', 'description' => 'Tu pedido fue marcado como entregado.', 'symbol' => '⌂', 'time' => $order->entregado_at],
    ];
    $stepKeys = array_keys($steps); $currentIndex = array_search($order->estado, $stepKeys, true); $isCancelled = $order->estado === \App\Support\OnlineOrderStatus::CANCELLED;
@endphp
<body style="--tracking-accent:{{ $store->color_primario ?: '#e8398c' }}">
<header class="tracking-header"><a href="{{ $store->publicUrl() }}"><span>@if($store->logo)<img src="{{ asset('storage/'.$store->logo) }}" alt="">@else{{ mb_substr($store->nombre, 0, 1) }}@endif</span><div><strong>{{ $store->nombre }}</strong><small>Seguimiento de compra</small></div></a><p>¿Necesitas ayuda? Contacta con la tienda</p></header>
<main class="tracking-shell">
    <section class="tracking-hero"><div><span class="tracking-eyebrow">Estado de tu pedido</span><h1>{{ $order->numero }}</h1><p>Hola {{ $order->cliente_nombre }}, aquí puedes consultar el avance de tu compra.</p></div><span class="tracking-status {{ $isCancelled ? 'is-cancelled' : '' }}"><i></i>{{ \App\Support\OnlineOrderStatus::label($order->estado) }}</span></section>
    @if($isCancelled)<section class="tracking-notice is-cancelled"><span>!</span><div><h2>Este pedido fue cancelado</h2><p>{{ $order->motivo_cancelacion ?: 'Comunícate con la tienda si necesitas más información.' }}</p></div></section>
    @elseif(in_array($order->estado, [\App\Support\OnlineOrderStatus::PENDING, \App\Support\OnlineOrderStatus::AWAITING_PAYMENT], true))<section class="tracking-notice"><span>⌛</span><div><h2>{{ $order->estado === \App\Support\OnlineOrderStatus::AWAITING_PAYMENT ? 'Estamos verificando tu transferencia' : 'Pago pendiente' }}</h2><p>{{ $order->estado === \App\Support\OnlineOrderStatus::AWAITING_PAYMENT ? 'Recibimos tu comprobante. El seguimiento comenzará cuando la tienda confirme el pago.' : 'Completa la transferencia para que podamos procesar tu pedido.' }}</p></div></section>
    @else<section class="tracking-progress-card"><div class="tracking-progress-heading"><div><h2>Progreso del pedido</h2><p>Actualizado el {{ $order->updated_at->format('d/m/Y \a \l\a\s H:i') }}</p></div><strong>{{ min(100, (($currentIndex + 1) / count($steps)) * 100) }}%</strong></div><div class="tracking-progress-bar"><span style="width:{{ min(100, (($currentIndex + 1) / count($steps)) * 100) }}%"></span></div><div class="tracking-timeline">@foreach($steps as $status => $step)@php($index = array_search($status, $stepKeys, true))<article class="{{ $index <= $currentIndex ? 'is-complete' : '' }} {{ $status === $order->estado ? 'is-current' : '' }}"><span>{{ $step['symbol'] }}</span><div><strong>{{ $step['label'] }}</strong><p>{{ $step['description'] }}</p><small>{{ $step['time']?->format('d/m/Y · H:i') ?: 'Pendiente' }}</small></div></article>@endforeach</div></section>@endif
    @if($order->guia_envio_path)
        @php($guideUrl = URL::temporarySignedRoute('storefront.orders.shipping-guide', now()->addDays(30), ['slug' => $store->slug, 'operation' => $order->operacion_id]))
        <section class="tracking-guide-card"><div class="tracking-guide-copy"><span>✓</span><div><small>Documento de envío</small><h2>Tu guía ya está disponible</h2><p>La tienda adjuntó esta fotografía al confirmar el envío de tu pedido.</p><time>Subida el {{ $order->guia_envio_subida_at?->format('d/m/Y · H:i') }}</time></div></div><a href="{{ $guideUrl }}" target="_blank" rel="noopener"><img src="{{ $guideUrl }}" alt="Guía de envío del pedido {{ $order->numero }}"><span>Ver imagen completa</span></a></section>
    @endif
    @if($order->comprobante_publicado_at && $order->venta_id)
        @php($saleReceiptUrl = URL::temporarySignedRoute('storefront.orders.sale-receipt', now()->addDays(30), ['slug' => $store->slug, 'operation' => $order->operacion_id]))
        <section class="tracking-guide-card tracking-receipt-card"><div class="tracking-guide-copy"><span>✓</span><div><small>Comprobante de venta</small><h2>Tu factura ya está disponible</h2><p>El pago fue verificado y la tienda publicó tu comprobante.</p><time>Publicado el {{ $order->comprobante_publicado_at->format('d/m/Y · H:i') }}</time></div></div><button type="button" data-open-sale-receipt data-receipt-url="{{ $saleReceiptUrl }}"><span>Ver factura</span></button></section>
    @endif
    <div class="tracking-grid"><section class="tracking-card tracking-products"><header><div><span>▣</span><div><h2>Resumen del pedido</h2><p>{{ $order->detalles->sum('cantidad') }} producto(s)</p></div></div></header>
        @foreach($order->detalles as $line)@php($image = $line->producto?->imagenes?->first(fn($item) => $item->color === $line->color) ?? $line->producto?->imagenes?->firstWhere('principal', true) ?? $line->producto?->imagenes?->first())<article><span class="tracking-product-image">@if($image?->ruta)<img src="{{ asset('storage/'.$image->ruta) }}" alt="">@elseif($line->producto?->imagen)<img src="{{ asset('storage/'.$line->producto->imagen) }}" alt="">@else◇@endif</span><div><strong>{{ $line->producto_nombre }}</strong><small>{{ $line->talla }} · {{ $line->color }} · Cant. {{ $line->cantidad }}</small></div><b>{{ $order->simbolo_moneda }} {{ number_format((float)$line->subtotal, 2) }}</b></article>@endforeach
        <footer><span>Total pagado</span><strong>{{ $order->simbolo_moneda }} {{ number_format((float)$order->total, 2) }}</strong></footer></section>
        <aside class="tracking-card tracking-help"><span>?</span><h2>¿Necesitas ayuda?</h2><p>Si tienes dudas sobre el estado o la entrega de tu pedido, comunícate directamente con la tienda.</p><a href="{{ $store->publicUrl() }}#contacto">Contactar con {{ $store->nombre }}</a><small>Guarda este enlace para revisar el avance durante los próximos 30 días.</small></aside></div>
</main>
@if($order->comprobante_publicado_at && $order->venta_id)
<dialog class="customer-receipt-modal" data-customer-receipt-modal>
    <div class="customer-receipt-dialog">
        <header><div><span>▣</span><div><strong>Factura del pedido</strong><small>{{ $order->numero }}</small></div></div><button type="button" data-close-sale-receipt aria-label="Cerrar">×</button></header>
        <div class="customer-receipt-frame"><div data-sale-receipt-loading>Cargando factura…</div><iframe title="Factura del pedido {{ $order->numero }}" data-sale-receipt-frame></iframe></div>
        <footer><button type="button" data-close-sale-receipt>Cerrar</button><button type="button" data-print-sale-receipt>Imprimir factura</button></footer>
    </div>
</dialog>
<script>
document.addEventListener('DOMContentLoaded',()=>{const modal=document.querySelector('[data-customer-receipt-modal]');const frame=modal?.querySelector('[data-sale-receipt-frame]');const loading=modal?.querySelector('[data-sale-receipt-loading]');document.querySelector('[data-open-sale-receipt]')?.addEventListener('click',event=>{frame.src=event.currentTarget.dataset.receiptUrl;loading.hidden=false;modal.showModal()});frame?.addEventListener('load',()=>loading.hidden=true);modal?.querySelectorAll('[data-close-sale-receipt]').forEach(button=>button.addEventListener('click',()=>modal.close()));modal?.addEventListener('click',event=>{if(event.target===modal)modal.close()});modal?.querySelector('[data-print-sale-receipt]')?.addEventListener('click',()=>frame.contentWindow?.print())});
</script>
@endif
</body></html>
