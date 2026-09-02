@extends('layouts.app')
@section('title','Clientes')
@section('page-title','Clientes')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/clientes.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/clientes.js') }}" defer></script>
@endpush

@section('content')
<div class="page-header customer-page-header">
    <div class="customer-page-heading"><span class="customer-page-icon"><i class="fa-solid fa-users"></i></span><div><span class="customer-eyebrow">Relaciones comerciales</span><h1>Clientes</h1><p>Consulta, segmenta y administra la cartera de tu tienda.</p></div></div>
    <div class="customer-header-actions"><a href="{{ route('clientes.export', request()->query()) }}" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Exportar</a><a href="{{ route('clientes.create') }}" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Nuevo Cliente</a></div>
</div>

<div class="card customer-filter-card">
    <form method="GET" class="customer-filters">
        <label class="customer-filter-search"><span class="form-label">Buscar cliente</span><input type="search" name="buscar" class="form-control" maxlength="100" value="{{ $filters['buscar'] ?? '' }}" placeholder="Código, nombre, documento, correo o teléfono..."></label>
        <label><span class="form-label">Estado</span><select name="estado" class="form-control"><option value="todos">Todos</option><option value="activo" @selected(($filters['estado'] ?? '') === 'activo')>Activos</option><option value="inactivo" @selected(($filters['estado'] ?? '') === 'inactivo')>Inactivos</option></select></label>
        <label><span class="form-label">Ciudad</span><select name="ciudad" class="form-control"><option value="">Todas</option>@foreach($ciudades as $ciudad)<option value="{{ $ciudad }}" @selected(($filters['ciudad'] ?? '') === $ciudad)>{{ $ciudad }}</option>@endforeach</select></label>
        <label><span class="form-label">Actividad</span><select name="actividad" class="form-control"><option value="todos">Todos</option><option value="con_compras" @selected(($filters['actividad'] ?? '') === 'con_compras')>Con compras</option><option value="sin_compras" @selected(($filters['actividad'] ?? '') === 'sin_compras')>Sin compras</option></select></label>
        <label><span class="form-label">Registrado desde</span><input type="date" name="registrado_desde" class="form-control" value="{{ $filters['registrado_desde'] ?? '' }}"></label>
        <label><span class="form-label">Registrado hasta</span><input type="date" name="registrado_hasta" class="form-control" value="{{ $filters['registrado_hasta'] ?? '' }}"></label>
        <label><span class="form-label">Última compra desde</span><input type="date" name="ultima_compra_desde" class="form-control" value="{{ $filters['ultima_compra_desde'] ?? '' }}"></label>
        <label><span class="form-label">Última compra hasta</span><input type="date" name="ultima_compra_hasta" class="form-control" value="{{ $filters['ultima_compra_hasta'] ?? '' }}"></label>
        <label><span class="form-label">Ordenar</span><select name="orden" class="form-control"><option value="recientes">Más recientes</option><option value="antiguos" @selected(($filters['orden'] ?? '') === 'antiguos')>Más antiguos</option><option value="nombre_asc" @selected(($filters['orden'] ?? '') === 'nombre_asc')>Nombre A–Z</option><option value="nombre_desc" @selected(($filters['orden'] ?? '') === 'nombre_desc')>Nombre Z–A</option><option value="compras_desc" @selected(($filters['orden'] ?? '') === 'compras_desc')>Más compras</option><option value="gasto_desc" @selected(($filters['orden'] ?? '') === 'gasto_desc')>Mayor gasto</option></select></label>
        <div class="customer-filter-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Aplicar</button><a href="{{ route('clientes.index') }}" class="btn btn-secondary">Limpiar</a></div>
    </form>
</div>

<div class="card">
    <div class="customer-results-summary"><strong>{{ number_format($clientes->total()) }}</strong> {{ $clientes->total() === 1 ? 'cliente encontrado' : 'clientes encontrados' }}</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th><th>Cliente</th><th>DNI</th><th>Contacto</th><th>Ciudad</th><th>Actividad</th><th>Puntos</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $c)
                <tr>
                    <td><code class="customer-code">{{ $c->codigo }}</code></td>
                    <td>
                        <div class="customer-identity">
                            <div class="customer-avatar customer-avatar-small">
                                {{ strtoupper(substr($c->nombre,0,1).substr($c->apellido,0,1)) }}
                            </div>
                            <div>
                                <strong>{{ $c->nombre }} {{ $c->apellido }}</strong>
                                <small>{{ $c->email ?? 'Sin correo' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>{{ $c->dni ?? '—' }}</td>
                    <td>{{ $c->telefono ?? '—' }}</td>
                    <td>{{ $c->ciudad ?? '—' }}</td>
                    <td><div class="customer-activity"><strong>{{ number_format($c->compras_completadas) }} compras</strong><span>{{ $simboloMoneda }} {{ number_format((float) ($c->total_gastado ?? 0), 2) }}</span>@if($c->ultima_compra)<small>{{ \Carbon\Carbon::parse($c->ultima_compra)->format('d/m/Y') }}</small>@endif</div></td>
                    <td>
                        <span class="customer-points">
                            <i class="fa-solid fa-star"></i> {{ number_format($c->puntos,0) }}
                        </span>
                    </td>
                    <td><span class="badge {{ $c->activo ? 'badge-success' : 'badge-danger' }}">{{ $c->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td>
                        <div class="customer-actions">
                            <a href="{{ route('clientes.show',$c) }}" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i></a>
                            <a href="{{ route('clientes.edit',$c) }}" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                            @can('changeStatus', $c)
                            <button type="button" class="btn btn-sm btn-outline customer-status-button {{ $c->activo ? 'is-deactivate' : '' }}" data-customer-status data-url="{{ route('clientes.estado', $c) }}" data-name="{{ $c->nombreCompleto() }}" data-active="{{ $c->activo ? '0' : '1' }}" aria-label="{{ $c->activo ? 'Desactivar' : 'Reactivar' }} a {{ $c->nombreCompleto() }}"><i class="fa-solid {{ $c->activo ? 'fa-user-slash' : 'fa-user-check' }}"></i></button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="customer-empty-result"><i class="fa-solid fa-users-slash"></i><strong>No se encontraron clientes</strong><span>Modifica o limpia los filtros para ampliar los resultados.</span></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $clientes->withQueryString()->links() }}
</div>

<dialog id="customerStatusDialog" class="customer-status-dialog">
    <form method="POST" class="customer-status-content">
        @csrf
        @method('PATCH')
        <input type="hidden" name="activo" value="">
        <h2 data-status-title>Cambiar estado</h2>
        <p data-status-message>Confirma el cambio de estado del cliente.</p>
        <div class="form-group">
            <label for="customerStatusReason" class="form-label">Motivo del cambio *</label>
            <textarea id="customerStatusReason" name="motivo" class="form-control" rows="3" minlength="5" maxlength="500" placeholder="Describe el motivo del cambio..." required></textarea>
        </div>
        <div class="customer-status-actions">
            <button type="button" class="btn btn-secondary" data-status-cancel>Cancelar</button>
            <button type="submit" class="btn btn-primary" data-status-submit>Confirmar</button>
        </div>
    </form>
</dialog>
@endsection
