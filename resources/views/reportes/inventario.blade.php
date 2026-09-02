@extends('layouts.app')
@section('title', 'Reporte de Inventario')
@section('page-title', 'Reporte de Inventario')
@push('styles')<link rel="stylesheet" href="{{ asset('css/inventory-report.css') }}">@endpush
@section('content')
<header class="inventory-heading">
    <div class="inventory-heading__title"><span class="inventory-heading__icon"><i class="fa-solid fa-box-archive"></i></span><div><h1>Inventario General</h1><p>Existencias y valorización por producto y variante.</p></div></div>
    <a class="btn btn-secondary inventory-export" href="{{ route('reportes.inventario.export', request()->except(['page', 'por_pagina'])) }}"><i class="fa-solid fa-download"></i> Exportar CSV</a>
</header>
<section class="inventory-filter-card" aria-labelledby="inventory-filter-title">
    <h2 id="inventory-filter-title" class="sr-only">Filtros del inventario</h2>
    <form method="GET" class="inventory-filters">
        <label class="inventory-search"><span class="sr-only">Buscar producto</span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="form-control" name="buscar" value="{{ $filters['buscar'] ?? '' }}" maxlength="100" placeholder="Buscar producto, código o código de barras"></label>
        <label><span>Categoría</span><select class="form-control" name="categoria_id"><option value="">Todas las categorías</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected(($filters['categoria_id'] ?? null)===$categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select></label>
        <label><span>Estado</span><select class="form-control" name="estado"><option value="todos">Todos los estados</option><option value="atencion" @selected(($filters['estado']??null)==='atencion')>Requieren atención</option><option value="bajo" @selected(($filters['estado']??null)==='bajo')>Con stock bajo</option><option value="agotado" @selected(($filters['estado']??null)==='agotado')>Con variantes agotadas</option><option value="disponible" @selected(($filters['estado']??null)==='disponible')>Disponibles</option></select></label>
        <label><span>Ordenar por</span><select class="form-control" name="orden"><option value="producto_asc">Producto A–Z</option><option value="stock_asc" @selected(($filters['orden']??null)==='stock_asc')>Menor stock</option><option value="stock_desc" @selected(($filters['orden']??null)==='stock_desc')>Mayor stock</option><option value="valor_desc" @selected(($filters['orden']??null)==='valor_desc')>Mayor valor</option></select></label>
        <label><span>Por página</span><select class="form-control" name="por_pagina">@foreach([25,50,100] as $size)<option value="{{ $size }}" @selected(($filters['por_pagina']??50)===$size)>{{ $size }} por página</option>@endforeach</select></label>
        <div class="inventory-filter-actions"><button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Aplicar filtros</button><a class="btn btn-secondary" href="{{ route('reportes.inventario') }}"><i class="fa-solid fa-rotate-right"></i> Limpiar</a></div>
    </form>
    @if($summary['productos_bajo'] > 0 || $summary['productos_agotados'] > 0)<div class="inventory-attention" role="status"><i class="fa-solid fa-triangle-exclamation"></i><strong>{{ $summary['productos_bajo'] }} con variantes bajas</strong><span></span><strong>{{ $summary['productos_agotados'] }} con variantes agotadas</strong><small>dentro del filtro actual.</small></div>@endif
</section>
<section class="inventory-metrics" aria-label="Resumen del inventario">
    <article><span class="metric-icon metric-products"><i class="fa-solid fa-cube"></i></span><div><small>Total de productos</small><strong>{{ $summary['productos'] }}</strong><em>productos</em></div></article>
    <article><span class="metric-icon metric-units"><i class="fa-solid fa-table-cells-large"></i></span><div><small>Unidades totales</small><strong>{{ $summary['unidades'] }}</strong><em>unidades</em></div></article>
    <article><span class="metric-icon metric-value"><i class="fa-solid fa-circle-dollar-to-slot"></i></span><div><small>Valor del inventario</small><strong>{{ $simboloMoneda }} {{ number_format($summary['valor'], 2) }}</strong></div></article>
    <article><span class="metric-icon metric-available"><i class="fa-solid fa-circle-check"></i></span><div><small>Disponibles</small><strong>{{ $summary['productos_disponibles'] }}</strong><em>productos</em></div></article>
    <article><span class="metric-icon metric-low"><i class="fa-solid fa-circle-exclamation"></i></span><div><small>Bajas</small><strong>{{ $summary['productos_bajo'] }}</strong><em>productos</em></div></article>
    <article><span class="metric-icon metric-out"><i class="fa-solid fa-circle-xmark"></i></span><div><small>Agotadas</small><strong>{{ $summary['productos_agotados'] }}</strong><em>productos</em></div></article>
</section>
<section class="inventory-table-card">
    <div class="inventory-table-wrap"><table class="inventory-table"><thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Género</th><th>Variantes</th><th>Stock total</th><th>Valor inventario</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($productos as $producto)
        @php
            $stockTotal = (int) ($producto->stock_total ?? 0);
            $variantesAgotadas = (int) $producto->variantes_agotadas;
            $variantesBajas = (int) $producto->variantes_bajas;
            $variantesTotal = (int) $producto->variantes_total;
            $requiereAtencion = $variantesAgotadas > 0 || $variantesBajas > 0 || $variantesTotal === 0;
        @endphp
        <tr>
            <td data-label="Código"><code class="inventory-code">{{ $producto->codigo }}</code></td>
            <td data-label="Producto"><div class="inventory-product">@if($producto->imagen)<img src="{{ asset('storage/'.$producto->imagen) }}" alt="" loading="lazy">@else<span><i class="fa-solid fa-shirt"></i></span>@endif<strong>{{ $producto->nombre }}</strong></div></td>
            <td data-label="Categoría">{{ $producto->categoria?->nombre ?? '—' }}</td><td data-label="Género">{{ ucfirst($producto->genero) }}</td><td data-label="Variantes">{{ $variantesTotal }}</td>
            <td data-label="Stock total"><span class="inventory-stock {{ $stockTotal <= 0 ? 'is-out' : ($requiereAtencion ? 'is-low' : 'is-available') }}">{{ $stockTotal }} uds.</span></td>
            <td data-label="Valor inventario"><strong>{{ $simboloMoneda }} {{ number_format($stockTotal * (float) $producto->precio_compra, 2) }}</strong></td>
            <td data-label="Estado"><div class="inventory-statuses">@if($variantesTotal === 0)<span class="inventory-status is-out">Sin variantes</span>@endif @if($variantesAgotadas > 0)<span class="inventory-status is-out">{{ $variantesAgotadas }} agotada(s)</span>@endif @if($variantesBajas > 0)<span class="inventory-status is-low">{{ $variantesBajas }} baja(s)</span>@endif @if(! $requiereAtencion)<span class="inventory-status is-available">Disponible</span>@endif</div></td>
            <td data-label="Acciones"><div class="inventory-actions">@can('view',$producto)<a href="{{ route('productos.show',$producto) }}" aria-label="Ver {{ $producto->nombre }}" title="Ver detalle"><i class="fa-regular fa-eye"></i></a>@endcan @can('update',$producto)<a class="is-primary" href="{{ route('productos.edit',$producto) }}" aria-label="Editar {{ $producto->nombre }}" title="Editar producto"><i class="fa-solid fa-pen"></i></a>@endcan</div></td>
        </tr>
    @empty<tr><td colspan="9" class="inventory-empty"><i class="fa-solid fa-box-open"></i><strong>Sin productos para estos filtros</strong><span>Prueba modificando o limpiando los filtros.</span></td></tr>@endforelse
    </tbody></table></div>
    <footer class="inventory-table-footer"><p>Mostrando {{ $productos->firstItem() ?? 0 }} a {{ $productos->lastItem() ?? 0 }} de {{ $productos->total() }} productos</p><div>{{ $productos->links() }}</div></footer>
</section>
@endsection
