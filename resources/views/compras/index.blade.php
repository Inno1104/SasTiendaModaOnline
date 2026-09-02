@extends('layouts.app')
@section('title','Compras')
@section('page-title','Compras')
@push('styles')<link rel="stylesheet" href="{{ asset('css/compras.css') }}">@endpush

@section('content')
<div class="purchase-page">
    <div class="purchase-header"><div><div class="purchase-header__eyebrow">Inventario y abastecimiento</div><h1>Compras</h1><div class="breadcrumb"><a href="{{ route('dashboard') }}">Inicio</a><span class="breadcrumb-sep">›</span>Compras</div></div><a href="{{ route('compras.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva compra</a></div>
    <section class="purchase-summary" aria-label="Resumen de compras"><article class="purchase-card purchase-month-card"><div class="purchase-month-icon"><i class="fa-solid fa-truck"></i></div><div><div class="purchase-label">Compras recibidas del mes</div><div class="purchase-total">{{ $simboloMoneda }} {{ number_format($totalMes,2) }}</div></div></article></section>
    <section class="purchase-card" aria-labelledby="purchase-list-title">
        <div class="purchase-card__header"><h2 class="purchase-card__title" id="purchase-list-title"><i class="fa-solid fa-list"></i>Historial de compras</h2></div>
        <form method="GET" class="purchase-filter-grid" aria-label="Filtros de compras">
            <label class="purchase-field"><span>Buscar</span><input type="search" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="N.º, proveedor o RUC"></label>
            <label class="purchase-field"><span>Proveedor</span><select name="proveedor_id" class="form-control"><option value="">Todos</option>@foreach($proveedores as $proveedor)<option value="{{ $proveedor->id }}" @selected((string) request('proveedor_id') === (string) $proveedor->id)>{{ $proveedor->nombre }}</option>@endforeach</select></label>
            <label class="purchase-field"><span>Estado</span><select name="estado" class="form-control"><option value="">Todos</option><option value="recibido" @selected(request('estado') === 'recibido')>Recibido</option><option value="anulado" @selected(request('estado') === 'anulado')>Anulado</option><option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente</option></select></label>
            <label class="purchase-field"><span>Desde</span><input type="date" name="desde" class="form-control" value="{{ request('desde') }}"></label>
            <label class="purchase-field"><span>Hasta</span><input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}"></label>
            <label class="purchase-field"><span>Orden</span><select name="orden" class="form-control"><option value="recientes" @selected(request('orden', 'recientes') === 'recientes')>Más recientes</option><option value="antiguas" @selected(request('orden') === 'antiguas')>Más antiguas</option><option value="mayor_total" @selected(request('orden') === 'mayor_total')>Mayor total</option><option value="menor_total" @selected(request('orden') === 'menor_total')>Menor total</option></select></label>
            <div class="purchase-filter-actions"><button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button><a href="{{ route('compras.index') }}" class="btn btn-secondary">Limpiar</a></div>
        </form>
        <div class="table-wrap"><table><thead><tr><th>N.º compra</th><th>Proveedor</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>@forelse($compras as $compra)<tr><td><code class="purchase-code">{{ $compra->numero_compra }}</code></td><td>{{ $compra->proveedor->nombre ?? '—' }}</td><td>{{ $compra->fecha->format('d/m/Y') }}</td><td class="purchase-value">{{ $simboloMoneda }} {{ number_format($compra->total,2) }}</td><td><span class="badge purchase-status {{ $compra->estado === 'recibido' ? 'badge-success' : ($compra->estado === 'anulado' ? 'badge-danger' : 'badge-warning') }}">{{ ucfirst($compra->estado) }}</span></td><td><a href="{{ route('compras.show',$compra) }}" class="btn btn-sm btn-secondary" aria-label="Ver compra {{ $compra->numero_compra }}"><i class="fa-solid fa-eye"></i></a></td></tr>@empty<tr><td colspan="6" class="purchase-empty">No hay compras que coincidan con los filtros.</td></tr>@endforelse</tbody></table></div>
        {{ $compras->links() }}
    </section>
</div>
@endsection
