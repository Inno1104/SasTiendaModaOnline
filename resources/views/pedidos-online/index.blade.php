@extends('layouts.app')
@section('title', 'Pedidos online')
@section('page-title', 'Pedidos online')
@push('styles')<link rel="stylesheet" href="{{ asset('css/online-orders.css') }}">@endpush

@section('content')
<div class="online-page-head">
    <div><span><i class="fa-solid fa-bag-shopping"></i></span><div><h1>Pedidos online</h1><p>Supervisa las compras realizadas desde tu tienda pública.</p></div></div>
    <a class="btn btn-secondary" href="{{ auth()->user()->tienda?->publicUrl() }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver tienda pública</a>
</div>

<section class="online-kpis" aria-label="Resumen de pedidos">
    <article><i class="fa-solid fa-boxes-stacked"></i><span><small>Total</small><strong>{{ number_format((int) $summary->total) }}</strong></span></article>
    <article><i class="fa-solid fa-clock"></i><span><small>Esperando pago</small><strong>{{ number_format((int) $summary->esperando) }}</strong></span></article>
    <article><i class="fa-solid fa-box-open"></i><span><small>En proceso</small><strong>{{ number_format((int) $summary->proceso) }}</strong></span></article>
    <article><i class="fa-solid fa-truck-fast"></i><span><small>Enviados</small><strong>{{ number_format((int) $summary->enviados) }}</strong></span></article>
    <article><i class="fa-solid fa-circle-check"></i><span><small>Entregados</small><strong>{{ number_format((int) $summary->entregados) }}</strong></span></article>
</section>

<section class="card online-filter-card">
    <form method="GET" action="{{ route('pedidos-online.index') }}">
        <label class="online-search"><span>Buscar pedido</span><div><i class="fa-solid fa-magnifying-glass"></i><input name="q" type="search" value="{{ request('q') }}" maxlength="100" placeholder="Número, cliente, DNI, correo o teléfono"></div></label>
        <label><span>Estado</span><select name="estado"><option value="">Todos los estados</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('estado') === $status)>{{ \App\Support\OnlineOrderStatus::label($status) }}</option>@endforeach</select></label>
        <label><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
        <label><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
        <div><button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filtrar</button><a class="btn btn-secondary" href="{{ route('pedidos-online.index') }}">Limpiar</a></div>
    </form>
</section>

<section class="card online-table-card">
    <header><div><i class="fa-solid fa-list-check"></i><span><strong>Pedidos registrados</strong><small>{{ $orders->total() }} resultados</small></span></div></header>
    <div class="online-table-wrap"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Contacto</th><th>Productos</th><th>Total</th><th>Estado</th><th></th></tr></thead><tbody>
    @forelse($orders as $order)
        <tr>
            <td><a class="online-number" href="{{ route('pedidos-online.show', $order->id) }}">{{ $order->numero }}</a></td>
            <td><strong>{{ $order->created_at->format('d/m/Y') }}</strong><small>{{ $order->created_at->format('H:i') }}</small></td>
            <td><strong>{{ $order->cliente_nombre }} {{ $order->cliente_apellido }}</strong><small>DNI: {{ $order->cliente_dni ?: 'No registrado' }}</small></td>
            <td><span>{{ $order->cliente_email }}</span><small>{{ $order->cliente_telefono }}</small></td>
            <td><span>{{ $order->detalles_count }} {{ $order->detalles_count === 1 ? 'variante' : 'variantes' }}</span></td>
            <td><strong>{{ $order->simbolo_moneda }} {{ number_format((float) $order->total, 2) }}</strong></td>
            <td><span class="online-status status-{{ $order->estado }}">{{ \App\Support\OnlineOrderStatus::label($order->estado) }}</span></td>
            <td><a class="online-view" href="{{ route('pedidos-online.show', $order->id) }}" title="Ver pedido"><i class="fa-solid fa-eye"></i></a></td>
        </tr>
    @empty
        <tr><td colspan="8"><div class="online-empty"><i class="fa-solid fa-bag-shopping"></i><strong>No hay pedidos online</strong><p>Los pedidos realizados desde la página pública aparecerán aquí.</p></div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="online-pagination">{{ $orders->links() }}</div>
</section>
@endsection
