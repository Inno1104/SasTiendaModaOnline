@extends('layouts.app')
@section('title','Reporte de Ventas')
@section('page-title','Reporte de Ventas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sales-report.css') }}">@endpush

@section('content')
<header class="page-header sales-report-header">
    <div><h1>Reporte de Ventas</h1><div class="breadcrumb"><a href="{{ route('dashboard') }}">Inicio</a><span>›</span><span>Reportes</span><span>›</span>Ventas</div></div>
    <button type="submit" form="salesReportFilters" class="btn btn-primary"><i class="fa-solid fa-chart-column"></i> Generar reporte</button>
</header>

<section class="card sales-report-filter-card">
    <form method="GET" id="salesReportFilters" class="sales-report-filters">
        <label><span>Desde</span><input type="date" name="desde" class="form-control" value="{{ $desde }}" required></label>
        <label><span>Hasta</span><input type="date" name="hasta" class="form-control" value="{{ $hasta }}" required></label>
        <div class="sales-report-filter-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Aplicar filtros</button><a href="{{ route('reportes.ventas') }}" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Limpiar</a></div>
        <nav class="sales-quick-ranges" aria-label="Rangos rápidos">
            @foreach([
                'Hoy' => [now()->toDateString(),now()->toDateString()],
                '7 días' => [now()->subDays(6)->toDateString(),now()->toDateString()],
                '30 días' => [now()->subDays(29)->toDateString(),now()->toDateString()],
                'Este mes' => [now()->startOfMonth()->toDateString(),now()->toDateString()],
            ] as $label => [$from,$to])
                <a href="{{ route('reportes.ventas',['desde'=>$from,'hasta'=>$to]) }}" @class(['active'=>$desde===$from && $hasta===$to])>{{ $label }}</a>
            @endforeach
        </nav>
    </form>
</section>

<section class="sales-report-kpis" aria-label="Resumen de ventas">
    <article class="sales-report-kpi kpi-pink"><span class="kpi-icon"><i class="fa-solid fa-bag-shopping"></i></span><div><small>Total ventas</small><strong>{{ number_format($totalVentas) }}</strong><em>Operaciones completadas</em></div><i class="fa-solid fa-chart-line kpi-trend"></i></article>
    <article class="sales-report-kpi kpi-green"><span class="kpi-icon"><i class="fa-solid fa-circle-dollar-to-slot"></i></span><div><small>Ingresos totales</small><strong>{{ $simboloMoneda }} {{ number_format($totalGeneral,2) }}</strong><em>En el periodo elegido</em></div><i class="fa-solid fa-chart-line kpi-trend"></i></article>
    @foreach($porMetodoPago as $metodo => $monto)
        @php($tone = ['tarjeta'=>'violet','efectivo'=>'blue','yape'=>'orange','plin'=>'cyan','transferencia'=>'cyan'][$metodo] ?? 'violet')
        <article class="sales-report-kpi kpi-{{ $tone }}"><span class="kpi-icon"><i class="fa-solid {{ $metodo === 'efectivo' ? 'fa-money-bill-wave' : 'fa-credit-card' }}"></i></span><div><small>{{ ucfirst($metodo) }}</small><strong>{{ $simboloMoneda }} {{ number_format($monto,2) }}</strong><em>Monto conciliado</em></div><i class="fa-solid fa-chart-line kpi-trend"></i></article>
    @endforeach
</section>

<section class="sales-report-charts">
    <article class="card sales-chart-card"><header><div><i class="fa-solid fa-chart-line"></i><strong>Ventas por día</strong></div><span>{{ $desde }} — {{ $hasta }}</span></header><div class="sales-chart-canvas"><canvas id="salesDailyChart" aria-label="Gráfico de ventas por día"></canvas></div></article>
    <article class="card sales-chart-card"><header><div><i class="fa-solid fa-chart-pie"></i><strong>Ventas por método de pago</strong></div><span>Distribución del monto</span></header><div class="payment-chart-layout"><div class="payment-chart-canvas"><canvas id="salesPaymentChart" aria-label="Gráfico por método de pago"></canvas><div><small>Total</small><strong>{{ $simboloMoneda }} {{ number_format($totalGeneral,2) }}</strong></div></div><ul>@forelse($porMetodoPago as $metodo=>$monto)<li><span><i></i>{{ ucfirst($metodo) }}</span><strong>{{ $simboloMoneda }} {{ number_format($monto,2) }}</strong></li>@empty<li>Sin pagos en el periodo</li>@endforelse</ul></div></article>
</section>

<section class="card sales-detail-card">
    <header class="sales-detail-heading"><div><i class="fa-solid fa-table-list"></i><strong>Detalle de ventas</strong></div><span>{{ $ventas->total() }} resultados</span></header>
    <div class="table-wrap"><table class="sales-report-table"><thead><tr><th>N° venta</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Comprobante</th><th>Método</th><th>Total</th><th></th></tr></thead><tbody>
    @forelse($ventas as $v)<tr>
        <td data-label="Venta"><a href="{{ route('ventas.show',$v) }}" class="sales-number">{{ $v->numero_venta }}</a></td>
        <td data-label="Fecha">{{ $v->fecha->format('d/m/Y') }} <small>{{ $v->created_at?->format('H:i') }}</small></td>
        <td data-label="Cliente"><i class="fa-regular fa-user"></i> {{ $v->cliente ? $v->cliente->nombre.' '.$v->cliente->apellido : 'Cliente directo' }}</td>
        <td data-label="Vendedor">{{ $v->vendedor->name ?? '—' }}</td>
        <td data-label="Comprobante"><span class="sales-receipt"><i class="fa-regular fa-file-lines"></i>{{ ucfirst($v->tipo_comprobante) }}</span></td>
        <td data-label="Método"><span class="sales-method">{{ $v->pagos->pluck('metodo')->unique()->count() > 1 ? 'Combinado' : ucfirst($v->metodo_pago) }}</span></td>
        <td data-label="Total"><strong>{{ $simboloMoneda }} {{ number_format($v->total,2) }}</strong></td>
        <td><a href="{{ route('ventas.show',$v) }}" class="sales-row-action" aria-label="Ver {{ $v->numero_venta }}"><i class="fa-solid fa-ellipsis-vertical"></i></a></td>
    </tr>@empty<tr><td colspan="8"><div class="sales-report-empty"><i class="fa-solid fa-chart-column"></i><strong>Sin ventas en el periodo seleccionado</strong><span>Prueba con otro rango de fechas.</span></div></td></tr>@endforelse
    </tbody></table></div>
    <footer class="sales-report-pagination"><span>Mostrando {{ $ventas->firstItem() ?? 0 }} a {{ $ventas->lastItem() ?? 0 }} de {{ $ventas->total() }}</span>{{ $ventas->links() }}</footer>
</section>

<script type="application/json" id="salesReportChartData">@json($chartData)</script>
@endsection
@push('scripts')<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script><script src="{{ asset('js/sales-report.js') }}" defer></script>@endpush
