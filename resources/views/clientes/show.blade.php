@extends('layouts.app')
@section('title','Detalle Cliente')
@section('page-title','Detalle del Cliente')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/clientes.css') }}">
@endpush

@section('content')
<div class="page-header customer-page-header">
    <div class="customer-page-heading"><span class="customer-page-icon"><i class="fa-solid fa-address-card"></i></span><div><span class="customer-eyebrow">{{ $cliente->codigo }}</span><h1>{{ $cliente->nombreCompleto() }}</h1><p>Información, actividad comercial e historial de compras.</p></div></div>
    <div class="customer-header-actions"><a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-outline"><i class="fa-solid fa-pen"></i> Editar</a><a href="{{ route('clientes.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a></div>
</div>

<div class="customer-detail-grid">
    <aside class="card customer-profile-card">
        <div class="customer-profile-heading"><div class="customer-avatar customer-avatar-large">{{ strtoupper(substr($cliente->nombre, 0, 1).substr($cliente->apellido, 0, 1)) }}</div><h2>{{ $cliente->nombreCompleto() }}</h2><code class="customer-code">{{ $cliente->codigo }}</code><span class="badge {{ $cliente->activo ? 'badge-success' : 'badge-danger' }}">{{ $cliente->activo ? 'Activo' : 'Inactivo' }}</span></div>
        <dl class="customer-profile-data">
            <div><dt><i class="fa-solid fa-id-card"></i> Documento</dt><dd>{{ $cliente->dni ?? 'No registrado' }}</dd></div>
            <div><dt><i class="fa-solid fa-envelope"></i> Correo</dt><dd>{{ $cliente->email ?? 'No registrado' }}</dd></div>
            <div><dt><i class="fa-solid fa-phone"></i> Teléfono</dt><dd>{{ $cliente->telefono ?? 'No registrado' }}</dd></div>
            <div><dt><i class="fa-solid fa-location-dot"></i> Ciudad</dt><dd>{{ $cliente->ciudad ?? 'No registrada' }}</dd></div>
            <div><dt><i class="fa-solid fa-map-location-dot"></i> Dirección</dt><dd>{{ $cliente->direccion ?? 'No registrada' }}</dd></div>
            <div><dt><i class="fa-solid fa-cake-candles"></i> Nacimiento</dt><dd>{{ $cliente->fecha_nacimiento?->format('d/m/Y') ?? 'No registrado' }}</dd></div>
            <div><dt><i class="fa-solid fa-star"></i> Puntos</dt><dd class="customer-profile-points">{{ number_format($cliente->puntos, 0) }} pts</dd></div>
        </dl>
        @if($cliente->estado_cambiado_at)
        <div class="customer-status-trace"><strong>Último cambio de estado</strong><span>{{ $cliente->estado_cambiado_at->format('d/m/Y H:i') }} · {{ $cliente->estadoCambiadoPor?->name ?? 'Usuario no disponible' }}</span><p>{{ $cliente->motivo_estado }}</p></div>
        @endif
    </aside>

    <div class="customer-commercial-column">
        <section class="customer-metrics" aria-label="Resumen comercial del cliente">
            <article><span class="customer-metric-icon"><i class="fa-solid fa-bag-shopping"></i></span><div><small>Compras completadas</small><strong>{{ number_format($resumenComercial['compras']) }}</strong></div></article>
            <article><span class="customer-metric-icon"><i class="fa-solid fa-sack-dollar"></i></span><div><small>Total gastado</small><strong>{{ $simboloMoneda }} {{ number_format($resumenComercial['total_gastado'], 2) }}</strong></div></article>
            <article><span class="customer-metric-icon"><i class="fa-solid fa-chart-line"></i></span><div><small>Ticket promedio</small><strong>{{ $simboloMoneda }} {{ number_format($resumenComercial['ticket_promedio'], 2) }}</strong></div></article>
            <article><span class="customer-metric-icon"><i class="fa-solid fa-calendar-check"></i></span><div><small>Última compra</small><strong>{{ $resumenComercial['ultima_compra']?->format('d/m/Y') ?? 'Sin compras' }}</strong></div></article>
        </section>

        <section class="card customer-purchase-history">
            <div class="card-header"><div><div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Historial de compras</div><p>Ventas vinculadas a este cliente.</p></div></div>
            <div class="table-wrap"><table><thead><tr><th>N° Venta</th><th>Fecha</th><th>Total</th><th>Estado</th></tr></thead><tbody>
                @forelse($ventas as $v)
                <tr><td><a href="{{ route('ventas.show', $v) }}" class="customer-sale-link">{{ $v->numero_venta }}</a></td><td>{{ $v->fecha->format('d/m/Y') }}</td><td><strong>{{ $simboloMoneda }} {{ number_format($v->total, 2) }}</strong></td><td><span class="badge {{ $v->estado === 'completada' ? 'badge-success' : ($v->estado === 'anulada' ? 'badge-danger' : 'badge-warning') }}">{{ ucfirst($v->estado) }}</span></td></tr>
                @empty
                <tr><td colspan="4" class="customer-empty-result"><i class="fa-solid fa-receipt"></i><strong>Sin compras registradas</strong><span>Las futuras ventas del cliente aparecerán aquí.</span></td></tr>
                @endforelse
            </tbody></table></div>
            {{ $ventas->links() }}
        </section>
    </div>
</div>
@endsection
