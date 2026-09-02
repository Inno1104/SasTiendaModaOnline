@extends('layouts.app')
@section('title', 'Categorías')
@section('page-title', 'Categorías')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/categorias.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('js/categorias.js') }}" defer></script>
@endpush

@section('content')
<div class="page-header category-page-header">
    <div class="category-page-heading">
        <span class="category-page-icon"><i class="fa-solid fa-layer-group"></i></span>
        <div><span class="category-eyebrow">Organización del catálogo</span><h1>Categorías</h1><p>Clasifica tus productos y controla su disponibilidad en la tienda.</p></div>
    </div>
    <div class="category-header-summary" aria-label="Resumen del resultado"><strong>{{ number_format($categorias->total()) }}</strong><span>{{ $categorias->total() === 1 ? 'resultado' : 'resultados' }}</span></div>
</div>

@if($errors->any())
<div class="alert alert-danger category-validation-summary" role="alert">
    <i class="fa-solid fa-circle-exclamation"></i><div><strong>Revisa la información ingresada.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
</div>
@endif

@php($canManageCategories = auth()->user()->can('create', App\Models\Categoria::class))
<div class="category-layout {{ $canManageCategories ? '' : 'category-layout-readonly' }}">
    @if($canManageCategories)
    <aside class="card category-form-card">
        <div class="category-card-heading">
            <span><i class="fa-solid {{ $editar ? 'fa-pen' : 'fa-plus' }}"></i></span>
            <div><h2>{{ $editar ? 'Editar categoría' : 'Nueva categoría' }}</h2><p>{{ $editar ? 'Actualiza su identidad y disponibilidad.' : 'Crea una agrupación para tus productos.' }}</p></div>
        </div>
        <form method="POST" action="{{ $editar ? route('categorias.update', $editar) : route('categorias.store') }}" class="category-form">
            @csrf
            <label class="form-group">
                <span class="form-label">Nombre <b aria-hidden="true">*</b></span>
                <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" maxlength="100" value="{{ old('nombre', $editar->nombre ?? '') }}" placeholder="Ej. Camisas" autocomplete="off" required>
                @error('nombre')<small class="category-field-error">{{ $message }}</small>@enderror
            </label>
            <label class="form-group">
                <span class="form-label">Género <b aria-hidden="true">*</b></span>
                <select name="genero" class="form-control @error('genero') is-invalid @enderror" required>
                    @foreach(['damas'=>'Damas','caballeros'=>'Caballeros','ninos'=>'Niños','unisex'=>'Unisex'] as $value=>$label)<option value="{{ $value }}" @selected(old('genero', $editar->genero ?? 'unisex') === $value)>{{ $label }}</option>@endforeach
                </select>
                @error('genero')<small class="category-field-error">{{ $message }}</small>@enderror
            </label>
            <label class="form-group">
                <span class="form-label">Descripción</span>
                <textarea name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" rows="3" maxlength="255" placeholder="Describe brevemente esta categoría">{{ old('descripcion', $editar->descripcion ?? '') }}</textarea>
                <small class="category-field-hint">Opcional · máximo 255 caracteres</small>
                @error('descripcion')<small class="category-field-error">{{ $message }}</small>@enderror
            </label>
            <label class="category-status-control">
                <input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" @checked(old('activo', $editar->activo ?? true))>
                <span class="category-switch" aria-hidden="true"></span><span><strong>Categoría activa</strong><small>Disponible para productos y filtros.</small></span>
            </label>
            @error('activo')<small class="category-field-error category-status-error">{{ $message }}</small>@enderror
            <div class="category-form-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> {{ $editar ? 'Guardar cambios' : 'Crear categoría' }}</button>@if($editar)<a href="{{ route('categorias.index', request()->except(['edit', 'page'])) }}" class="btn btn-secondary">Cancelar</a>@endif</div>
        </form>
    </aside>
    @endif

    <section class="category-content">
        <div class="card category-filter-card">
            <form method="GET" class="category-filters">
                <label class="category-filter-search"><span class="form-label">Buscar</span><span class="category-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Nombre de la categoría..." maxlength="100"></span></label>
                <label><span class="form-label">Estado</span><select name="estado" class="form-control"><option value="todos">Todos</option><option value="activo" @selected(request('estado') === 'activo')>Activas</option><option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option></select></label>
                <label><span class="form-label">Género</span><select name="genero" class="form-control"><option value="todos">Todos</option>@foreach(['damas'=>'Damas','caballeros'=>'Caballeros','ninos'=>'Niños','unisex'=>'Unisex'] as $value=>$label)<option value="{{ $value }}" @selected(request('genero') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="form-label">Ordenar</span><select name="orden" class="form-control"><option value="nombre_asc" @selected(request('orden', 'nombre_asc') === 'nombre_asc')>Nombre A–Z</option><option value="nombre_desc" @selected(request('orden') === 'nombre_desc')>Nombre Z–A</option><option value="recientes" @selected(request('orden') === 'recientes')>Más recientes</option><option value="antiguas" @selected(request('orden') === 'antiguas')>Más antiguas</option></select></label>
                <div class="category-filter-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Aplicar</button><a href="{{ route('categorias.index') }}" class="btn btn-secondary">Limpiar</a></div>
            </form>
        </div>

        <div class="card category-list-card">
            <div class="category-list-heading"><div><h2>Listado de categorías</h2><p>Los conteos incluyen dependencias activas e inactivas.</p></div><span>Página {{ $categorias->currentPage() }} de {{ $categorias->lastPage() }}</span></div>
            <div class="table-wrap category-table-wrap">
                <table class="category-table">
                    <thead><tr><th>Categoría</th><th>Género</th><th>Productos</th><th>Subcategorías</th><th>Estado</th><th class="category-actions-heading">Acciones</th></tr></thead>
                    <tbody>
                        @forelse($categorias as $category)
                        <tr>
                            <td data-label="Categoría"><div class="category-identity"><span>{{ mb_strtoupper(mb_substr($category->nombre, 0, 1)) }}</span><div><strong>{{ $category->nombre }}</strong><small>{{ $category->descripcion ?: 'Sin descripción' }}</small></div></div></td>
                            <td data-label="Género"><span class="category-gender"><i class="fa-solid fa-shirt"></i> {{ ['damas'=>'Damas','caballeros'=>'Caballeros','ninos'=>'Niños','unisex'=>'Unisex'][$category->genero] ?? ucfirst($category->genero) }}</span></td>
                            <td data-label="Productos"><span class="category-count"><strong>{{ number_format($category->productos_count) }}</strong><small>productos</small></span></td>
                            <td data-label="Subcategorías"><span class="category-count"><strong>{{ number_format($category->subcategorias_count) }}</strong><small>subcategorías</small></span></td>
                            <td data-label="Estado"><span class="category-state {{ $category->activo ? 'is-active' : 'is-inactive' }}"><i class="fa-solid fa-circle"></i>{{ $category->activo ? 'Activa' : 'Inactiva' }}</span></td>
                            <td data-label="Acciones"><div class="category-actions">
                                @can('update', $category)<a href="{{ route('categorias.index', array_merge(request()->except('edit'), ['edit'=>$category->id])) }}" class="btn btn-sm btn-outline" aria-label="Editar {{ $category->nombre }}" title="Editar"><i class="fa-solid fa-pen"></i></a>@endcan
                                @can('delete', $category)<form method="POST" action="{{ route('categorias.destroy', $category) }}" data-category-delete data-name="{{ $category->nombre }}" data-products="{{ $category->productos_count }}" data-subcategories="{{ $category->subcategorias_count }}">@csrf<button type="submit" class="btn btn-sm btn-danger" aria-label="Eliminar {{ $category->nombre }}" title="Eliminar"><i class="fa-solid fa-trash"></i></button></form>@endcan
                            </div></td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="category-empty-result"><span><i class="fa-solid fa-folder-open"></i></span><strong>No se encontraron categorías</strong><p>Modifica los filtros o crea una nueva categoría para organizar tu catálogo.</p>@if(request()->hasAny(['buscar','estado','genero','orden']))<a href="{{ route('categorias.index') }}" class="btn btn-secondary">Limpiar filtros</a>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($categorias->hasPages())<div class="category-pagination">{{ $categorias->links() }}</div>@endif
        </div>
    </section>
</div>

<dialog id="categoryDeleteDialog" class="category-dialog">
    <div class="category-dialog-content">
        <span class="category-dialog-icon"><i class="fa-solid fa-trash-can"></i></span><h2>Eliminar categoría</h2><p data-delete-message>Confirma que deseas eliminar esta categoría.</p>
        <div class="category-dialog-warning" data-delete-warning hidden><i class="fa-solid fa-triangle-exclamation"></i><span></span></div>
        <div class="category-dialog-actions"><button type="button" class="btn btn-secondary" data-delete-cancel>Cancelar</button><button type="button" class="btn btn-danger" data-delete-confirm>Eliminar</button></div>
    </div>
</dialog>
@endsection
