@extends('layouts.app')
@section('title','Productos')
@section('page-title','Productos')

@section('content')
<div class="page-header">
    <div>
        <h1>Productos</h1>
        <div class="breadcrumb"><a href="{{ route('dashboard') }}">Inicio</a><span class="breadcrumb-sep">›</span>Productos</div>
    </div>
    @can('create', App\Models\Producto::class)
        <a href="{{ route('productos.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo Producto</a>
    @endcan
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:20px;">
    <form method="GET" class="form-row" style="align-items:flex-end;">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Buscar</label>
            <input type="search" name="buscar" class="form-control" value="{{ request('buscar') }}" maxlength="100" placeholder="Nombre, código o código de barras...">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Categoría</label>
            <select name="categoria_id" class="form-control">
                <option value="">Todas</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat->id }}" {{ request('categoria_id') == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Género</label>
            <select name="genero" class="form-control">
                <option value="">Todos</option>
                <option value="damas" {{ request('genero')=='damas'?'selected':'' }}>Damas</option>
                <option value="caballeros" {{ request('genero')=='caballeros'?'selected':'' }}>Caballeros</option>
                <option value="ninos" {{ request('genero')=='ninos'?'selected':'' }}>Niños</option>
                <option value="unisex" {{ request('genero')=='unisex'?'selected':'' }}>Unisex</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-control">
                <option value="">Todos</option>
                <option value="casual" {{ request('tipo')=='casual'?'selected':'' }}>Casual</option>
                <option value="deportivo" {{ request('tipo')=='deportivo'?'selected':'' }}>Deportivo</option>
                <option value="formal" {{ request('tipo')=='formal'?'selected':'' }}>Formal</option>
                <option value="otro" {{ request('tipo')=='otro'?'selected':'' }}>Otro</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
            <a href="{{ route('productos.index') }}" class="btn btn-secondary" style="margin-left:6px;">Limpiar</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Resultados</div>
        <span class="badge badge-info">{{ $productos->total() }} {{ $productos->total() === 1 ? 'producto' : 'productos' }}</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Género</th>
                    <th>Tipo</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productos as $p)
                <tr>
                    <td><code style="background:#f3f4f6;padding:3px 8px;border-radius:5px;font-size:12px;">{{ $p->codigo }}</code></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if($p->imagen)
                                <img src="{{ asset('storage/'.$p->imagen) }}" alt="Imagen de {{ $p->nombre }}" loading="lazy" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
                            @else
                                <div style="width:40px;height:40px;border-radius:8px;background:linear-gradient(135deg,#fce7f3,#fbcfe8);display:flex;align-items:center;justify-content:center;color:#be185d;font-size:16px;">
                                    <i class="fa-solid fa-shirt"></i>
                                </div>
                            @endif
                            <div>
                                <div style="font-weight:600;font-size:13.5px;">{{ $p->nombre }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $p->marca->nombre ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $p->categoria->nombre ?? '—' }}</td>
                    <td>
                        <span class="badge {{ ['damas'=>'badge-pink','caballeros'=>'badge-info','ninos'=>'badge-warning','unisex'=>'badge-success'][$p->genero] ?? 'badge-info' }}">
                            {{ ucfirst($p->genero) }}
                        </span>
                    </td>
                    <td>{{ ucfirst($p->tipo) }}</td>
                    <td><strong>{{ $simboloMoneda }} {{ number_format($p->precio_venta,2) }}</strong></td>
                    <td>
                        @php
                            $stock = (int) ($p->stock_total ?? 0);
                            $hasLowStock = (int) ($p->variantes_stock_bajo ?? 0) > 0;
                        @endphp
                        <span class="badge {{ $stock <= 0 ? 'badge-danger' : ($hasLowStock ? 'badge-warning' : 'badge-success') }}">
                            {{ $stock }} uds.
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $p->activo ? 'badge-success' : 'badge-danger' }}">{{ $p->activo ? 'Activo' : 'Inactivo' }}</span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="{{ route('productos.show',$p) }}" class="btn btn-sm btn-secondary" title="Ver"><i class="fa-solid fa-eye"></i></a>
                            @can('update', $p)
                                <a href="{{ route('productos.edit',$p) }}" class="btn btn-sm btn-outline" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            @endcan
                            @if($p->activo)
                                @can('delete', $p)
                                    <form method="POST" action="{{ route('productos.destroy',$p) }}" style="display:inline;" onsubmit="return confirm('¿Desactivar este producto?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Desactivar"><i class="fa-solid fa-ban"></i></button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">No se encontraron productos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">
        {{ $productos->withQueryString()->links('pagination::simple-bootstrap-5') }}
    </div>
</div>
@endsection
