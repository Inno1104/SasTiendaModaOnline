<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Super Admin') — SaaS Tienda Moda</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/superadmin.css') }}" rel="stylesheet">
</head>
<body>
    @php $u = auth()->user(); @endphp
    <div class="sa-overlay" id="saOverlay"></div>
    <header class="sa-header">
        <button class="menu-toggle" id="saToggle" type="button" aria-label="Abrir menú"><i class="fa-solid fa-bars"></i></button>
        <a class="sa-brand" href="{{ route('admin.index') }}"><i class="fa-solid fa-shield-halved"></i>Control SaaS</a>
        <nav class="sa-nav" id="saNav">
            <a href="{{ route('admin.index') }}" class="{{ request()->routeIs('admin.index') ? 'active' : '' }}"><i class="fa-solid fa-house"></i>Resumen</a>
            <a href="{{ route('admin.tiendas') }}" class="{{ request()->routeIs('admin.tiendas') ? 'active' : '' }}"><i class="fa-solid fa-store"></i>Tiendas</a>
            <a href="{{ route('admin.planes') }}" class="{{ request()->routeIs('admin.planes') ? 'active' : '' }}"><i class="fa-solid fa-layer-group"></i>Planes</a>
            <a href="{{ route('admin.perfil') }}" class="{{ request()->routeIs('admin.perfil') ? 'active' : '' }}"><i class="fa-solid fa-user-gear"></i>Mi Perfil</a>
        </nav>
        <a class="sa-user" href="{{ route('admin.perfil') }}">
            <div class="sa-user-meta"><div class="sa-user-name">{{ $u->name }}</div><div class="sa-user-role">Super Administrador</div></div>
            <div class="sa-avatar">{{ strtoupper(substr($u->name,0,2)) }}</div>
        </a>
        <form class="logout-form" method="POST" action="{{ route('logout') }}">@csrf<button class="logout-btn" title="Cerrar sesión" aria-label="Cerrar sesión"><i class="fa-solid fa-right-from-bracket"></i></button></form>
    </header>
    <main class="main">
        <div class="content-pad">
            @if(session('success'))<div class="alert-ok"><i class="fa-solid fa-check-circle"></i> {{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert-ok alert-error"><i class="fa-solid fa-circle-exclamation"></i> @foreach($errors->all() as $e) {{ $e }} @endforeach</div>@endif
            @yield('content')
        </div>
        <footer class="sa-footer"><i class="fa-solid fa-shield-halved"></i><strong>Control SaaS</strong> © {{ date('Y') }} &nbsp; | &nbsp; Todos los derechos reservados.</footer>
    </main>
    <dialog class="sa-confirm" data-sa-confirm><div><span><i class="fa-solid fa-circle-question"></i></span><h2>Confirmar acción</h2><p data-sa-confirm-message></p><footer><button type="button" data-sa-cancel>Cancelar</button><button type="button" data-sa-accept>Confirmar</button></footer></div></dialog>
    <script>
        (()=>{const n=document.getElementById('saNav'),o=document.getElementById('saOverlay'),t=document.getElementById('saToggle');const close=()=>{n.classList.remove('open');o.classList.remove('show')};t.addEventListener('click',()=>{n.classList.toggle('open');o.classList.toggle('show')});o.addEventListener('click',close);n.querySelectorAll('a').forEach(a=>a.addEventListener('click',close));addEventListener('resize',()=>innerWidth>1000&&close())})();
        (()=>{const d=document.querySelector('[data-sa-confirm]'),m=d.querySelector('[data-sa-confirm-message]'),yes=d.querySelector('[data-sa-accept]'),no=d.querySelector('[data-sa-cancel]');let pending=null;document.addEventListener('submit',e=>{const f=e.target.closest?.('form[data-confirm-message]');if(!f||f.dataset.approved==='true'){if(f)delete f.dataset.approved;return}e.preventDefault();e.stopImmediatePropagation();pending=f;m.textContent=f.dataset.confirmMessage;d.showModal()},true);no.addEventListener('click',()=>{pending=null;d.close()});yes.addEventListener('click',()=>{const f=pending;pending=null;d.close();if(f){f.dataset.approved='true';f.requestSubmit()}});d.addEventListener('cancel',()=>pending=null)})();
    </script>
</body>
</html>
