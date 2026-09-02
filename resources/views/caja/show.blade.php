@extends('layouts.app')
@section('title', 'Detalle de Caja #'.$caja->id)
@section('page-title', 'Detalle de Caja')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/caja.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/caja.js') }}" defer></script>
@endpush

@section('content')
<div class="cash-print-header"><h1>{{ auth()->user()->tienda?->nombre }}</h1><p>Reporte de caja #{{ $caja->id }}</p></div>
<div class="page-header cash-page-header">
    <div class="cash-page-heading"><span class="cash-page-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span><div><span class="cash-eyebrow">Reporte operativo</span><h1>Caja #{{ $caja->id }}</h1><div class="breadcrumb">Detalle completo del turno y su conciliación</div></div></div>
    <div class="cash-detail-actions">
        <a href="{{ route('caja.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary" data-print-cash><i class="fa-solid fa-print"></i> Imprimir</button>
    </div>
</div>

<section class="card" aria-labelledby="cash-detail-summary-title">
    <div class="card-header"><div class="card-title" id="cash-detail-summary-title"><i class="fa-solid fa-vault"></i> Resumen del turno</div></div>
    <div class="cash-detail-grid">
        <div><span>Responsable de apertura</span><strong>{{ $caja->usuario?->name ?? '—' }}</strong></div>
        <div><span>Responsable de cierre</span><strong>{{ $caja->usuarioCierre?->name ?? '—' }}</strong></div>
        <div><span>Apertura</span><strong>{{ $caja->apertura?->format('d/m/Y H:i') ?? '—' }}</strong></div>
        <div><span>Cierre</span><strong>{{ $caja->cierre?->format('d/m/Y H:i') ?? 'Turno abierto' }}</strong></div>
        <div><span>Duración</span><strong>{{ $caja->apertura?->diffForHumans($caja->cierre ?? now(), true) ?? '—' }}</strong></div>
        <div><span>Estado</span><strong>{{ ucfirst($caja->estado) }}</strong></div>
        <div><span>Monto inicial</span><strong>{{ $simboloMoneda }} {{ number_format($caja->monto_inicial, 2) }}</strong></div>
        <div><span>Otros ingresos</span><strong class="cash-total-positive">{{ $simboloMoneda }} {{ number_format($resumen['total_ingresos'], 2) }}</strong></div>
        <div><span>Egresos</span><strong class="cash-total-negative">{{ $simboloMoneda }} {{ number_format($resumen['total_egresos'], 2) }}</strong></div>
        <div><span>Efectivo esperado</span><strong>{{ $simboloMoneda }} {{ number_format($caja->efectivo_esperado ?? $resumen['efectivo_esperado'], 2) }}</strong></div>
        <div><span>Efectivo contado</span><strong>{{ $caja->efectivo_contado === null ? '—' : $simboloMoneda.' '.number_format($caja->efectivo_contado, 2) }}</strong></div>
        <div><span>Diferencia</span><strong class="cash-difference {{ $caja->estado_conciliacion }}">{{ $caja->diferencia === null ? '—' : ($caja->diferencia > 0 ? '+' : '').$simboloMoneda.' '.number_format($caja->diferencia, 2) }}</strong></div>
        <div><span>Conciliación</span>@if($caja->estado_conciliacion)<strong class="reconciliation-status {{ $caja->estado_conciliacion }}">{{ ucfirst($caja->estado_conciliacion) }}</strong>@else<strong> Pendiente</strong>@endif</div>
        <div><span>Ventas completadas</span><strong>{{ $resumen['ventas_cantidad'] }} · {{ $simboloMoneda }} {{ number_format($resumen['total_ventas'], 2) }}</strong></div>
    </div>
    @if($caja->observaciones)<p class="cash-note"><strong>Observación de apertura:</strong> {{ $caja->observaciones }}</p>@endif
    @if($caja->observaciones_cierre)<p class="cash-note"><strong>Observación de cierre:</strong> {{ $caja->observaciones_cierre }}</p>@endif
    <div class="cash-payment-breakdown" aria-label="Desglose por forma de pago">
        @foreach($resumen['pagos'] as $metodo => $total)<span class="cash-payment-chip">{{ ucfirst($metodo) }}: <strong>{{ $simboloMoneda }} {{ number_format($total, 2) }}</strong></span>@endforeach
        <span class="cash-payment-chip">Vuelto: <strong>{{ $simboloMoneda }} {{ number_format($resumen['vuelto'], 2) }}</strong></span>
    </div>
</section>

<section class="card cash-section" aria-labelledby="cash-sales-title">
    <div class="card-header"><div class="card-title" id="cash-sales-title"><i class="fa-solid fa-receipt"></i> Ventas del turno</div></div>
    <div class="table-wrap"><table><thead><tr><th>Comprobante</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Pago</th><th>Estado</th><th>Total</th><th></th></tr></thead><tbody>
    @forelse($ventas as $venta)
        <tr><td>{{ $venta->numero_venta }}</td><td>{{ $venta->created_at->format('d/m/Y H:i') }}</td><td>{{ $venta->cliente?->nombreCompleto() ?? 'Cliente directo' }}</td><td>{{ $venta->vendedor?->name ?? '—' }}</td><td>{{ $venta->pagos->pluck('metodo')->map(fn($metodo) => ucfirst($metodo))->join(', ') ?: ucfirst($venta->metodo_pago) }}</td><td>{{ ucfirst($venta->estado) }}</td><td>{{ $simboloMoneda }} {{ number_format($venta->total, 2) }}</td><td><a class="btn btn-secondary" href="{{ route('ventas.show', $venta) }}" aria-label="Ver venta {{ $venta->numero_venta }}"><i class="fa-solid fa-eye"></i></a></td></tr>
    @empty<tr><td colspan="8"><div class="cash-empty-state"><i class="fa-solid fa-receipt"></i><strong>Sin ventas</strong><span>Este turno no tiene ventas registradas.</span></div></td></tr>@endforelse
    </tbody></table></div>
    {{ $ventas->links() }}
</section>

<section class="card cash-section" aria-labelledby="cash-movements-title">
    <div class="card-header"><div class="card-title" id="cash-movements-title"><i class="fa-solid fa-money-bill-transfer"></i> Movimientos del turno</div></div>
    <div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Responsable</th><th>Observación</th><th>Monto</th></tr></thead><tbody>
    @forelse($movimientos as $movimiento)
        <tr><td>{{ $movimiento->created_at->format('d/m/Y H:i') }}</td><td><span class="cash-movement-type {{ $movimiento->tipo }}">{{ ucfirst($movimiento->tipo) }}</span></td><td>{{ $movimiento->concepto }}</td><td>{{ $movimiento->usuario?->name ?? '—' }}</td><td>{{ $movimiento->observaciones ?? '—' }}</td><td class="{{ $movimiento->tipo === 'ingreso' ? 'cash-total-positive' : 'cash-total-negative' }}">{{ $movimiento->tipo === 'ingreso' ? '+' : '−' }} {{ $simboloMoneda }} {{ number_format($movimiento->monto, 2) }}</td></tr>
    @empty<tr><td colspan="6"><div class="cash-empty-state"><i class="fa-solid fa-money-bill-transfer"></i><strong>Sin movimientos</strong><span>Este turno no tiene ingresos ni egresos adicionales.</span></div></td></tr>@endforelse
    </tbody></table></div>
    {{ $movimientos->links() }}
</section>
@endsection
