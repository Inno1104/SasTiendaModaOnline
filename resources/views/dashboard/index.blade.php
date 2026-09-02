@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<link href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}" rel="stylesheet">
@endpush

@section('content')
<div class="page-header">
    <div><h1>Dashboard</h1><div class="breadcrumb"><i class="fa-solid fa-house"></i> Inicio <span class="breadcrumb-sep">›</span> Dashboard</div></div>
    <a href="{{ route('ventas.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva Venta</a>
</div>

<form method="GET" action="{{ route('dashboard') }}" class="period-filter" id="periodFilter">
    <div class="period-filter-main">
        <label for="periodo"><i class="fa-regular fa-calendar"></i> Periodo</label>
        <select name="periodo" id="periodo" class="form-control">
            <option value="hoy" {{ $periodo['key'] === 'hoy' ? 'selected' : '' }}>Hoy</option>
            <option value="7_dias" {{ $periodo['key'] === '7_dias' ? 'selected' : '' }}>Últimos 7 días</option>
            <option value="30_dias" {{ $periodo['key'] === '30_dias' ? 'selected' : '' }}>Últimos 30 días</option>
            <option value="mes_actual" {{ $periodo['key'] === 'mes_actual' ? 'selected' : '' }}>Mes actual</option>
            <option value="mes_anterior" {{ $periodo['key'] === 'mes_anterior' ? 'selected' : '' }}>Mes anterior</option>
            <option value="personalizado" {{ $periodo['key'] === 'personalizado' ? 'selected' : '' }}>Personalizado</option>
        </select>
        <span class="period-label">{{ $periodo['label'] }}</span>
    </div>
    <div class="custom-period {{ $periodo['key'] === 'personalizado' ? 'is-visible' : '' }}" id="customPeriod">
        <label>Desde <input type="date" name="desde" value="{{ request('desde', $periodo['start']->toDateString()) }}" class="form-control"></label>
        <label>Hasta <input type="date" name="hasta" value="{{ request('hasta', $periodo['end']->toDateString()) }}" class="form-control"></label>
        <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Aplicar</button>
    </div>
</form>

<div class="kpi-grid">
    <div class="kpi-card c-pink">
        <div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
        <div><div class="kpi-label">Ventas de Hoy</div><div class="kpi-value">{{ $simboloMoneda }} {{ number_format($ventasHoy, 2) }}</div><div class="kpi-change {{ $comparacionVentasHoy['direction'] }}"><i class="fa-solid {{ $comparacionVentasHoy['direction'] === 'up' ? 'fa-arrow-trend-up' : ($comparacionVentasHoy['direction'] === 'down' ? 'fa-arrow-trend-down' : 'fa-minus') }}"></i> {{ $comparacionVentasHoy['text'] }}</div></div>
    </div>
    <div class="kpi-card c-purple">
        <div class="kpi-icon"><i class="fa-solid fa-chart-line"></i></div>
        <div><div class="kpi-label">Ventas del periodo</div><div class="kpi-value">{{ $simboloMoneda }} {{ number_format($ventasPeriodo, 2) }}</div><div class="kpi-change {{ $comparacionVentasPeriodo['direction'] }}"><i class="fa-solid {{ $comparacionVentasPeriodo['direction'] === 'up' ? 'fa-arrow-trend-up' : ($comparacionVentasPeriodo['direction'] === 'down' ? 'fa-arrow-trend-down' : 'fa-minus') }}"></i> {{ $comparacionVentasPeriodo['text'] }}</div></div>
    </div>
    <div class="kpi-card c-blue">
        <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
        <div><div class="kpi-label">Clientes</div><div class="kpi-value">{{ number_format($totalClientes) }}</div><div class="kpi-change {{ $clientesNuevosPeriodo > 0 ? 'up' : 'neutral' }}"><i class="fa-solid fa-user-plus"></i> +{{ $clientesNuevosPeriodo }} en el periodo</div></div>
    </div>
    <div class="kpi-card c-green">
        <div class="kpi-icon"><i class="fa-solid fa-tags"></i></div>
        <div><div class="kpi-label">Productos</div><div class="kpi-value">{{ number_format($totalProductos) }}</div><div class="kpi-change {{ $productosNuevosPeriodo > 0 ? 'up' : 'neutral' }}"><i class="fa-solid fa-plus"></i> +{{ $productosNuevosPeriodo }} en el periodo</div></div>
    </div>
    <a href="{{ route('stock.index', ['filtro' => 'bajo']) }}" class="kpi-card kpi-card-link c-orange" title="Ver variantes con stock bajo">
        <div class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div><div class="kpi-label">Productos con stock bajo</div><div class="kpi-value">{{ $stockBajo }}</div><div class="kpi-change down">{{ $variantesStockBajo }} bajas · {{ $variantesAgotadas }} agotadas <i class="fa-solid fa-arrow-right"></i></div></div>
    </a>
</div>

<div class="charts-grid">
    <section class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-line"></i> Ventas · {{ $periodo['label'] }}</div></div>
        <div class="chart-box"><canvas id="ventasChart"></canvas></div>
    </section>
    <section class="card category-card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-pie"></i> Ventas por Categoría</div></div>
        <div class="chart-box sm"><canvas id="categoriaChart"></canvas></div>
        <div class="category-legend">
            @foreach($ventasPorCategoria as $i => $vc)
                <div class="category-legend-row"><span class="category-dot" style="--category-color:{{ ['#e8398c','#7c3aed','#2563eb','#16a34a','#ea580c'][$i] ?? '#ccc' }}"></span><span class="category-name">{{ $vc->nombre }}</span><strong>{{ $simboloMoneda }} {{ number_format($vc->total, 0) }}</strong></div>
            @endforeach
        </div>
    </section>
</div>

<div class="charts-grid">
    <section class="card"><div class="card-header"><div class="card-title"><i class="fa-solid fa-warehouse"></i> Inventario por Categoría</div></div><div class="chart-box"><canvas id="stockChart"></canvas></div></section>
    <section class="card"><div class="card-header"><div class="card-title"><i class="fa-solid fa-credit-card"></i> Métodos de Pago</div></div><div class="chart-box"><canvas id="metodoChart"></canvas></div></section>
</div>

<div class="bottom-grid">
    <section class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-ranking-star"></i> Top Productos · {{ $periodo['label'] }}</div></div>
        @forelse($topProductos as $i => $prod)
            <div class="rank-item"><div class="rank-num">{{ $i + 1 }}</div><div class="rank-name">{{ $prod->nombre }}</div><div class="rank-qty">{{ $prod->total_vendido }} uds.</div></div>
        @empty
            <p class="dashboard-empty">Sin ventas este mes</p>
        @endforelse
    </section>
    <section class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Últimas Ventas</div><a href="{{ route('ventas.index') }}" class="btn btn-outline btn-sm">Ver todas</a></div>
        @forelse($ultimasVentas as $v)
            <div class="sale-row"><div class="sale-icon"><i class="fa-solid fa-receipt"></i></div><div class="sale-info"><div class="sale-num">{{ $v->numero_venta }}</div><div class="sale-client">{{ $v->cliente ? $v->cliente->nombre . ' ' . $v->cliente->apellido : 'Cliente directo' }}</div></div><div class="sale-summary"><div class="sale-total">{{ $simboloMoneda }} {{ number_format($v->total, 2) }}</div><span class="badge {{ $v->estado === 'completada' ? 'badge-success' : ($v->estado === 'anulada' ? 'badge-danger' : 'badge-warning') }}">{{ ucfirst($v->estado) }}</span></div></div>
        @empty
            <p class="dashboard-empty">Sin ventas aún</p>
        @endforelse
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/chart.umd.min.js') }}?v=4.4.0"></script>
<script>
const labels = @json($labels);
const data = @json($data);
const chartFont = { family: 'Poppins', size: 11 };
const gridColor = '#f0f0f5';

new Chart(document.getElementById('ventasChart'), {
    type: 'line',
    data: { labels, datasets: [{ label: 'Ventas ({{ $simboloMoneda }})', data, borderColor: '#e8398c', backgroundColor: 'rgba(232,57,140,.08)', borderWidth: 2.5, pointBackgroundColor: '#e8398c', pointRadius: 5, tension: .4, fill: true }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { font: chartFont } }, x: { grid: { display: false }, ticks: { font: chartFont } } } }
});

const catLabels = @json($ventasPorCategoria->pluck('nombre'));
const catData = @json($ventasPorCategoria->pluck('total'));
new Chart(document.getElementById('categoriaChart'), {
    type: 'doughnut',
    data: { labels: catLabels.length ? catLabels : ['Sin datos'], datasets: [{ data: catData.length ? catData : [1], backgroundColor: ['#e8398c','#7c3aed','#2563eb','#16a34a','#ea580c'], borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
});

const stockLabels = @json($stockCatLabels);
const stockData = @json($stockCatData);
new Chart(document.getElementById('stockChart'), {
    type: 'bar',
    data: { labels: stockLabels.length ? stockLabels : ['Sin datos'], datasets: [{ label: 'Unidades en stock', data: stockData.length ? stockData : [0], backgroundColor: 'rgba(225,7,91,.88)', hoverBackgroundColor: '#c9004d', borderRadius: 6, maxBarThickness: 38 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { font: chartFont } }, x: { grid: { display: false }, ticks: { font: { family: 'Poppins', size: 10 } } } } }
});

const metodoLabels = @json($metodoLabels);
const metodoData = @json($metodoData);
new Chart(document.getElementById('metodoChart'), {
    type: 'bar',
    data: { labels: metodoLabels.length ? metodoLabels : ['Sin datos'], datasets: [{ label: 'Total ({{ $simboloMoneda }})', data: metodoData.length ? metodoData : [0], backgroundColor: ['#e8398c','#7c3aed','#2563eb','#16a34a','#ea580c'], borderRadius: 6, maxBarThickness: 26 }] },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { color: gridColor }, ticks: { font: chartFont } }, y: { grid: { display: false }, ticks: { font: { family: 'Poppins', size: 12 } } } } }
});
</script>
<script>
const periodSelect = document.getElementById('periodo');
const customPeriod = document.getElementById('customPeriod');
periodSelect.addEventListener('change', function () {
    if (this.value === 'personalizado') {
        customPeriod.classList.add('is-visible');
        return;
    }
    customPeriod.classList.remove('is-visible');
    document.getElementById('periodFilter').submit();
});
</script>
@endpush
