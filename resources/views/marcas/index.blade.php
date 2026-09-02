@extends('layouts.app')
@section('title', 'Marcas')
@section('page-title', 'Marcas')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/marcas.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('js/marcas.js') }}" defer></script>
@endpush

@section('content')
<div class="page-header brand-page-header">
    <div class="brand-page-heading">
        <span class="brand-page-icon"><i class="fa-solid fa-star"></i></span>
        <div><span class="brand-eyebrow">Identidad del catálogo</span><h1>Marcas</h1><p>Organiza los fabricantes y controla su disponibilidad en productos y ventas.</p></div>
    </div>
    <div class="brand-header-summary" aria-label="Resumen del resultado"><strong>{{ number_format($marcas->total()) }}</strong><span>{{ $marcas->total() === 1 ? 'resultado' : 'resultados' }}</span></div>
</div>

@if($errors->any())
<div class="alert alert-danger brand-validation-summary" role="alert">
    <i class="fa-solid fa-circle-exclamation"></i><div><strong>Revisa la información ingresada.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
</div>
@endif

@php($canManageBrands = auth()->user()->can('create', App\Models\Marca::class))
<div class="brand-layout {{ $canManageBrands ? '' : 'brand-layout-readonly' }}">
    @if($canManageBrands)
    <aside class="card brand-form-card">
        <div class="brand-card-heading">
            <span><i class="fa-solid {{ $editar ? 'fa-pen' : 'fa-plus' }}"></i></span>
            <div><h2>{{ $editar ? 'Editar marca' : 'Nueva marca' }}</h2><p>{{ $editar ? 'Actualiza su nombre y disponibilidad.' : 'Registra una marca para clasificar tus productos.' }}</p></div>
        </div>
        <form method="POST" action="{{ $editar ? route('marcas.update', $editar) : route('marcas.store') }}" class="brand-form">
            @csrf
            <label class="form-group">
                <span class="form-label">Nombre <b aria-hidden="true">*</b></span>
                <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" maxlength="100" value="{{ old('nombre', $editar->nombre ?? '') }}" placeholder="Ej. Nike" autocomplete="off" required autofocus>
                <small class="brand-field-hint">Debe ser único dentro de tu tienda.</small>
                @error('nombre')<small class="brand-field-error">{{ $message }}</small>@enderror
            </label>
            <label class="brand-status-control">
                <input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" @checked(old('activo', $editar->activo ?? true))>
                <span class="brand-switch" aria-hidden="true"></span><span><strong>Marca activa</strong><small>Disponible para productos y filtros del punto de venta.</small></span>
            </label>
            @error('activo')<small class="brand-field-error brand-status-error">{{ $message }}</small>@enderror
            <div class="brand-form-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> {{ $editar ? 'Guardar cambios' : 'Crear marca' }}</button>@if($editar)<a href="{{ route('marcas.index', request()->except(['edit', 'page'])) }}" class="btn btn-secondary">Cancelar</a>@endif</div>
        </form>
    </aside>
    @endif

    <section class="brand-content">
        <div class="card brand-filter-card">
            <form method="GET" class="brand-filters">
                <label class="brand-filter-search"><span class="form-label">Buscar</span><span class="brand-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Nombre de la marca..." maxlength="100"></span></label>
                <label><span class="form-label">Estado</span><select name="estado" class="form-control"><option value="todos">Todos</option><option value="activo" @selected(request('estado') === 'activo')>Activas</option><option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option></select></label>
                <label><span class="form-label">Ordenar</span><select name="orden" class="form-control"><option value="nombre_asc" @selected(request('orden', 'nombre_asc') === 'nombre_asc')>Nombre A–Z</option><option value="nombre_desc" @selected(request('orden') === 'nombre_desc')>Nombre Z–A</option><option value="recientes" @selected(request('orden') === 'recientes')>Más recientes</option><option value="antiguas" @selected(request('orden') === 'antiguas')>Más antiguas</option></select></label>
                <div class="brand-filter-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Aplicar</button><a href="{{ route('marcas.index') }}" class="btn btn-secondary">Limpiar</a></div>
            </form>
        </div>

        <div class="card brand-list-card">
            <div class="brand-list-heading"><div><h2>Listado de marcas</h2><p>El conteo incluye productos activos e inactivos asociados.</p></div><span>Página {{ $marcas->currentPage() }} de {{ $marcas->lastPage() }}</span></div>
            <div class="table-wrap brand-table-wrap">
                <table class="brand-table">
                    <thead><tr><th>Marca</th><th>Productos</th><th>Estado</th><th class="brand-actions-heading">Acciones</th></tr></thead>
                    <tbody>
                        @forelse($marcas as $brand)
                        <tr>
                            <td data-label="Marca"><div class="brand-identity"><span>{{ mb_strtoupper(mb_substr($brand->nombre, 0, 2)) }}</span><div><strong>{{ $brand->nombre }}</strong><small>Marca del catálogo</small></div></div></td>
                            <td data-label="Productos"><span class="brand-count"><strong>{{ number_format($brand->productos_count) }}</strong><small>{{ $brand->productos_count === 1 ? 'producto' : 'productos' }}</small></span></td>
                            <td data-label="Estado"><span class="brand-state {{ $brand->activo ? 'is-active' : 'is-inactive' }}"><i class="fa-solid fa-circle"></i>{{ $brand->activo ? 'Activa' : 'Inactiva' }}</span></td>
                            <td data-label="Acciones"><div class="brand-actions">
                                @can('update', $brand)<a href="{{ route('marcas.index', array_merge(request()->except('edit'), ['edit' => $brand->id])) }}" class="btn btn-sm btn-outline" aria-label="Editar {{ $brand->nombre }}" title="Editar"><i class="fa-solid fa-pen"></i></a>@endcan
                                @can('delete', $brand)<form method="POST" action="{{ route('marcas.destroy', $brand) }}" data-brand-delete data-name="{{ $brand->nombre }}" data-products="{{ $brand->productos_count }}">@csrf<button type="submit" class="btn btn-sm btn-danger" aria-label="Eliminar {{ $brand->nombre }}" title="Eliminar"><i class="fa-solid fa-trash"></i></button></form>@endcan
                            </div></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="brand-empty-result"><span><i class="fa-solid fa-tags"></i></span><strong>No se encontraron marcas</strong><p>Modifica los filtros o registra una nueva marca para organizar tu catálogo.</p>@if(request()->hasAny(['buscar', 'estado', 'orden']))<a href="{{ route('marcas.index') }}" class="btn btn-secondary">Limpiar filtros</a>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($marcas->hasPages())<div class="brand-pagination">{{ $marcas->links() }}</div>@endif
        </div>
    </section>
</div>

<dialog id="brandDeleteDialog" class="brand-dialog">
    <div class="brand-dialog-content">
        <span class="brand-dialog-icon"><i class="fa-solid fa-trash-can"></i></span><h2>Eliminar marca</h2><p data-delete-message>Confirma que deseas eliminar esta marca.</p>
        <div class="brand-dialog-warning" data-delete-warning hidden><i class="fa-solid fa-triangle-exclamation"></i><span></span></div>
        <div class="brand-dialog-actions"><button type="button" class="btn btn-secondary" data-delete-cancel>Cancelar</button><button type="button" class="btn btn-danger" data-delete-confirm>Eliminar</button></div>
    </div>
</dialog>
@endsection
