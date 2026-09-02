@extends('layouts.app')
@section('title', 'Usuarios')
@section('page-title', 'Usuarios')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/usuarios.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('js/usuarios.js') }}" defer></script>
@endpush

@section('content')
<main class="user-page">
    <header class="user-page-header">
        <div class="user-heading"><span class="user-heading-icon" aria-hidden="true"><i class="fa-solid fa-users-gear"></i></span><div><span class="user-eyebrow">Equipo y accesos</span><h1>Usuarios</h1><p>Administra los perfiles, roles y accesos de tu tienda.</p></div></div>
        @if($tienda?->plan)<div class="user-plan-usage" aria-label="Uso del plan de usuarios"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i><span><strong>{{ $usuariosRegistrados }} / {{ (int) $tienda->plan->max_usuarios === -1 ? '∞' : $tienda->plan->max_usuarios }}</strong><small>Plan {{ $tienda->plan->nombre }}</small></span></div>@endif
    </header>

    @if($errors->any())
    <div class="alert alert-danger user-validation-summary" role="alert" aria-live="assertive"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div><strong>No se pudo completar la operación.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <div class="user-layout">
        <section class="card user-form-card" aria-labelledby="userFormTitle">
            <div class="user-card-heading"><div><span aria-hidden="true"><i class="fa-solid {{ $editar ? 'fa-user-pen' : 'fa-user-plus' }}"></i></span><h2 id="userFormTitle">{{ $editar ? 'Editar usuario' : 'Nuevo usuario' }}</h2></div></div>
            @include('usuarios._form')
        </section>

        <section class="card user-list-card" aria-labelledby="userListTitle">
            <div class="user-list-heading"><div><h2 id="userListTitle">Equipo registrado</h2><p>{{ number_format($usuarios->total()) }} {{ $usuarios->total() === 1 ? 'resultado' : 'resultados' }}</p></div><span>Página {{ $usuarios->currentPage() }} de {{ $usuarios->lastPage() }}</span></div>

            <form method="GET" class="user-filters" aria-label="Filtros de usuarios">
                <label class="user-filter-field"><span>Buscar</span><input type="search" name="buscar" class="form-control" value="{{ request('buscar') }}" maxlength="100" placeholder="Nombre, correo o teléfono"></label>
                <label class="user-filter-field"><span>Estado</span><select name="estado" class="form-control"><option value="todos">Todos</option><option value="activo" @selected(request('estado') === 'activo')>Activos</option><option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option></select></label>
                <label class="user-filter-field"><span>Rol</span><select name="rol_id" class="form-control"><option value="">Todos</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string) request('rol_id') === (string) $role->id)>{{ $role->nombre }}</option>@endforeach</select></label>
                <label class="user-filter-field"><span>Orden</span><select name="orden" class="form-control"><option value="nombre_asc" @selected(request('orden', 'nombre_asc') === 'nombre_asc')>Nombre A–Z</option><option value="nombre_desc" @selected(request('orden') === 'nombre_desc')>Nombre Z–A</option><option value="recientes" @selected(request('orden') === 'recientes')>Más recientes</option><option value="antiguos" @selected(request('orden') === 'antiguos')>Más antiguos</option></select></label>
                <label class="user-filter-field"><span>Por página</span><select name="por_pagina" class="form-control">@foreach([10, 15, 25, 50] as $size)<option value="{{ $size }}" @selected((int) request('por_pagina', 15) === $size)>{{ $size }}</option>@endforeach</select></label>
                <div class="user-filter-actions"><button class="btn btn-primary" title="Aplicar filtros"><i class="fa-solid fa-filter" aria-hidden="true"></i><span class="sr-only">Aplicar filtros</span></button><a href="{{ route('usuarios.index') }}" class="btn btn-secondary" title="Limpiar filtros"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span class="sr-only">Limpiar filtros</span></a></div>
            </form>

            <div class="table-wrap">
                <table class="user-table">
                    <thead><tr><th scope="col">Usuario</th><th scope="col">Rol</th><th scope="col">Teléfono</th><th scope="col">Estado</th><th scope="col" class="text-right">Acciones</th></tr></thead>
                    <tbody>
                        @forelse($usuarios as $user)
                        <tr>
                            <td data-label="Usuario"><div class="user-identity"><span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}</span><div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div></div></td>
                            <td data-label="Rol"><span class="badge badge-pink">{{ $user->rol?->nombre ?? 'Sin rol' }}</span></td>
                            <td data-label="Teléfono">{{ $user->telefono ?: 'Sin teléfono' }}</td>
                            <td data-label="Estado"><span class="user-state {{ $user->activo ? 'is-active' : 'is-inactive' }}">{{ $user->activo ? 'Activo' : 'Inactivo' }}</span></td>
                            <td data-label="Acciones"><div class="user-actions">
                                @can('update', $user)<a href="{{ route('usuarios.index', array_filter(['edit' => $user->id, 'buscar' => request('buscar'), 'estado' => request('estado'), 'rol_id' => request('rol_id'), 'orden' => request('orden'), 'por_pagina' => request('por_pagina'), 'page' => request('page')])) }}" class="btn btn-sm btn-outline" title="Editar" aria-label="Editar a {{ $user->name }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>@endcan
                                @can('changeStatus', $user)<form method="POST" action="{{ route('usuarios.toggle', $user) }}" data-user-status-form data-user-name="{{ $user->name }}" data-next-state="{{ $user->activo ? 'inactivo' : 'activo' }}">@csrf<input type="hidden" name="activo" value="{{ $user->activo ? 0 : 1 }}"><input type="hidden" name="motivo" value=""><button type="submit" class="btn btn-sm {{ $user->activo ? 'btn-danger' : 'btn-primary' }}" title="{{ $user->activo ? 'Desactivar' : 'Activar' }}" aria-label="{{ $user->activo ? 'Desactivar' : 'Activar' }} a {{ $user->name }}"><i class="fa-solid {{ $user->activo ? 'fa-ban' : 'fa-check' }}" aria-hidden="true"></i></button></form>@endcan
                            </div></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="user-empty"><i class="fa-solid fa-users-slash" aria-hidden="true"></i><strong>No se encontraron usuarios</strong><p>Modifica los filtros o registra un nuevo integrante.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($usuarios->hasPages())<nav class="user-pagination" aria-label="Paginación de usuarios">{{ $usuarios->links() }}</nav>@endif
        </section>
    </div>
</main>

<dialog class="user-dialog" data-user-status-dialog aria-labelledby="userStatusDialogTitle" aria-describedby="userStatusDialogMessage">
    <div class="user-dialog-content">
        <span class="user-dialog-icon" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span><h2 id="userStatusDialogTitle" data-status-title>Confirmar cambio</h2><p id="userStatusDialogMessage" data-status-message></p>
        <label class="user-form-field" for="user-status-reason"><span>Motivo del cambio <span aria-hidden="true">*</span></span><textarea id="user-status-reason" class="form-control" rows="3" minlength="5" maxlength="500" required aria-describedby="user-status-help user-status-error" data-status-reason></textarea><small id="user-status-help" class="user-field-help">El motivo quedará registrado en la auditoría.</small><small id="user-status-error" class="user-field-error" role="alert" data-status-error hidden>Escribe un motivo de al menos 5 caracteres.</small></label>
        <div class="user-dialog-actions"><button type="button" class="btn btn-secondary" data-status-cancel>Cancelar</button><button type="button" class="btn btn-danger" data-status-confirm>Confirmar cambio</button></div>
    </div>
</dialog>
@endsection
