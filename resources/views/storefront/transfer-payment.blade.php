<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pago por transferencia · {{ $store->nombre }}</title><link rel="stylesheet" href="{{ asset('css/storefront.css') }}"><link rel="stylesheet" href="{{ asset('css/transfer-payment.css') }}"></head>
<body style="--store-color:{{ $store->color_primario }}"><main class="transfer-shell">
    <a class="transfer-brand" href="{{ $store->publicUrl() }}">← {{ $store->nombre }}</a>
    <section class="transfer-card"><header><span>Pago pendiente</span><h1>Transferencia bancaria</h1><p>Pedido <strong>{{ $order->numero }}</strong></p></header>
        @if(session('transfer_success'))<div class="transfer-success">{{ session('transfer_success') }}</div>@endif
        @if(!$store->transferencia_activa)<div class="transfer-warning">La tienda todavía no configuró sus datos bancarios. Comunícate con el establecimiento antes de pagar.</div>@else
        <div class="transfer-amount"><small>Total a transferir</small><strong>{{ $order->simbolo_moneda }} {{ number_format((float)$order->total,2) }}</strong><span>Reserva disponible hasta {{ $order->reserva_expira_at?->format('d/m/Y H:i') }}</span></div>
        <dl class="bank-details"><div><dt>Banco</dt><dd>{{ $store->transferencia_banco }}</dd></div><div><dt>Tipo de cuenta</dt><dd>{{ ucfirst($store->transferencia_tipo_cuenta) }}</dd></div><div><dt>Número de cuenta</dt><dd>{{ $store->transferencia_numero_cuenta }}</dd></div><div><dt>Titular</dt><dd>{{ $store->transferencia_titular }}</dd></div>@if($store->transferencia_identificacion)<div><dt>Identificación</dt><dd>{{ $store->transferencia_identificacion }}</dd></div>@endif</dl>
        @if($store->transferencia_instrucciones)<p class="transfer-instructions">{{ $store->transferencia_instrucciones }}</p>@endif
        <form method="POST" enctype="multipart/form-data" action="{{ route('storefront.transfer.store',['slug'=>$store->slug,'operation'=>$order->operacion_id]) }}">@csrf
            <label>Referencia de la transferencia<input name="referencia_pago" value="{{ old('referencia_pago',$order->referencia_pago) }}" maxlength="100" required>@error('referencia_pago')<small>{{ $message }}</small>@enderror</label>
            <label>Comprobante (JPG, PNG o PDF, máximo 5 MB)<input type="file" name="comprobante" accept="image/jpeg,image/png,application/pdf" required>@error('comprobante')<small>{{ $message }}</small>@enderror</label>
            <button>Enviar comprobante</button>
        </form>@endif
        <a class="transfer-tracking-link" href="{{ URL::temporarySignedRoute('storefront.orders.tracking', now()->addDays(30), ['slug' => $store->slug, 'operation' => $order->operacion_id]) }}">Consultar seguimiento del pedido <span>→</span></a>
        <footer>El pedido se marcará como pagado únicamente después de que la tienda verifique el depósito.</footer>
    </section>
</main></body></html>
