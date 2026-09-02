<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — SaaS Tienda Moda</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1e2139;
            --sidebar-hover: #2a2d4a;
            --sidebar-active: #e8398c;
            --sidebar-width: 260px;
            --topbar-height: 64px;
            --accent: #e8398c;
            --accent-dark: #c2185b;
            --bg: #f0f2f8;
            --card-bg: #ffffff;
            --text-dark: #1e2139;
            --text-muted: #8a8ea8;
            --border: #e4e6f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        /* ─── SIDEBAR ─── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s ease;
            overflow: hidden;
        }
        /* Botón hamburguesa (oculto en escritorio) */
        .menu-toggle {
            display: none;
            width: 40px; height: 40px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            border-radius: 10px;
            color: var(--text-dark);
            font-size: 18px;
            cursor: pointer;
            align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .menu-toggle:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
        /* Fondo oscuro al abrir menú en móvil */
        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 99;
        }
        .sidebar-overlay.show { display: block; }
        /* Navegación horizontal de escritorio */
        .desktop-header {
            display: flex; align-items: center; gap: 24px;
            height: 82px; padding: 0 24px;
            position: sticky; top: 0; z-index: 200;
            color: #fff;
            background: radial-gradient(circle at 85% 0, rgba(211,0,78,.35), transparent 28%),
                        linear-gradient(105deg,#210014,#500025 58%,#310019);
            border-top: 2px solid #c90050;
            box-shadow: 0 7px 22px rgba(70,0,35,.18);
        }
        .desktop-brand { display:flex; align-items:center; gap:11px; min-width:210px; color:#fff; text-decoration:none; }
        .desktop-brand .logo-icon { width:46px; height:46px; overflow:hidden; }
        .store-brand-logo{width:100%;height:100%;object-fit:contain;border-radius:inherit;background:#fff;padding:3px}
        .desktop-brand strong { display:block; font-size:15px; }
        .desktop-brand small { display:block; margin-top:2px; color:rgba(255,255,255,.6); font-size:9px; }
        .desktop-nav { display:flex; align-items:center; gap:7px; }
        .desktop-nav-group { position:relative; }
        .desktop-nav-link {
            height:46px; display:flex; align-items:center; gap:9px; padding:0 17px;
            border:0; border-radius:9px; color:rgba(255,255,255,.92); background:transparent;
            font:600 12px 'Poppins',sans-serif; text-decoration:none; cursor:pointer; white-space:nowrap;
        }
        .desktop-nav-link:hover,.desktop-nav-link.active { color:#fff; background:linear-gradient(110deg,#87003d,#ae0051); }
        .desktop-nav-link .fa-chevron-down { margin-left:3px; font-size:8px; }
        .desktop-dropdown {
            min-width:205px; display:none; position:absolute; top:calc(100% + 8px); left:0;
            padding:8px; border:1px solid #eadde4; border-radius:11px; background:#fff;
            box-shadow:0 14px 35px rgba(35,0,20,.18);
        }
        .desktop-nav-group:hover .desktop-dropdown,.desktop-nav-group:focus-within .desktop-dropdown { display:block; }
        .desktop-dropdown a { display:flex; align-items:center; gap:10px; padding:10px 11px; border-radius:7px; color:#29263a; text-decoration:none; font-size:11px; }
        .desktop-dropdown a:hover,.desktop-dropdown a.active { color:#c90050; background:#fff0f6; }
        .desktop-tools { margin-left:auto; display:flex; align-items:center; gap:14px; }
        .desktop-search { position:relative; }
        .desktop-search i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#d5afc2; font-size:12px; }
        .desktop-search input { width:210px; height:42px; padding:0 14px 0 38px; border:1px solid rgba(255,255,255,.16); border-radius:9px; outline:0; color:#fff; background:rgba(255,255,255,.08); font:11px 'Poppins',sans-serif; }
        .desktop-search input::placeholder { color:rgba(255,255,255,.65); }
        .desktop-user { display:flex; align-items:center; gap:10px; }
        .desktop-user .user-info { text-align:right; }
        .desktop-user .name,.desktop-user .role { display:block; }
        .desktop-user .name { color:#fff; }
        .desktop-user-menu { right:0; left:auto; }
        .desktop-user-menu form { margin:0; }
        .desktop-user-menu button { width:100%; display:flex; align-items:center; gap:10px; padding:10px 11px; border:0; border-radius:7px; color:#c7004b; background:transparent; font:11px 'Poppins',sans-serif; cursor:pointer; }
        .desktop-user-menu button:hover { background:#fff0f6; }
        .sidebar-logo {
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }
        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 18px;
            flex-shrink: 0;
        }
        .logo-text .brand { font-size: 15px; font-weight: 700; color: #fff; }
        .logo-text .sub { font-size: 11px; color: rgba(255,255,255,0.45); }

        /* User card */
        .sidebar-user {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }
        .sidebar-store { flex-shrink: 0; }
        .user-avatar {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #7c4dff, #e8398c);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 14px; font-weight: 600;
            flex-shrink: 0;
        }
        .user-info .name { font-size: 13px; font-weight: 600; color: #fff; }
        .user-info .role { font-size: 11px; color: rgba(255,255,255,0.45); }

        /* Nav */
        .sidebar-nav {
            flex: 1 1 auto;
            min-height: 0;
            padding: 14px 0;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(232,57,140,0.65) rgba(255,255,255,0.05);
        }
        .sidebar-nav::-webkit-scrollbar { width: 6px; }
        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
        }
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(232,57,140,0.58);
            border-radius: 10px;
        }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: var(--accent); }
        .nav-section {
            padding: 10px 20px 6px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 24px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            position: relative;
        }
        .nav-item:hover {
            color: #fff;
            background: var(--sidebar-hover);
        }
        .nav-item.active {
            color: #fff;
            background: rgba(232,57,140,0.15);
            border-left-color: var(--accent);
        }
        .nav-item i { width: 18px; text-align: center; font-size: 15px; }
        .nav-badge {
            margin-left: auto;
            background: var(--accent);
            color: #fff;
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 20px;
            font-weight: 600;
        }
        /* Submenu */
        .nav-submenu { display: none; }
        .nav-submenu.open { display: block; }
        .nav-parent { cursor: pointer; }
        .nav-parent .arrow {
            margin-left: auto;
            font-size: 11px;
            transition: transform 0.2s;
        }
        .nav-parent.open .arrow { transform: rotate(90deg); }
        .nav-submenu .nav-item {
            padding-left: 52px;
            font-size: 13px;
        }

        /* Sidebar footer */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }
        .btn-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 14px;
            background: rgba(232,57,140,0.12);
            color: rgba(255,255,255,0.7);
            border: 1px solid rgba(232,57,140,0.25);
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-logout:hover {
            background: rgba(232,57,140,0.25);
            color: #fff;
        }

        /* ─── MAIN CONTENT ─── */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-width: 0; /* permite que las tablas anchas se contengan en vez de expandir el layout */
            max-width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .page-content { min-width: 0; }
        /* Las tablas nunca deben desbordar el área de contenido */
        .table-wrap { max-width: 100%; }

        /* Topbar */
        .topbar {
            height: var(--topbar-height);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 28px;
            gap: 16px;
            position: sticky; top: 0;
            z-index: 50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .topbar-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-dark);
            flex: 1;
        }
        .topbar-search {
            position: relative;
        }
        .topbar-search input {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 14px 8px 38px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            width: 220px;
            transition: all 0.2s;
        }
        .topbar-search input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(232,57,140,0.1);
        }
        .topbar-search i {
            position: absolute;
            left: 12px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
        }
        .topbar-actions { display: flex; align-items: center; gap: 10px; }
        .topbar-btn {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: var(--bg);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 14px;
            position: relative;
        }
        .topbar-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
        .topbar-btn .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px;
            background: var(--accent);
            border-radius: 50%;
            border: 2px solid #fff;
        }

        /* Page content */
        .page-content { padding: 28px; flex: 1; }
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px;
        }
        .page-header h1 { font-size: 22px; font-weight: 700; color: var(--text-dark); }
        .breadcrumb {
            font-size: 12px; color: var(--text-muted);
            display: flex; align-items: center; gap: 6px;
            margin-top: 3px;
        }
        .breadcrumb a { color: var(--accent); text-decoration: none; }
        .breadcrumb-sep { color: #ccc; }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px;
        }
        .card-title { font-size: 15px; font-weight: 600; color: var(--text-dark); }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13.5px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-left: 4px solid #22c55e; }
        .alert-danger  { background: #fff5f5; border: 1px solid #fed7d7; color: #c53030; border-left: 4px solid #fc8181; }
        .alert-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-left: 4px solid #f59e0b; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13.5px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            border: none;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-dark); transform: translateY(-1px); box-shadow: 0 4px 15px rgba(232,57,140,0.35); }
        .btn-outline { background: transparent; color: var(--accent); border: 2px solid var(--accent); }
        .btn-outline:hover { background: var(--accent); color: #fff; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            background: #fafbff;
        }
        tbody td {
            padding: 13px 16px;
            font-size: 13.5px;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border);
        }
        tbody tr:hover { background: #fafbff; }
        tbody tr:last-child td { border-bottom: none; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-info    { background: #dbeafe; color: #1e40af; }
        .badge-pink    { background: #fce7f3; color: #9d174d; }

        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 7px; }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 13.5px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            background: #fafbff;
            color: var(--text-dark);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(232,57,140,0.1);
            background: #fff;
        }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }

        /* Pagination */
        .pagination { display: flex; gap: 4px; margin-top: 20px; }
        .pagination .page-link {
            padding: 7px 12px;
            border-radius: 7px;
            border: 1px solid var(--border);
            font-size: 13px;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination .page-link:hover,
        .pagination .page-link.active { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* Responsive */
        /* ===== TABLETAS Y CELULARES: menú lateral deslizable ===== */
        @media (max-width: 1024px) {
            .desktop-header { display:none; }
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 4px 0 24px rgba(0,0,0,0.25);
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; max-width: 100%; }
            .menu-toggle { display: flex; }
            .topbar { padding: 0 16px; }
        }
        @media (min-width: 1025px) {
            body { display:block; }
            .sidebar,.sidebar-overlay { display:none !important; }
            .main-content { margin-left:0; max-width:100%; min-height:calc(100vh - 82px); }
            .main-content .topbar { display:none; }
        }
        @media (min-width:1025px) and (max-width:1450px) {
            .desktop-header { gap:14px; padding:0 16px; }
            .desktop-brand { min-width:auto; margin-right:8px; }
            .desktop-nav-link { padding:0 11px; }
            .desktop-search { display:none; }
        }
        @media (max-width: 640px) {
            .topbar-search { display: none; }
            .topbar-title { font-size: 16px; }
            .page-content { padding: 16px !important; }
            .topbar-actions { gap: 6px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            /* Tablas con desplazamiento horizontal */
            .page-content table { display: block; overflow-x: auto; white-space: nowrap; }
            .kpi-grid, .charts-grid, .bottom-grid, .form-row { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 420px) {
            .topbar-actions .topbar-btn:not(:last-child) { display: none; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @php $layoutStore = auth()->user()->tienda; @endphp
    <header class="desktop-header">
        <a href="{{ route('dashboard') }}" class="desktop-brand"><span class="logo-icon">@if($layoutStore?->logo)<img class="store-brand-logo" src="{{ asset('storage/'.$layoutStore->logo) }}" alt="Logo de {{ $layoutStore->nombre }}">@else<i class="fa-solid fa-shirt"></i>@endif</span><span><strong>{{ $layoutStore?->nombre ?? 'Tienda Moda' }}</strong><small>SaaS v1.0</small></span></a>
        <nav class="desktop-nav" aria-label="Navegación principal">
            <a href="{{ route('dashboard') }}" class="desktop-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa-solid fa-house"></i>Dashboard</a>
            <div class="desktop-nav-group"><button class="desktop-nav-link {{ request()->routeIs('ventas*','clientes*','caja*') ? 'active' : '' }}"><i class="fa-solid fa-bag-shopping"></i>Ventas<i class="fa-solid fa-chevron-down"></i></button><div class="desktop-dropdown"><a href="{{ route('ventas.create') }}"><i class="fa-solid fa-cash-register"></i>Nueva venta</a><a href="{{ route('ventas.index') }}"><i class="fa-solid fa-receipt"></i>Historial de ventas</a><a href="{{ route('caja.index') }}"><i class="fa-solid fa-vault"></i>Caja</a><a href="{{ route('clientes.index') }}"><i class="fa-solid fa-users"></i>Clientes</a></div></div>
            <div class="desktop-nav-group"><button class="desktop-nav-link {{ request()->routeIs('productos*','categorias*','marcas*') ? 'active' : '' }}"><i class="fa-solid fa-tags"></i>Catálogo<i class="fa-solid fa-chevron-down"></i></button><div class="desktop-dropdown"><a href="{{ route('productos.index') }}"><i class="fa-solid fa-shirt"></i>Productos</a><a href="{{ route('categorias.index') }}"><i class="fa-solid fa-layer-group"></i>Categorías</a><a href="{{ route('marcas.index') }}"><i class="fa-solid fa-star"></i>Marcas</a></div></div>
            <div class="desktop-nav-group"><button class="desktop-nav-link {{ request()->routeIs('stock*','proveedores*','compras*') ? 'active' : '' }}"><i class="fa-solid fa-box"></i>Inventario<i class="fa-solid fa-chevron-down"></i></button><div class="desktop-dropdown"><a href="{{ route('stock.index') }}"><i class="fa-solid fa-boxes-stacked"></i>Stock</a><a href="{{ route('proveedores.index') }}"><i class="fa-solid fa-truck"></i>Proveedores</a><a href="{{ route('compras.index') }}"><i class="fa-solid fa-file-invoice"></i>Compras</a></div></div>
            <div class="desktop-nav-group"><button class="desktop-nav-link {{ request()->routeIs('reportes*') ? 'active' : '' }}"><i class="fa-solid fa-chart-column"></i>Reportes<i class="fa-solid fa-chevron-down"></i></button><div class="desktop-dropdown"><a href="{{ route('reportes.ventas') }}"><i class="fa-solid fa-chart-line"></i>Ventas</a><a href="{{ route('reportes.inventario') }}"><i class="fa-solid fa-chart-pie"></i>Inventario</a></div></div>
            <div class="desktop-nav-group"><button class="desktop-nav-link {{ request()->routeIs('usuarios*','tienda.*','configuracion*') ? 'active' : '' }}"><i class="fa-solid fa-gear"></i>Sistema<i class="fa-solid fa-chevron-down"></i></button><div class="desktop-dropdown"><a href="{{ route('usuarios.index') }}"><i class="fa-solid fa-users-gear"></i>Usuarios</a><a href="{{ route('tienda.edit') }}"><i class="fa-solid fa-store"></i>Mi tienda</a><a href="{{ route('configuracion.index') }}"><i class="fa-solid fa-sliders"></i>Configuración</a></div></div>
        </nav>
        <div class="desktop-tools">
            <label class="desktop-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Buscar..."></label>
            <div class="desktop-nav-group desktop-user"><div class="user-avatar">{{ strtoupper(substr(auth()->user()->name,0,2)) }}</div><button class="desktop-nav-link"><span class="user-info"><span class="name">{{ auth()->user()->name }}</span><span class="role">{{ auth()->user()->rol->nombre ?? 'Usuario' }}</span></span><i class="fa-solid fa-chevron-down"></i></button><div class="desktop-dropdown desktop-user-menu"><a href="{{ route('tienda.edit') }}"><i class="fa-solid fa-store"></i>Mi tienda</a><a href="{{ route('configuracion.index') }}"><i class="fa-solid fa-gear"></i>Configuración</a><form method="POST" action="{{ route('logout') }}">@csrf<button><i class="fa-solid fa-right-from-bracket"></i>Cerrar sesión</button></form></div></div>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">@if($layoutStore?->logo)<img class="store-brand-logo" src="{{ asset('storage/'.$layoutStore->logo) }}" alt="Logo de {{ $layoutStore->nombre }}">@else<i class="fa-solid fa-shirt"></i>@endif</div>
            <div class="logo-text">
                <div class="brand">{{ $layoutStore?->nombre ?? 'Tienda Moda' }}</div>
                <div class="sub">SaaS v1.0</div>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
            <div class="user-info">
                <div class="name">{{ auth()->user()->name }}</div>
                <div class="role">{{ auth()->user()->rol->nombre ?? 'Usuario' }}</div>
            </div>
        </div>

        @php $miTienda = auth()->user()->tienda; @endphp
        @if($miTienda)
        <div class="sidebar-store" style="margin:0 16px 14px;padding:12px 14px;background:rgba(255,255,255,0.06);border-radius:12px;">
            <div style="font-size:12px;color:#fff;font-weight:600;"><i class="fa-solid fa-store" style="opacity:.7;margin-right:6px;"></i>{{ $miTienda->nombre }}</div>
            <div style="display:flex;align-items:center;gap:6px;margin-top:6px;">
                <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:linear-gradient(135deg,#e8398c,#7b1fa2);color:#fff;">{{ $miTienda->plan->nombre ?? 'Sin plan' }}</span>
                @php $dr = $miTienda->diasRestantes(); @endphp
                @if($dr !== null)
                    <span style="font-size:10px;color:{{ $dr <= 5 ? '#fca5a5' : 'rgba(255,255,255,0.5)' }};">{{ $dr }} días</span>
                @endif
            </div>
        </div>
        @endif

        <nav class="sidebar-nav">
            <div class="nav-section">Principal</div>
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
            </a>

            <div class="nav-section">Ventas</div>
            <a href="{{ route('ventas.create') }}" class="nav-item {{ request()->routeIs('ventas.create') ? 'active' : '' }}">
                <i class="fa-solid fa-cash-register"></i><span>Nueva Venta</span>
            </a>
            <a href="{{ route('ventas.index') }}" class="nav-item {{ request()->routeIs('ventas.index') ? 'active' : '' }}">
                <i class="fa-solid fa-receipt"></i><span>Historial Ventas</span>
            </a>
            <a href="{{ route('caja.index') }}" class="nav-item {{ request()->routeIs('caja*') ? 'active' : '' }}">
                <i class="fa-solid fa-vault"></i><span>Caja</span>
            </a>
            <a href="{{ route('clientes.index') }}" class="nav-item {{ request()->routeIs('clientes*') ? 'active' : '' }}">
                <i class="fa-solid fa-users"></i><span>Clientes</span>
            </a>

            <div class="nav-section">Catálogo</div>
            <a href="{{ route('productos.index') }}" class="nav-item {{ request()->routeIs('productos*') ? 'active' : '' }}">
                <i class="fa-solid fa-tags"></i><span>Productos</span>
            </a>
            <a href="{{ route('categorias.index') }}" class="nav-item {{ request()->routeIs('categorias*') ? 'active' : '' }}">
                <i class="fa-solid fa-layer-group"></i><span>Categorías</span>
            </a>
            <a href="{{ route('marcas.index') }}" class="nav-item {{ request()->routeIs('marcas*') ? 'active' : '' }}">
                <i class="fa-solid fa-star"></i><span>Marcas</span>
            </a>

            <div class="nav-section">Inventario</div>
            <a href="{{ route('stock.index') }}" class="nav-item {{ request()->routeIs('stock*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked"></i><span>Stock</span>
            </a>
            <a href="{{ route('proveedores.index') }}" class="nav-item {{ request()->routeIs('proveedores*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck"></i><span>Proveedores</span>
            </a>
            <a href="{{ route('compras.index') }}" class="nav-item {{ request()->routeIs('compras*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice"></i><span>Compras</span>
            </a>

            <div class="nav-section">Reportes</div>
            <a href="{{ route('reportes.ventas') }}" class="nav-item {{ request()->routeIs('reportes.ventas') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-bar"></i><span>Ventas</span>
            </a>
            <a href="{{ route('reportes.inventario') }}" class="nav-item {{ request()->routeIs('reportes.inventario') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie"></i><span>Inventario</span>
            </a>

            <div class="nav-section">Sistema</div>
            <a href="{{ route('usuarios.index') }}" class="nav-item {{ request()->routeIs('usuarios*') ? 'active' : '' }}">
                <i class="fa-solid fa-users-gear"></i><span>Usuarios</span>
            </a>
            <a href="{{ route('tienda.edit') }}" class="nav-item {{ request()->routeIs('tienda.*') ? 'active' : '' }}">
                <i class="fa-solid fa-store"></i><span>Mi Tienda</span>
            </a>
            <a href="{{ route('configuracion.index') }}" class="nav-item {{ request()->routeIs('configuracion*') ? 'active' : '' }}">
                <i class="fa-solid fa-gear"></i><span>Configuración</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- ─── MAIN ─── -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú"><i class="fa-solid fa-bars"></i></button>
            <div class="topbar-title">@yield('page-title', 'Panel')</div>
            <div class="topbar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Buscar...">
            </div>
            <div class="topbar-actions">
                <a href="{{ route('ventas.create') }}" class="topbar-btn" title="Nueva venta">
                    <i class="fa-solid fa-plus"></i>
                </a>
                <a href="#" class="topbar-btn" title="Notificaciones">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notif-dot"></span>
                </a>
                <a href="#" class="topbar-btn" title="Mi perfil">
                    <i class="fa-solid fa-user"></i>
                </a>
            </div>
        </header>

        <!-- Content -->
        <main class="page-content">
            @if(session('success'))
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>{{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-xmark"></i>{{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        // Submenús toggle
        document.querySelectorAll('.nav-parent').forEach(item => {
            item.addEventListener('click', () => {
                item.classList.toggle('open');
                const sub = item.nextElementSibling;
                if (sub && sub.classList.contains('nav-submenu')) {
                    sub.classList.toggle('open');
                }
            });
        });

        // Menú lateral responsive (tabletas / celulares)
        (function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle  = document.getElementById('menuToggle');
            const abrir  = () => { sidebar.classList.add('open'); overlay.classList.add('show'); };
            const cerrar = () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); };

            if (toggle) toggle.addEventListener('click', () => {
                sidebar.classList.contains('open') ? cerrar() : abrir();
            });
            if (overlay) overlay.addEventListener('click', cerrar);

            // Al tocar un enlace del menú en móvil, cerrar
            sidebar.querySelectorAll('a.nav-item').forEach(a => {
                a.addEventListener('click', () => { if (window.innerWidth <= 1024) cerrar(); });
            });
            // Si se agranda la ventana, asegurar estado limpio
            window.addEventListener('resize', () => { if (window.innerWidth > 1024) cerrar(); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
