@extends('layouts.app')
@section('title', 'Pedido '.$order->numero)
@section('page-title', 'Detalle del pedido')
@push('styles')<link rel="stylesheet" href="{{ asset('css/online-orders.css') }}"><link rel="stylesheet" href="{{ asset('css/online-order-detail.css') }}">@endpush

@section('content')
<div class="online-page-head"><div><span><i class="fa-solid fa-box"></i></span><div><h1>{{ $order->numero }}</h1><p>Registrado el {{ $order->created_at->format('d/m/Y \a \l\a\s H:i') }}</p></div></div><a href="{{ route('pedidos-online.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a></div>
@if(session('success'))<div class="online-order-alert success" role="status">{{ session('success') }}</div>@endif

<div class="order-detail-grid">
    <main>
        <section class="card order-detail-card"><header><h2><i class="fa-solid fa-shirt"></i> Productos</h2><span class="online-status status-{{ $order->estado }}">{{ \App\Support\OnlineOrderStatus::label($order->estado) }}</span></header>
            <div class="order-lines">@foreach($order->detalles as $line)<article><div><small>{{ $line->producto_codigo }}</small><strong>{{ $line->producto_nombre }}</strong><span>{{ $line->talla }} / {{ $line->color }}</span></div><span>{{ $line->cantidad }} × {{ $order->simbolo_moneda }} {{ number_format((float) $line->precio_unitario, 2) }}</span><strong>{{ $order->simbolo_moneda }} {{ number_format((float) $line->subtotal, 2) }}</strong></article>@endforeach</div>
            <dl class="order-totals"><div><dt>Subtotal</dt><dd>{{ $order->simbolo_moneda }} {{ number_format((float) $order->subtotal, 2) }}</dd></div><div><dt>Envío</dt><dd>{{ $order->simbolo_moneda }} {{ number_format((float) $order->envio, 2) }}</dd></div><div><dt>Total</dt><dd>{{ $order->simbolo_moneda }} {{ number_format((float) $order->total, 2) }}</dd></div></dl>
        </section>
    </main>
    <aside>
        <section class="card order-info-card"><h2><i class="fa-solid fa-building-columns"></i> Transferencia</h2>@if($order->pago_reportado_at)<dl><div><dt>Referencia</dt><dd>{{ $order->referencia_pago }}</dd></div><div><dt>Reportada</dt><dd>{{ $order->pago_reportado_at->format('d/m/Y H:i') }}</dd></div>@if($order->venta)<div><dt>Comprobante de venta</dt><dd>{{ $order->venta->numero_venta }}</dd></div>@endif</dl><a class="btn btn-secondary" href="{{ route('pedidos-online.receipt',$order->id) }}"><i class="fa-solid fa-paperclip"></i> Ver comprobante bancario</a>@if($order->venta)<a class="btn btn-primary" href="{{ route('ventas.receipt',$order->venta) }}"><i class="fa-solid fa-file-invoice"></i> Ver comprobante de venta</a>@endif @if($order->estado === \App\Support\OnlineOrderStatus::AWAITING_PAYMENT)<form method="POST" action="{{ route('pedidos-online.verify',$order->id) }}" style="margin-top:10px">@csrf<button class="btn btn-primary"><i class="fa-solid fa-check"></i> Verificar pago y facturar</button></form>@endif @else<p>El cliente todavía no ha reportado la transferencia.</p>@endif</section>
        <section class="card order-info-card"><h2><i class="fa-solid fa-user"></i> Cliente</h2><dl><div><dt>Nombre</dt><dd>{{ $order->cliente_nombre }} {{ $order->cliente_apellido }}</dd></div><div><dt>DNI</dt><dd>{{ $order->cliente_dni ?: 'No registrado' }}</dd></div><div><dt>Correo</dt><dd>{{ $order->cliente_email }}</dd></div><div><dt>Teléfono</dt><dd>{{ $order->cliente_telefono }}</dd></div></dl></section>
        @php($delivery = $order->direcciones->firstWhere('tipo', 'entrega'))
        @if($delivery)<section class="card order-info-card"><h2><i class="fa-solid fa-location-dot"></i> Entrega</h2><dl><div><dt>Destinatario</dt><dd>{{ $delivery->destinatario }}</dd></div><div><dt>Dirección</dt><dd>{{ $delivery->direccion }}</dd></div><div><dt>Ubicación</dt><dd>{{ $delivery->ciudad }}, {{ $delivery->provincia }} · {{ $delivery->pais_codigo }}</dd></div>@if($delivery->referencia)<div><dt>Referencia</dt><dd>{{ $delivery->referencia }}</dd></div>@endif</dl></section>@endif
        <section class="card order-info-card"><h2><i class="fa-solid fa-file-invoice"></i> Facturación</h2><p>{{ $order->requiere_factura ? 'Solicita factura con datos fiscales.' : 'No solicita factura con datos fiscales.' }}</p>@if($order->requiere_factura)<dl><div><dt>Identificación</dt><dd>{{ $order->identificacion }}</dd></div><div><dt>Razón social</dt><dd>{{ $order->razon_social }}</dd></div></dl>@endif</section>
        @if($order->puedeTransicionarA(\App\Support\OnlineOrderStatus::CANCELLED))
            <section class="card order-info-card">
                <h2><i class="fa-solid fa-ban"></i> {{ $order->estado === \App\Support\OnlineOrderStatus::AWAITING_PAYMENT ? 'Desaprobar pedido' : 'Cancelar pedido' }}</h2>
                <p>{{ $order->estado === \App\Support\OnlineOrderStatus::AWAITING_PAYMENT ? 'El comprobante será rechazado y la reserva quedará disponible nuevamente.' : 'Las unidades descontadas serán repuestas al inventario.' }}</p>
                <form method="POST" action="{{ route('pedidos-online.cancel', $order->id) }}">
                    @csrf
                    <label for="motivo_cancelacion">Motivo</label>
                    <textarea id="motivo_cancelacion" name="motivo" rows="3" minlength="5" maxlength="500" required>{{ old('motivo') }}</textarea>
                    @error('motivo')<small>{{ $message }}</small>@enderror
                    @error('pedido')<small>{{ $message }}</small>@enderror
                    <button class="btn btn-danger" onclick="return confirm('¿Confirmas la cancelación del pedido?')"><i class="fa-solid fa-ban"></i> Confirmar cancelación</button>
                </form>
            </section>
        @endif
    </aside>
</div>
@endsection
