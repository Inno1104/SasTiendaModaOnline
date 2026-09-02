@extends('superadmin.layout')
@section('title', 'Mi Perfil')
@section('content')
<div class="pagetitle">Mi Perfil</div><div class="pagesub">Administra tu cuenta de super administrador</div>
<div class="perfil-grid">
    <section class="pf-card surface">
        <div class="pf-hero"><div class="av">{{ strtoupper(substr($user->name,0,2)) }}</div><div class="nm">{{ $user->name }}</div><div class="rl"><i class="fa-solid fa-shield-halved"></i> Super Administrador</div></div>
        <div class="pf-body">
            <div class="pf-row"><i class="fa-solid fa-envelope"></i><div><div class="k">Correo</div><div class="v">{{ $user->email }}</div></div></div>
            <div class="pf-row"><i class="fa-solid fa-phone"></i><div><div class="k">Teléfono</div><div class="v">{{ $user->telefono ?: 'No registrado' }}</div></div></div>
            <div class="pf-row"><i class="fa-solid fa-calendar-check"></i><div><div class="k">Miembro desde</div><div class="v">{{ $user->created_at->locale('es')->isoFormat('D [de] MMMM YYYY') }}</div></div></div>
        </div>
        <div class="pf-stats">@foreach(['tiendas'=>'Tiendas','activas'=>'Activas','usuarios'=>'Usuarios','planes'=>'Planes'] as $key=>$label)<div class="pf-stat"><div class="n">{{ $stats[$key] }}</div><div class="l">{{ $label }}</div></div>@endforeach</div>
    </section>
    <div>
        <section class="form-panel surface"><h3><i class="fa-solid fa-id-card"></i>Datos personales</h3><div class="desc">Actualiza tu nombre, correo y teléfono de contacto.</div>
            <form method="POST" action="{{ route('admin.perfil.update') }}">@csrf
                <div class="fg"><label>Nombre completo</label><input type="text" name="name" value="{{ old('name',$user->name) }}" required></div>
                <div class="grid2"><div class="fg"><label>Correo electrónico</label><input type="email" name="email" value="{{ old('email',$user->email) }}" required></div><div class="fg"><label>Teléfono</label><input type="text" name="telefono" value="{{ old('telefono',$user->telefono) }}" placeholder="Opcional"></div></div>
                <button class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
            </form>
        </section>
        <section class="form-panel surface"><h3><i class="fa-solid fa-lock"></i>Cambiar contraseña</h3><div class="desc">Te recomendamos usar una contraseña segura y única.</div>
            <form method="POST" action="{{ route('admin.perfil.password') }}">@csrf
                <div class="fg"><label>Contraseña actual</label><input type="password" name="password_actual" required></div>
                <div class="grid2"><div class="fg"><label>Nueva contraseña</label><input type="password" name="password" required></div><div class="fg"><label>Confirmar nueva contraseña</label><input type="password" name="password_confirmation" required></div></div>
                <button class="btn-save"><i class="fa-solid fa-key"></i> Actualizar contraseña</button>
            </form>
        </section>
    </div>
</div>
@endsection
