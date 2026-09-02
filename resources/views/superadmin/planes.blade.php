@extends('superadmin.layout')
@section('title', 'Planes')
@section('content')
<div class="pagetitle">Planes de suscripción</div><div class="pagesub">Crea y administra los planes disponibles para las tiendas</div>
@php $caractTexto=$editar ? implode("\n",$editar->caracteristicas ?? []) : ''; @endphp
<div class="planes-grid">
    <form class="pl-form surface" method="POST" action="{{ $editar ? route('admin.planes.update',$editar) : route('admin.planes.store') }}">@csrf
        <h3><i class="fa-solid {{ $editar ? 'fa-pen' : 'fa-plus' }}"></i>{{ $editar ? 'Editar plan' : 'Nuevo plan' }}</h3><div class="desc">Configura los límites y beneficios del plan.</div>
        <div class="fg"><label>Nombre</label><input type="text" name="nombre" value="{{ old('nombre',$editar->nombre ?? '') }}" placeholder="Ej. Pro" required></div>
        <div class="fg"><label>Precio mensual ($)</label><input type="number" step="0.01" min="0" name="precio" value="{{ old('precio',$editar->precio ?? '') }}" placeholder="99.00" required></div>
        <div class="g3">
            <div class="fg"><label>Productos</label><input type="number" name="max_productos" value="{{ old('max_productos',$editar->max_productos ?? -1) }}" required></div>
            <div class="fg"><label>Usuarios</label><input type="number" name="max_usuarios" value="{{ old('max_usuarios',$editar->max_usuarios ?? -1) }}" required></div>
            <div class="fg"><label>Ventas/mes</label><input type="number" name="max_ventas_mes" value="{{ old('max_ventas_mes',$editar->max_ventas_mes ?? -1) }}" required></div>
        </div>
        <div class="hint" style="margin:-6px 0 14px">Usa <b>-1</b> para indicar ilimitado.</div>
        <div class="fg"><label>Descripción corta</label><input type="text" name="descripcion" value="{{ old('descripcion',$editar->descripcion ?? '') }}" placeholder="Para tiendas en crecimiento"></div>
        <div class="fg"><label>Características (una por línea)</label><textarea name="caracteristicas" rows="4" placeholder="Soporte prioritario&#10;Reportes avanzados">{{ old('caracteristicas',$caractTexto) }}</textarea></div>
        <div class="chk"><input type="checkbox" name="activo" value="1" id="activo" {{ old('activo',$editar->activo ?? true) ? 'checked' : '' }}><label for="activo">Plan activo (visible en el registro)</label></div>
        <button class="btn-save"><i class="fa-solid fa-floppy-disk"></i> {{ $editar ? 'Guardar cambios' : 'Crear plan' }}</button>
        @if($editar)<a href="{{ route('admin.planes') }}" class="btn-cancel">Cancelar edición</a>@endif
    </form>
    <div class="pl-cards">
        @forelse($planes as $plan)
            <article class="pl-card surface {{ $plan->activo ? '' : 'off' }}">
                <div class="top"><div class="nm">{{ $plan->nombre }}</div><span class="{{ $plan->activo ? 'badge-on' : 'badge-off' }}">{{ $plan->activo ? 'Activo' : 'Inactivo' }}</span></div>
                <div class="pr">$ {{ number_format($plan->precio,0) }}<small>/mes</small></div><div class="ds">{{ $plan->descripcion }}</div>
                <ul>@foreach(($plan->caracteristicas ?? []) as $c)<li><i class="fa-solid fa-check"></i>{{ $c }}</li>@endforeach</ul>
                <div class="lim">Productos: <b>{{ $plan->max_productos == -1 ? 'Ilimitados' : $plan->max_productos }}</b><br>Usuarios: <b>{{ $plan->max_usuarios == -1 ? 'Ilimitados' : $plan->max_usuarios }}</b><br>Ventas/mes: <b>{{ $plan->max_ventas_mes == -1 ? 'Ilimitadas' : $plan->max_ventas_mes }}</b></div>
                <div class="pl-actions">
                    <a href="{{ route('admin.planes',['edit'=>$plan->id]) }}" class="mini m-edit"><i class="fa-solid fa-pen"></i>Editar</a>
                    <form method="POST" action="{{ route('admin.planes.toggle',$plan) }}">@csrf<button class="mini m-toggle"><i class="fa-solid fa-power-off"></i>{{ $plan->activo ? 'Desactivar' : 'Activar' }}</button></form>
                    <form method="POST" action="{{ route('admin.planes.destroy',$plan) }}" onsubmit="return confirm('¿Eliminar este plan?');">@csrf<button class="mini m-del" aria-label="Eliminar"><i class="fa-solid fa-trash"></i></button></form>
                </div>
                <div class="tiendas-tag"><i class="fa-solid fa-store"></i> {{ $plan->tiendas_count }} tienda(s)</div>
            </article>
        @empty<div class="surface">Aún no hay planes. Crea el primero con el formulario.</div>@endforelse
    </div>
</div>
@endsection
