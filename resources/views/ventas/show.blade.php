@extends('layouts.app')
@section('title', 'Comprobante '.$venta->numero_venta)
@section('page-title', 'Detalle de Venta')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/sale-receipt.css') }}">
<link rel="stylesheet" href="{{ asset('css/sale-receipt-theme.css') }}">
<link rel="stylesheet" href="{{ asset('css/sale-receipt-detail.css') }}">
@endpush

@section('content')
<div class="page-header receipt-page-header">
    <div><h1>{{ $venta->numero_venta }}</h1><div class="breadcrumb"><a href="{{ route('ventas.index') }}">Historial</a><span class="breadcrumb-sep">›</span>Detalle de venta</div></div>
    <div class="receipt-actions">
        <a href="{{ route('ventas.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        <button type="button" id="printCurrentReceipt" class="btn btn-outline"><i class="fa-solid fa-print"></i> Imprimir</button>
        <button type="button" id="toggleReceiptFormat" class="btn btn-primary" aria-pressed="false"><i class="fa-solid fa-receipt"></i><span>Ticket 80 mm</span></button>
        <a href="{{ route('ventas.receipt', [$venta, 'download' => 1]) }}" class="btn btn-outline"><i class="fa-solid fa-download"></i> Descargar</a>
        <a href="{{ route('ventas.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva venta</a>
    </div>
</div>

<div class="receipt-detail-layout">
    @include('ventas.partials.receipt', ['standalone' => false])

    <aside class="receipt-audit card">
        <div class="receipt-panel-title"><i class="fa-solid fa-clock-rotate-left"></i><div><strong>Actividad de la venta</strong><small>Trazabilidad de la operación</small></div></div>
        @foreach($venta->pagos as $pago)
            <div class="audit-event"><span></span><div><strong>Pago {{ ucfirst($pago->metodo) }} · {{ $simboloMoneda }} {{ number_format($pago->monto, 2) }}</strong><small>{{ $pago->referencia ? 'Ref. '.$pago->referencia.' · ' : '' }}{{ $pago->created_at->format('d/m/Y H:i:s') }}</small></div></div>
        @endforeach
        @foreach($venta->reversiones as $reversion)
            <div class="audit-event"><span></span><div><strong>{{ ucfirst($reversion->tipo) }} · - {{ $simboloMoneda }} {{ number_format($reversion->monto, 2) }}</strong><small>{{ $reversion->responsable?->name ?? 'Sistema' }} · {{ $reversion->created_at->format('d/m/Y H:i:s') }} · {{ $reversion->motivo }}</small></div></div>
        @endforeach
        @forelse($venta->auditorias->sortByDesc('created_at') as $evento)
            <div class="audit-event"><span></span><div><strong>{{ $evento->descripcion }}</strong><small>{{ $evento->user?->name ?? 'Sistema' }} · {{ $evento->created_at->format('d/m/Y H:i:s') }}</small></div></div>
        @empty
            <div class="audit-empty">No existen eventos registrados.</div>
        @endforelse
    </aside>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/sale-receipt.js') }}"></script>
@endpush
