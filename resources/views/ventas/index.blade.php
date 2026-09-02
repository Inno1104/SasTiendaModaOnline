@extends('layouts.app')
@section('title', 'Ventas')
@section('page-title', 'Historial de Ventas')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/sales-history.css') }}">
<link rel="stylesheet" href="{{ asset('css/sales-metrics.css') }}">
<link rel="stylesheet" href="{{ asset('css/sales-history-ui.css') }}">
@endpush

@section('content')
<div class="page-header sales-page-header">
    <div class="sales-page-title">
        <span><i class="fa-solid fa-receipt"></i></span>
        <div><h1>Historial de Ventas</h1><p>Consulta, filtra y supervisa las operaciones de tu tienda.</p></div>
    </div>
    <a href="{{ route('ventas.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva Venta</a>
</div>

@if($errors->any())
    <div class="sales-alert" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
@endif

<div class="card sales-filters-card">
    <div class="sales-section-heading"><div><i class="fa-solid fa-sliders"></i><span><strong>Filtros de búsqueda</strong><small>Combina los criterios para encontrar una operación</small></span></div></div>
    <form method="GET" class="sales-filters">
        <label class="sales-search"><span>Buscar venta</span><div><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" class="form-control" value="{{ request('q') }}" maxlength="100" placeholder="Número, cliente, documento, vendedor o referencia..."></div></label>
        <label><span>Periodo</span><select name="periodo" class="form-control"><option value="">Cualquier periodo</option><option value="hoy" @selected(request('periodo') === 'hoy')>Hoy</option><option value="7_dias" @selected(request('periodo') === '7_dias')>Últimos 7 días</option><option value="30_dias" @selected(request('periodo') === '30_dias')>Últimos 30 días</option><option value="mes_actual" @selected(request('periodo') === 'mes_actual')>Mes actual</option></select></label>
        <label><span>Desde</span><input type="date" name="desde" class="form-control" value="{{ request('desde') }}"></label>
        <label><span>Hasta</span><input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}"></label>
        <label><span>Estado</span><select name="estado" class="form-control"><option value="">Todos los estados</option><option value="completada" @selected(request('estado') === 'completada')>Completada</option><option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente</option><option value="anulada" @selected(request('estado') === 'anulada')>Anulada</option><option value="devuelta" @selected(request('estado') === 'devuelta')>Devuelta</option></select></label>
        <label><span>Vendedor</span><select name="vendedor_id" class="form-control"><option value="">Todos los vendedores</option>@foreach($vendedores as $vendedor)<option value="{{ $vendedor->id }}" @selected((string) request('vendedor_id') === (string) $vendedor->id)>{{ $vendedor->name }}</option>@endforeach</select></label>
        <label><span>Método de pago</span><select name="metodo" class="form-control"><option value="">Todos los métodos</option>@foreach(['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'yape' => 'Yape', 'plin' => 'Plin'] as $value => $label)<option value="{{ $value }}" @selected(request('metodo') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label><span>Caja</span><select name="caja_id" class="form-control"><option value="">Todas las cajas</option>@foreach($cajas as $caja)<option value="{{ $caja->id }}" @selected((string) request('caja_id') === (string) $caja->id)>Caja #{{ $caja->id }} · {{ $caja->fecha->format('d/m/Y') }} · {{ ucfirst($caja->estado) }}</option>@endforeach</select></label>
        <div class="sales-filter-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Aplicar filtros</button><a href="{{ route('ventas.index') }}" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Limpiar</a></div>
    </form>
</div>

<section class="sales-metrics" aria-label="Resumen del historial filtrado">
    <article class="sales-metric metric-results"><span><i class="fa-solid fa-receipt"></i></span><div><small>Resultados</small><strong>{{ number_format($metrics['results_count']) }}</strong><em>Ventas encontradas</em></div></article>
    <article class="sales-metric metric-completed"><span><i class="fa-solid fa-circle-check"></i></span><div><small>Completadas</small><strong>{{ number_format($metrics['completed_count']) }}</strong><em>Ventas válidas</em></div></article>
    <article class="sales-metric metric-cancelled"><span><i class="fa-solid fa-ban"></i></span><div><small>Anuladas</small><strong>{{ number_format($metrics['cancelled_count']) }}</strong><em>No generan ingresos</em></div></article>
    <article class="sales-metric metric-cancelled"><span><i class="fa-solid fa-rotate-left"></i></span><div><small>Devueltas</small><strong>{{ number_format($metrics['returned_count']) }}</strong><em>Revertidas después del cierre</em></div></article>
    <article class="sales-metric metric-income"><span><i class="fa-solid fa-sack-dollar"></i></span><div><small>Ingresos</small><strong>{{ $simboloMoneda }} {{ number_format($metrics['income'], 2) }}</strong><em>Solo completadas</em></div></article>
    <article class="sales-metric metric-ticket"><span><i class="fa-solid fa-chart-line"></i></span><div><small>Ticket promedio</small><strong>{{ $simboloMoneda }} {{ number_format($metrics['average_ticket'], 2) }}</strong><em>Promedio por venta</em></div></article>
    <article class="sales-metric metric-discount"><span><i class="fa-solid fa-tags"></i></span><div><small>Descuentos</small><strong>{{ $simboloMoneda }} {{ number_format($metrics['discounts'], 2) }}</strong><em>Concedidos</em></div></article>
    <article class="sales-metric metric-tax"><span><i class="fa-solid fa-percent"></i></span><div><small>Impuestos</small><strong>{{ $simboloMoneda }} {{ number_format($metrics['taxes'], 2) }}</strong><em>En ventas válidas</em></div></article>
</section>

<div class="card sales-table-card">
    <div class="sales-table-heading"><div><i class="fa-solid fa-list-check"></i><span><strong>Operaciones registradas</strong><small>{{ $ventas->total() }} resultados · página {{ $ventas->currentPage() }} de {{ $ventas->lastPage() }}</small></span></div></div>
    <div class="table-wrap">
        <table class="sales-table">
            <thead><tr><th>Venta</th><th>Fecha y hora</th><th>Cliente</th><th>Vendedor</th><th>Comprobante</th><th>Total</th><th>Pago</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
                @forelse($ventas as $venta)
                    @php($paymentMethods = $venta->pagos->pluck('metodo')->unique())
                    <tr>
                        <td data-label="Venta"><a href="{{ route('ventas.show', $venta) }}" class="sale-number"><i class="fa-solid fa-receipt"></i><span>{{ $venta->numero_venta }}</span></a></td>
                        <td data-label="Fecha"><span class="sale-date">{{ $venta->fecha->format('d/m/Y') }}</span><small class="sale-time">{{ $venta->created_at->format('H:i') }}</small></td>
                        <td data-label="Cliente">@if($venta->cliente)<span class="sale-customer">{{ $venta->cliente->nombre }} {{ $venta->cliente->apellido }}</span>@else<span class="direct-customer">Cliente directo</span>@endif</td>
                        <td data-label="Vendedor"><span class="seller-name">{{ $venta->vendedor->name ?? 'No disponible' }}</span></td>
                        <td data-label="Comprobante"><span class="receipt-badge"><i class="fa-regular fa-file-lines"></i>{{ ucfirst($venta->tipo_comprobante) }}</span></td>
                        <td data-label="Total"><strong class="sale-total">{{ $simboloMoneda }} {{ number_format($venta->total, 2) }}</strong></td>
                        <td data-label="Pago"><span class="payment-badge"><i class="fa-solid fa-wallet"></i>{{ $paymentMethods->count() > 1 ? 'Combinado' : ucfirst($paymentMethods->first() ?? $venta->metodo_pago) }}</span></td>
                        <td data-label="Estado"><span class="sale-status status-{{ $venta->estado }}"><i class="fa-solid {{ $venta->estado === 'completada' ? 'fa-circle-check' : ($venta->estado === 'anulada' ? 'fa-circle-xmark' : ($venta->estado === 'devuelta' ? 'fa-rotate-left' : 'fa-clock')) }}"></i>{{ ucfirst($venta->estado) }}</span></td>
                        <td data-label="Acciones"><div class="sale-actions"><a href="{{ route('ventas.show', $venta) }}" class="sale-action view" title="Ver detalle" aria-label="Ver venta {{ $venta->numero_venta }}"><i class="fa-solid fa-eye"></i></a>@if($venta->estado === 'completada' && $venta->caja?->estado === 'abierta' && auth()->user()->can('cancel', $venta))<button type="button" class="sale-action cancel cancel-sale" data-url="{{ route('ventas.anular', $venta) }}" data-mode="anular" data-number="{{ $venta->numero_venta }}" data-total="{{ $simboloMoneda }} {{ number_format($venta->total, 2) }}" title="Anular venta" aria-label="Anular venta {{ $venta->numero_venta }}"><i class="fa-solid fa-ban"></i></button>@elseif($venta->estado === 'completada' && $venta->caja?->estado === 'cerrada' && auth()->user()->can('return', $venta))<button type="button" class="sale-action cancel cancel-sale" data-url="{{ route('ventas.devolver', $venta) }}" data-mode="devolver" data-number="{{ $venta->numero_venta }}" data-total="{{ $simboloMoneda }} {{ number_format($venta->total, 2) }}" title="Procesar devolución" aria-label="Devolver venta {{ $venta->numero_venta }}"><i class="fa-solid fa-rotate-left"></i></button>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="sales-empty"><span><i class="fa-solid fa-receipt"></i></span><strong>No encontramos ventas</strong><p>Modifica los filtros o registra una nueva venta.</p><a href="{{ route('ventas.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva venta</a></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="sales-pagination">{{ $ventas->withQueryString()->links() }}</div>
</div>

<dialog id="cancelSaleDialog" class="cancel-sale-dialog">
    <form id="cancelSaleForm" method="POST">@csrf<div class="cancel-dialog-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><h2 id="saleReversalTitle">Anular venta</h2><p id="saleReversalDescription">Vas a revertir <strong id="cancelSaleNumber"></strong> por <strong id="cancelSaleTotal"></strong>. El stock será repuesto y se registrará la reversión financiera.</p><label for="cancelReason" id="saleReversalReasonLabel">Motivo</label><textarea id="cancelReason" name="motivo" rows="4" minlength="5" maxlength="500" required placeholder="Describe el motivo..."></textarea><small><span id="reasonLength">0</span>/500 caracteres</small><div class="cancel-dialog-actions"><button type="button" id="closeCancelSale" class="btn btn-secondary">Conservar venta</button><button type="submit" id="saleReversalSubmit" class="btn btn-danger"><i class="fa-solid fa-ban"></i> Confirmar</button></div></form>
</dialog>
@endsection

@push('scripts')
<script src="{{ asset('js/sales-history.js') }}" defer></script>
@endpush
