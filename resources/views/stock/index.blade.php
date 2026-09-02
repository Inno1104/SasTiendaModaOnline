@extends('layouts.app')
@section('title', 'Stock')
@section('page-title', 'Stock')
@push('styles')<link rel="stylesheet" href="{{ asset('css/stock.css') }}">@endpush
@section('content')
<div class="page-header stock-page-header"><div><span class="stock-eyebrow">Inventario operativo</span><h1>Control de stock</h1><div class="breadcrumb"><a href="{{ route('dashboard') }}">Inicio</a><span>›</span>Stock</div></div><a href="{{ route('stock.replenishment') }}" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Reporte de reposición</a></div>
<section class="stock-summary" aria-label="Resumen de inventario">
@foreach([['total','fa-boxes-stacked','Unidades totales',number_format($totalUnidades),'En todo el inventario'],['low','fa-triangle-exclamation','Stock bajo',$bajoStock,'Requieren atención'],['out','fa-ban','Agotadas',$sinStock,'Sin existencias'],['available','fa-circle-check','Disponibles',$variantesDisponibles,'Con stock disponible']] as [$tone,$icon,$label,$value,$description])
<article class="stock-summary-card stock-summary-{{ $tone }}"><span class="stock-summary-icon"><i class="fa-solid {{ $icon }}"></i></span><div><small>{{ $label }}</small><strong>{{ $value }}</strong><em>{{ $description }}</em></div><span class="stock-spark" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span></article>
@endforeach
</section>
<section class="card stock-catalog-card">
<form method="GET" class="stock-filters" aria-label="Filtros de inventario">
<label class="stock-search"><span class="sr-only">Buscar inventario</span><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Producto, código, barra, talla o color"></label>
<select name="categoria_id" class="form-control" aria-label="Categoría"><option value="">Todas las categorías</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected((string)request('categoria_id')===(string)$categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select>
<select name="filtro" class="form-control" aria-label="Estado del stock"><option value="todos">Todos los estados</option><option value="bajo" @selected(request('filtro')==='bajo')>Stock bajo</option><option value="agotado" @selected(request('filtro')==='agotado')>Agotadas</option><option value="disponible" @selected(request('filtro')==='disponible')>Disponibles</option></select>
<select name="producto_estado" class="form-control" aria-label="Estado del producto"><option value="activo" @selected(request('producto_estado','activo')==='activo')>Productos activos</option><option value="inactivo" @selected(request('producto_estado')==='inactivo')>Productos inactivos</option><option value="todos" @selected(request('producto_estado')==='todos')>Todos los productos</option></select>
<select name="orden" class="form-control" aria-label="Orden"><option value="stock_asc" @selected(request('orden','stock_asc')==='stock_asc')>Menor stock</option><option value="stock_desc" @selected(request('orden')==='stock_desc')>Mayor stock</option><option value="producto_asc" @selected(request('orden')==='producto_asc')>Nombre del producto</option></select>
<select name="por_pagina" class="form-control stock-page-size" aria-label="Filas por página">@foreach([20,50,100] as $cantidad)<option value="{{ $cantidad }}" @selected((int)request('por_pagina',20)===$cantidad)>{{ $cantidad }} filas</option>@endforeach</select>
<button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Aplicar</button><a href="{{ route('stock.index') }}" class="btn btn-secondary">Limpiar</a>
</form>
<div class="stock-help"><i class="fa-solid fa-shield-halved"></i><span>Los valores permanecen bloqueados hasta seleccionar <strong>Editar</strong>. Todo ajuste requiere motivo y confirmación.</span></div>
<div class="table-wrap stock-table-wrap"><table class="stock-table"><thead><tr><th>Producto</th><th>Categoría</th><th>Variante</th><th>Stock</th><th>Mínimo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($variantes as $v)
@php($state=match($v->estadoStock()){'agotada'=>['Agotada','out'],'bajo'=>['Stock bajo','low'],default=>['Disponible','available']})
<tr class="stock-row stock-row-{{ $state[1] }}" data-stock-row data-current-stock="{{ $v->stock }}" data-current-minimum="{{ $v->stock_minimo }}">
<td data-label="Producto"><div class="stock-product"><span class="stock-product-icon">@if($v->producto?->imagen)<img src="{{ asset('storage/'.$v->producto->imagen) }}" alt="" loading="lazy">@else<i class="fa-solid fa-shirt"></i>@endif</span><div><strong>{{ $v->producto->nombre??'—' }}</strong><small>{{ $v->producto->codigo??'' }}</small></div></div></td>
<td data-label="Categoría">{{ $v->producto->categoria->nombre??'—' }}@if($v->producto&&!$v->producto->activo)<span class="stock-product-inactive">Inactivo</span>@endif</td>
<td data-label="Variante"><strong>{{ $v->talla }}</strong><small class="stock-color"><i class="fa-solid fa-palette"></i> {{ $v->color }}</small></td>
<td data-label="Stock">@can('update',$v)<input type="number" form="stk-{{ $v->id }}" name="stock" value="{{ $v->stock }}" min="0" max="2000000000" class="form-control stock-number" disabled data-stock-value>@else<strong>{{ $v->stock }}</strong>@endcan</td>
<td data-label="Mínimo">@can('update',$v)<input type="number" form="stk-{{ $v->id }}" name="stock_minimo" value="{{ $v->stock_minimo }}" min="0" max="2000000000" class="form-control stock-number" disabled data-minimum-value>@else{{ $v->stock_minimo }}@endcan</td>
<td data-label="Estado"><span class="stock-state stock-state-{{ $state[1] }}" data-stock-state><i class="fa-solid fa-circle"></i> {{ $state[0] }}</span></td>
<td data-label="Acciones"><div class="stock-actions"><a href="{{ route('stock.history',$v) }}" class="btn btn-sm btn-secondary" title="Ver historial"><i class="fa-solid fa-clock-rotate-left"></i><span>Historial</span></a>@can('update',$v)<button type="button" class="btn btn-sm btn-primary" data-stock-edit><i class="fa-solid fa-pen"></i><span>Editar</span></button><button type="button" class="btn btn-sm btn-secondary is-hidden" data-stock-cancel>Cancelar</button>@else<span class="stock-muted">Solo lectura</span>@endcan</div></td>
</tr>
@can('update',$v)<tr class="stock-reason-row is-hidden" data-stock-editor><td colspan="7"><form id="stk-{{ $v->id }}" method="POST" action="{{ route('stock.update',$v) }}" class="stock-adjust-form" data-confirm-message="¿Confirmas este ajuste? El cambio quedará registrado en el kardex.">@csrf<input type="hidden" name="expected_version" value="{{ $v->lock_version }}"><label><span>Motivo obligatorio</span><input name="motivo" class="form-control" minlength="5" maxlength="255" required disabled data-stock-reason placeholder="Ej.: corrección después del conteo físico"></label><button class="btn btn-primary" disabled data-stock-save><i class="fa-solid fa-floppy-disk"></i> Confirmar ajuste</button></form></td></tr>@endcan
@empty<tr><td colspan="7" class="stock-empty"><i class="fa-solid fa-box-open"></i><strong>No hay variantes con estos filtros</strong><span>Modifica los criterios o limpia la búsqueda.</span></td></tr>@endforelse
</tbody></table></div><div class="stock-pagination">{{ $variantes->withQueryString()->links() }}</div>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/stock.js') }}" defer></script>@endpush
