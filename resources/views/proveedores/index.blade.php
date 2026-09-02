@extends('layouts.app')
@section('title', 'Proveedores')
@section('page-title', 'Proveedores')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/proveedores.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('js/proveedores.js') }}" defer></script>
@endpush

@section('content')
<div class="supplier-page-header">
    <div class="supplier-heading">
        <span class="supplier-heading-icon"><i class="fa-solid fa-truck-fast"></i></span>
        <div><span class="supplier-eyebrow">Red de abastecimiento</span><h1>Proveedores</h1><p>Gestiona contactos, disponibilidad y relaciones comerciales de tu tienda.</p></div>
    </div>
    <div class="supplier-header-actions">
        <span class="supplier-result-count"><strong>{{ number_format($proveedores->total()) }}</strong><small>{{ $proveedores->total() === 1 ? 'resultado' : 'resultados' }}</small></span>
        @can('create', App\Models\Proveedor::class)<a href="{{ route('proveedores.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo proveedor</a>@endcan
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger supplier-validation-summary" role="alert" aria-live="polite"><i class="fa-solid fa-circle-exclamation"></i><div><strong>No se pudo completar la operación.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif

<section class="card supplier-filter-card" aria-labelledby="supplierFiltersTitle">
    <div class="supplier-card-title"><span><i class="fa-solid fa-filter"></i></span><div><h2 id="supplierFiltersTitle">Buscar y filtrar</h2><p>Encuentra proveedores por sus datos comerciales.</p></div></div>
    <form method="GET" class="supplier-filters">
        <label class="supplier-search-field"><span>Buscar</span><span class="supplier-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Nombre, RUC, contacto, teléfono o correo..." maxlength="100"></span></label>
        <label><span>Estado</span><select name="estado" class="form-control"><option value="todos">Todos</option><option value="activo" @selected(request('estado') === 'activo')>Activos</option><option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option></select></label>
        <label><span>Orden</span><select name="orden" class="form-control"><option value="nombre_asc" @selected(request('orden', 'nombre_asc') === 'nombre_asc')>Nombre A–Z</option><option value="nombre_desc" @selected(request('orden') === 'nombre_desc')>Nombre Z–A</option><option value="recientes" @selected(request('orden') === 'recientes')>Más recientes</option><option value="antiguos" @selected(request('orden') === 'antiguos')>Más antiguos</option></select></label>
        <div class="supplier-filter-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Aplicar</button><a href="{{ route('proveedores.index') }}" class="btn btn-secondary">Limpiar</a></div>
    </form>
</section>

<section class="card supplier-list-card" aria-labelledby="supplierListTitle">
    <div class="supplier-list-heading"><div><h2 id="supplierListTitle">Listado de proveedores</h2><p>Las dependencias incluyen productos y compras históricas.</p></div><span>Página {{ $proveedores->currentPage() }} de {{ $proveedores->lastPage() }}</span></div>
    <div class="table-wrap supplier-table-wrap">
        <table class="supplier-table">
            <thead><tr><th>Proveedor</th><th>Contacto</th><th>Ubicación</th><th>Dependencias</th><th>Estado</th><th class="supplier-actions-heading">Acciones</th></tr></thead>
            <tbody>
                @forelse($proveedores as $supplier)
                <tr>
                    <td data-label="Proveedor"><div class="supplier-identity"><span>{{ mb_strtoupper(mb_substr($supplier->nombre, 0, 2)) }}</span><div><strong>{{ $supplier->nombre }}</strong><small>{{ $supplier->ruc ?: 'Sin identificación fiscal' }}</small></div></div></td>
                    <td data-label="Contacto"><div class="supplier-contact"><strong>{{ $supplier->contacto ?: 'Sin contacto' }}</strong><small><i class="fa-regular fa-envelope"></i> {{ $supplier->email ?: 'Sin correo' }}</small><small><i class="fa-solid fa-phone"></i> {{ $supplier->telefono ?: 'Sin teléfono' }}</small></div></td>
                    <td data-label="Ubicación"><div class="supplier-location"><i class="fa-solid fa-location-dot"></i><span>{{ collect([$supplier->ciudad, $supplier->pais])->filter()->join(', ') ?: 'Sin ubicación' }}</span></div></td>
                    <td data-label="Dependencias"><div class="supplier-dependencies"><span><i class="fa-solid fa-shirt"></i><strong>{{ number_format($supplier->productos_count) }}</strong> productos</span><span><i class="fa-solid fa-file-invoice"></i><strong>{{ number_format($supplier->compras_count) }}</strong> compras</span></div></td>
                    <td data-label="Estado"><span class="supplier-state {{ $supplier->activo ? 'is-active' : 'is-inactive' }}"><i class="fa-solid fa-circle"></i>{{ $supplier->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td data-label="Acciones"><div class="supplier-actions">
                        @can('update', $supplier)<a href="{{ route('proveedores.edit', $supplier) }}" class="btn btn-sm btn-outline" title="Editar" aria-label="Editar {{ $supplier->nombre }}"><i class="fa-solid fa-pen"></i></a>@endcan
                        @can('update', $supplier)<form method="POST" action="{{ route('proveedores.estado', $supplier) }}" data-supplier-state data-name="{{ $supplier->nombre }}" data-products="{{ $supplier->productos_count }}" data-purchases="{{ $supplier->compras_count }}" data-next-state="{{ $supplier->activo ? 'inactivo' : 'activo' }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $supplier->activo ? 0 : 1 }}"><button type="submit" class="btn btn-sm {{ $supplier->activo ? 'btn-danger' : 'btn-primary' }}" title="{{ $supplier->activo ? 'Desactivar' : 'Activar' }}" aria-label="{{ $supplier->activo ? 'Desactivar' : 'Activar' }} {{ $supplier->nombre }}"><i class="fa-solid {{ $supplier->activo ? 'fa-ban' : 'fa-check' }}"></i></button></form>@endcan
                    </div></td>
                </tr>
                @empty
                <tr><td colspan="6" class="supplier-empty"><span><i class="fa-solid fa-truck-ramp-box"></i></span><strong>No se encontraron proveedores</strong><p>Modifica los filtros o registra un proveedor para comenzar.</p>@if(request()->hasAny(['buscar', 'estado', 'orden']))<a href="{{ route('proveedores.index') }}" class="btn btn-secondary">Limpiar filtros</a>@endif</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($proveedores->hasPages())<div class="supplier-pagination">{{ $proveedores->links() }}</div>@endif
</section>

<dialog id="supplierStateDialog" class="supplier-dialog" aria-labelledby="supplierDialogTitle">
    <div class="supplier-dialog-content"><span class="supplier-dialog-icon"><i class="fa-solid fa-triangle-exclamation"></i></span><h2 id="supplierDialogTitle">Confirmar cambio de estado</h2><p data-state-message></p><div class="supplier-dialog-dependencies"><span><i class="fa-solid fa-shirt"></i><b data-state-products>0</b> productos</span><span><i class="fa-solid fa-file-invoice"></i><b data-state-purchases>0</b> compras</span></div><div class="supplier-dialog-note" data-state-consequence></div><div class="supplier-dialog-actions"><button type="button" class="btn btn-secondary" data-state-cancel>Cancelar</button><button type="button" class="btn btn-danger" data-state-confirm>Confirmar cambio</button></div></div>
</dialog>
@endsection
