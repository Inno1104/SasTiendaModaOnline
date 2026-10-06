@extends('layouts.app')
@section('title', 'Pedido '.$order->numero)
@section('page-title', 'Detalle del pedido')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/online-orders.css') }}">
<link rel="stylesheet" href="{{ asset('css/online-order-detail.css') }}?v={{ filemtime(public_path('css/online-order-detail.css')) }}">
@endpush
@push('scripts')<script src="{{ asset('js/online-order-detail.js') }}?v={{ filemtime(public_path('js/online-order-detail.js')) }}" defer></script>@endpush

@section('content')
@php
    $delivery = $order->direcciones->firstWhere('tipo', 'entrega');
    $canCancel = $order->puedeTransicionarA(\App\Support\OnlineOrderStatus::CANCELLED);
    $isAwaitingPayment = $order->estado === \App\Support\OnlineOrderStatus::AWAITING_PAYMENT;
    $documentLabels = ['factura' => 'Factura', 'nota_venta' => 'Nota de venta', 'ticket' => 'Ticket'];
@endphp
<div class="order-detail-page">
    <nav class="order-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('pedidos-online.index') }}"><i class="fa-solid fa-arrow-left"></i> Pedidos</a><i class="fa-solid fa-chevron-right"></i><span>Detalle del pedido</span></nav>
    <header class="order-detail-toolbar">
        <div class="order-heading"><span class="order-heading-icon"><i class="fa-solid fa-bag-shopping"></i></span><div><h1>{{ $order->numero }}</h1><p>Registrado el {{ $order->created_at->format('d/m/Y \a \l\a\s H:i') }}</p></div></div>
        <span class="online-status status-{{ $order->estado }}"><i class="fa-solid fa-circle"></i> {{ \App\Support\OnlineOrderStatus::label($order->estado) }}</span>
        <div class="order-toolbar-actions"><button type="button" class="btn btn-secondary" data-print-order><i class="fa-solid fa-print"></i> Imprimir</button><a href="{{ route('pedidos-online.index') }}" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Volver</a></div>
    </header>
    @if(session('success'))<div class="online-order-alert success" role="status"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>@endif

    <div class="order-detail-grid">
        <main class="order-detail-main">
            <section class="card order-detail-card">
                <header><h2><i class="fa-solid fa-bag-shopping"></i> Productos <small>({{ $order->detalles->count() }})</small></h2></header>
                <div class="order-products-table">
                    <div class="order-products-head"><span>Producto</span><span>Precio unitario</span><span>Cantidad</span><span>Subtotal</span></div>
                    <div class="order-lines">
                        @forelse($order->detalles as $line)
                            @php
                                $productImages = $line->producto?->imagenes ?? collect();
                                $lineImage = $productImages->first(fn ($image) => $image->color === $line->color && $image->talla === $line->talla)
                                    ?? $productImages->first(fn ($image) => $image->color === $line->color && blank($image->talla))
                                    ?? $productImages->first(fn ($image) => $image->color === $line->color)
                                    ?? $productImages->firstWhere('principal', true) ?? $productImages->first();
                                $imagePath = $lineImage?->ruta ?: $line->producto?->imagen;
                            @endphp
                            <article>
                                <div class="order-product-cell"><span class="order-product-image">@if($imagePath)<img src="{{ asset('storage/'.$imagePath) }}" alt="{{ $line->producto_nombre }}" loading="lazy">@else<i class="fa-solid fa-shirt"></i>@endif</span><span class="order-product-copy"><strong>{{ $line->producto_nombre }}</strong><small>{{ $line->producto_codigo }}</small><span class="order-variant"><b>{{ $line->talla ?: 'Sin talla' }}</b><i></i>{{ $line->color ?: 'Sin color' }}</span></span></div>
                                <span class="order-money">{{ $order->simbolo_moneda }} {{ number_format((float) $line->precio_unitario, 2) }}</span><strong class="order-quantity">{{ $line->cantidad }}</strong><strong class="order-line-total">{{ $order->simbolo_moneda }} {{ number_format((float) $line->subtotal, 2) }}</strong>
                            </article>
                        @empty<div class="order-empty-lines"><i class="fa-solid fa-box-open"></i> No hay productos registrados.</div>@endforelse
                    </div>
                </div>
                <dl class="order-totals"><div><dt>Subtotal</dt><dd>{{ $order->simbolo_moneda }} {{ number_format((float) $order->subtotal, 2) }}</dd></div>@if((float) $order->descuento > 0)<div><dt>Descuento</dt><dd>- {{ $order->simbolo_moneda }} {{ number_format((float) $order->descuento, 2) }}</dd></div>@endif<div><dt>Envío</dt><dd>{{ $order->simbolo_moneda }} {{ number_format((float) $order->envio, 2) }}</dd></div><div class="order-grand-total"><dt>Total</dt><dd>{{ $order->simbolo_moneda }} {{ number_format((float) $order->total, 2) }}</dd></div></dl>
            </section>

            @if($canCancel)
                <section class="card order-cancel-card">
                    <header><span><i class="fa-solid fa-ban"></i></span><div><h2>{{ $isAwaitingPayment ? 'Desaprobar pedido' : 'Cancelar pedido' }}</h2><p>{{ $isAwaitingPayment ? 'El comprobante será rechazado y la reserva quedará disponible nuevamente.' : 'Las unidades descontadas serán repuestas al inventario.' }}</p></div></header>
                    <form method="POST" action="{{ route('pedidos-online.cancel', $order->id) }}" data-confirm-message="¿Confirmas la cancelación del pedido?">@csrf<label for="motivo_cancelacion">Motivo de cancelación</label><textarea id="motivo_cancelacion" name="motivo" rows="3" minlength="5" maxlength="500" placeholder="Escribe el motivo de la cancelación..." required>{{ old('motivo') }}</textarea><div class="order-cancel-footer"><span data-character-count>0/500</span><button class="btn btn-danger"><i class="fa-solid fa-circle-xmark"></i> Confirmar cancelación</button></div>@error('motivo')<small class="order-form-error">{{ $message }}</small>@enderror @error('pedido')<small class="order-form-error">{{ $message }}</small>@enderror</form>
                </section>
            @endif
        </main>

        <aside class="order-detail-aside">
            @php
                $workflow = [
                    \App\Support\OnlineOrderStatus::PAID => ['label' => 'Pago confirmado', 'icon' => 'fa-circle-check', 'time' => $order->pagado_at],
                    \App\Support\OnlineOrderStatus::PREPARING => ['label' => 'Preparando pedido', 'icon' => 'fa-box-open', 'time' => $order->preparando_at],
                    \App\Support\OnlineOrderStatus::SHIPPED => ['label' => 'Pedido enviado', 'icon' => 'fa-truck-fast', 'time' => $order->enviado_at],
                    \App\Support\OnlineOrderStatus::DELIVERED => ['label' => 'Pedido entregado', 'icon' => 'fa-house-circle-check', 'time' => $order->entregado_at],
                ];
                $workflowKeys = array_keys($workflow);
                $currentWorkflowIndex = array_search($order->estado, $workflowKeys, true);
                $nextStatus = match ($order->estado) {
                    \App\Support\OnlineOrderStatus::PAID => \App\Support\OnlineOrderStatus::PREPARING,
                    \App\Support\OnlineOrderStatus::PREPARING => \App\Support\OnlineOrderStatus::SHIPPED,
                    \App\Support\OnlineOrderStatus::SHIPPED => \App\Support\OnlineOrderStatus::DELIVERED,
                    default => null,
                };
            @endphp
            @if(in_array($order->estado, $workflowKeys, true))
                <section class="card order-info-card admin-tracking-card">
                    <h2><i class="fa-solid fa-route"></i> Seguimiento del pedido</h2>
                    <div class="admin-order-timeline">
                        @foreach($workflow as $status => $step)
                            @php($stepIndex = array_search($status, $workflowKeys, true))
                            <div class="admin-tracking-step {{ $stepIndex <= $currentWorkflowIndex ? 'is-complete' : '' }} {{ $status === $order->estado ? 'is-current' : '' }}"><span><i class="fa-solid {{ $step['icon'] }}"></i></span><div><strong>{{ $step['label'] }}</strong><small>{{ $step['time']?->format('d/m/Y · H:i') ?: 'Pendiente' }}</small></div></div>
                        @endforeach
                    </div>
                    @if($nextStatus)
                        <form method="POST" action="{{ route('pedidos-online.status', $order->id) }}" enctype="multipart/form-data" data-confirm-message="¿Confirmas que deseas cambiar el pedido a {{ \App\Support\OnlineOrderStatus::label($nextStatus) }}?">@csrf @method('PATCH')<input type="hidden" name="estado" value="{{ $nextStatus }}">@if($nextStatus === \App\Support\OnlineOrderStatus::SHIPPED)<label class="shipping-guide-upload"><span><i class="fa-solid fa-camera"></i></span><div><strong>Foto de la guía de envío</strong><small>JPG, PNG o WEBP · máximo 5 MB</small></div><input type="file" name="guia_envio" accept="image/jpeg,image/png,image/webp" required data-shipping-guide-input></label><div class="shipping-guide-preview" hidden data-shipping-guide-preview><img alt="Vista previa de la guía"><span data-shipping-guide-name></span></div>@error('guia_envio')<small class="order-form-error">{{ $message }}</small>@enderror @endif<button class="btn btn-primary order-next-status"><i class="fa-solid fa-arrow-right"></i> Marcar como {{ mb_strtolower(\App\Support\OnlineOrderStatus::label($nextStatus)) }}</button></form>
                    @else<div class="tracking-finished"><i class="fa-solid fa-circle-check"></i> Flujo completado correctamente</div>@endif
                </section>
            @endif
            <section class="card order-info-card order-summary-card">
                <h2><i class="fa-solid fa-building-columns"></i> Información del pedido</h2>
                <dl><div><dt>Referencia</dt><dd>{{ $order->referencia_pago ?: 'Compra online' }}</dd></div><div><dt>Fecha y hora</dt><dd>{{ $order->created_at->format('d/m/Y H:i') }}</dd></div><div><dt>Método de pago</dt><dd>{{ ucfirst(str_replace('_', ' ', $order->metodo_pago ?: 'Transferencia')) }}</dd></div>@if($order->venta)<div><dt>Comprobante de venta</dt><dd>{{ $order->venta->numero_venta }}</dd></div>@endif</dl>
                @if($order->pago_reportado_at)<div class="order-document-actions"><button type="button" class="btn btn-secondary" data-open-bank-receipt data-receipt-url="{{ route('pedidos-online.receipt', $order->id) }}"><i class="fa-solid fa-eye"></i> Ver comprobante bancario</button>@if($order->venta)<a class="btn btn-primary" href="{{ route('ventas.receipt', $order->venta) }}"><i class="fa-solid fa-file-invoice"></i> Comprobante de venta</a>@endif</div>@if($isAwaitingPayment)<form method="POST" action="{{ route('pedidos-online.verify', $order->id) }}">@csrf<button class="btn btn-primary order-verify-button"><i class="fa-solid fa-check"></i> Verificar pago y facturar</button></form>@endif @else<p>El cliente todavía no ha reportado la transferencia.</p>@endif
            </section>
            <section class="card order-info-card"><h2><i class="fa-solid fa-user"></i> Datos del cliente</h2><dl><div><dt>Nombre</dt><dd>{{ $order->cliente_nombre }} {{ $order->cliente_apellido }}</dd></div><div><dt>DNI</dt><dd>{{ $order->cliente_dni ?: 'No registrado' }}</dd></div><div><dt>Correo</dt><dd>{{ $order->cliente_email }}</dd></div><div><dt>Teléfono</dt><dd>{{ $order->cliente_telefono }}</dd></div></dl></section>
            @if($delivery)<section class="card order-info-card"><h2><i class="fa-solid fa-location-dot"></i> Información de entrega</h2><dl><div><dt>Destinatario</dt><dd>{{ $delivery->destinatario }}</dd></div><div><dt>Dirección</dt><dd>{{ $delivery->direccion }}</dd></div><div><dt>Ubicación</dt><dd>{{ collect([$delivery->ciudad, $delivery->provincia, $delivery->pais_codigo])->filter()->implode(', ') }}</dd></div>@if($delivery->referencia)<div><dt>Referencia</dt><dd>{{ $delivery->referencia }}</dd></div>@endif</dl></section>@endif
            <section class="card order-info-card"><h2><i class="fa-solid fa-file-invoice"></i> Facturación</h2><dl><div><dt>Comprobante solicitado</dt><dd>{{ $documentLabels[$order->tipo_comprobante] ?? ucfirst(str_replace('_', ' ', $order->tipo_comprobante ?: 'Nota de venta')) }}</dd></div>@if($order->requiere_factura)<div><dt>Identificación</dt><dd>{{ trim(($order->tipo_identificacion ? strtoupper($order->tipo_identificacion).' · ' : '').$order->identificacion) }}</dd></div><div><dt>Razón social</dt><dd>{{ $order->razon_social }}</dd></div>@endif</dl></section>
            @if($order->estado === \App\Support\OnlineOrderStatus::CANCELLED && $order->motivo_cancelacion)<section class="card order-info-card order-cancelled-note"><h2><i class="fa-solid fa-circle-xmark"></i> Pedido cancelado</h2><p>{{ $order->motivo_cancelacion }}</p></section>@endif
        </aside>
    </div>

    <dialog class="bank-receipt-modal" data-bank-receipt-modal aria-labelledby="bank-receipt-title">
        <div class="bank-receipt-modal-card">
            <header><div><span><i class="fa-solid fa-building-columns"></i></span><div><h2 id="bank-receipt-title">Comprobante bancario</h2><p>Pedido {{ $order->numero }}</p></div></div><button type="button" data-close-bank-receipt aria-label="Cerrar visor"><i class="fa-solid fa-xmark"></i></button></header>
            <div class="bank-receipt-viewer"><div class="bank-receipt-loading" data-receipt-loading><i class="fa-solid fa-spinner fa-spin"></i><span>Cargando comprobante…</span></div><div class="bank-receipt-sheet"><iframe title="Vista previa del comprobante bancario" data-receipt-frame></iframe></div></div>
            <footer><button type="button" class="btn btn-secondary" data-close-bank-receipt><i class="fa-solid fa-xmark"></i> Cerrar</button><div><button type="button" class="btn btn-secondary" data-print-bank-receipt><i class="fa-solid fa-print"></i> Imprimir</button><a class="btn btn-primary" href="{{ route('pedidos-online.receipt', ['pedido' => $order->id, 'download' => 1]) }}"><i class="fa-solid fa-download"></i> Descargar archivo</a></div></footer>
        </div>
    </dialog>
</div>
@endsection
