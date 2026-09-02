@extends('layouts.app')
@section('title', 'Nueva Venta')
@section('page-title', 'Nueva Venta - POS')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pos.css') }}">
@endpush

@section('content')
<div class="page-header pos-page-header">
    <div class="pos-title-wrap">
        <span class="pos-title-icon"><i class="fa-solid fa-bag-shopping"></i></span>
        <div><h1>Punto de Venta</h1><p>Selecciona productos, variantes y confirma el cobro.</p></div>
    </div>
    <a href="{{ route('ventas.index') }}" class="btn btn-secondary"><i class="fa-solid fa-clock-rotate-left"></i> Historial de ventas</a>
</div>

@if($errors->any())
    <div class="pos-alert pos-alert-error" role="alert" aria-labelledby="posValidationTitle" tabindex="-1">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div><strong id="posValidationTitle">No se pudo registrar la venta.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
@endif

@if(! $cajaAbierta)
    <div class="pos-alert pos-alert-error pos-cash-alert" role="alert">
        <i class="fa-solid fa-cash-register"></i>
        <div>
            <strong>No hay un turno de caja abierto.</strong>
            <div>Abre la caja antes de registrar una venta.</div>
        </div>
                    <a href="{{ route('caja.index') }}" class="btn btn-primary pos-open-cash"><i class="fa-solid fa-lock-open"></i> Abrir caja<span class="sr-only"> · Ir a Caja</span></a>
    </div>
@endif

<div id="posNotice" class="pos-notice" role="status" aria-live="polite" aria-atomic="true" tabindex="-1"></div>

<div class="pos-layout">
    <nav class="category-rail" aria-label="Categorías del catálogo">
        <div class="category-rail-title"><i class="fa-regular fa-rectangle-list"></i><span>Categorías</span></div>
        <a href="{{ route('ventas.create') }}" class="category-shortcut {{ empty($filters['categoria_id']) ? 'active' : '' }}" data-category-shortcut=""><i class="fa-solid fa-border-all"></i><span>Todas</span></a>
        @foreach($categorias as $categoria)
            <a href="{{ route('ventas.create', ['categoria_id' => $categoria->id]) }}" class="category-shortcut {{ (string) ($filters['categoria_id'] ?? '') === (string) $categoria->id ? 'active' : '' }}" data-category-shortcut="{{ $categoria->id }}"><i class="fa-solid fa-shirt"></i><span>{{ $categoria->nombre }}</span></a>
        @endforeach
    </nav>
    <section class="pos-catalog" aria-label="Catálogo de productos">
        <div class="pos-toolbar">
            <label class="pos-search" for="productSearch">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="productSearch" autocomplete="off" placeholder="Nombre, SKU o código de barras...">
                <kbd>F2</kbd>
            </label>
            <div class="pos-filters">
                <select id="categoryFilter" aria-label="Filtrar por categoría">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected((string) ($filters['categoria_id'] ?? '') === (string) $categoria->id)>{{ $categoria->nombre }}</option>@endforeach
                </select>
                <select id="brandFilter" aria-label="Filtrar por marca">
                    <option value="">Todas las marcas</option>
                    @foreach($marcas as $marca)<option value="{{ $marca->id }}" @selected((string) ($filters['marca_id'] ?? '') === (string) $marca->id)>{{ $marca->nombre }}</option>@endforeach
                </select>
                <label class="stock-filter"><input type="checkbox" id="stockFilter"> Solo disponibles</label>
            </div>
        </div>

        <div class="catalog-heading">
            <div><span class="catalog-heading-icon"><i class="fa-solid fa-boxes-stacked"></i></span><div><strong>Productos disponibles</strong><small>Selecciona una variante para agregarla</small></div></div>
            <div class="catalog-meta"><span id="productCount">{{ $productos->count() }} productos</span><button type="button" id="clearFilters"><i class="fa-solid fa-filter-circle-xmark"></i> Limpiar filtros</button></div>
        </div>
        <div class="product-grid" id="productGrid">
            @foreach($productos as $producto)
                @php
                    $stock = $producto->variantes->sum('stock');
                    $lowStockVariants = $producto->variantes->filter->tieneStockBajo()->count();
                    $firstAvailableVariantId = $producto->variantes->first(fn ($variante) => ! $variante->estaAgotada())?->id;
                    $search = $producto->nombre.' '.$producto->codigo.' '.$producto->variantes->pluck('codigo_barra')->filter()->join(' ');
                @endphp
                <article class="product-card" data-search="{{ mb_strtolower($search) }}" data-category="{{ $producto->categoria_id }}"
                         data-brand="{{ $producto->marca_id }}" data-available="{{ $stock > 0 ? '1' : '0' }}">
                    <div class="product-media">
                        @if($producto->imagen)
                            <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" loading="lazy">
                        @else
                            <div class="product-placeholder"><i class="fa-solid fa-shirt"></i></div>
                        @endif
                        <span class="stock-badge {{ $stock <= 0 ? 'out' : ($lowStockVariants > 0 ? 'low' : '') }}">
                            {{ $stock <= 0 ? 'Agotado' : ($lowStockVariants > 0 ? $lowStockVariants.' con stock bajo' : $stock.' disponibles') }}
                        </span>
                    </div>
                    <div class="product-body">
                        <div class="product-code">{{ $producto->codigo }}</div>
                        <h2>{{ $producto->nombre }}</h2>
                        <div class="product-meta">{{ $producto->categoria?->nombre ?? 'Sin categoría' }}@if($producto->marca) · {{ $producto->marca->nombre }}@endif</div>
                        <div class="product-price">{{ $simboloMoneda }} {{ number_format($producto->precioActual(), 2) }}</div>
                        <select class="variant-select" id="variant-{{ $producto->id }}" aria-label="Talla y color de {{ $producto->nombre }}">
                            @forelse($producto->variantes as $variante)
                                <option value="{{ $variante->id }}" data-talla="{{ $variante->talla }}" data-color="{{ $variante->color }}"
                                        data-stock="{{ $variante->stock }}" @selected($variante->id === $firstAvailableVariantId) @disabled($variante->estaAgotada())>
                                    {{ $variante->talla }} / {{ $variante->color }} · {{ $variante->descripcionStock() }}
                                </option>
                            @empty
                                <option value="">Sin variantes</option>
                            @endforelse
                        </select>
                        <button type="button" class="add-product" data-product-id="{{ $producto->id }}" data-name="{{ $producto->nombre }}"
                                data-price="{{ $producto->precioActual() }}" data-image="{{ $producto->imagen ? asset('storage/'.$producto->imagen) : '' }}" @disabled($stock <= 0)>
                            <i class="fa-solid fa-plus"></i> {{ $stock <= 0 ? 'Agotado' : 'Agregar' }}
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
        <div id="emptyCatalog" class="empty-catalog" hidden><i class="fa-solid fa-box-open"></i><strong>Sin coincidencias</strong><span>Prueba con otros filtros.</span></div>
        <div class="pos-pagination">{{ $productos->withQueryString()->links() }}</div>
    </section>

    <aside class="cart-panel" aria-label="Carrito de venta">
        <div class="cart-header"><div><i class="fa-solid fa-cart-shopping"></i><strong>Carrito</strong><b id="cartBubble">0</b></div><span id="cartCount">0 artículos</span></div>
        <div class="cart-items" id="cartItems"></div>
        <div class="cart-summary">
            <div><span>Subtotal</span><span id="sumSubtotal">{{ $simboloMoneda }} 0.00</span></div>
            <div><span>Impuesto ({{ number_format(auth()->user()->tienda->igv, 2) }}%)</span><span id="sumIGV">{{ $simboloMoneda }} 0.00</span></div>
            <div class="discount-row"><span>Descuento</span><span id="sumDesc">- {{ $simboloMoneda }} 0.00</span></div>
            <div class="summary-total"><span>Total</span><span id="sumTotal">{{ $simboloMoneda }} 0.00</span></div>
        </div>
        <div class="cart-footer">
            <label>Cliente</label>
            <div class="client-picker">
                <div class="client-search-wrap"><i class="fa-solid fa-user-magnifying-glass"></i><input type="search" id="clientSearch" class="form-control" autocomplete="off" placeholder="Buscar cliente o usar Cliente directo"><input type="hidden" id="clienteSelect"><div id="clientResults" class="client-results" hidden></div></div>
                <button type="button" id="newClient" title="Crear cliente"><i class="fa-solid fa-user-plus"></i></button>
            </div>
            <div class="checkout-grid">
                <label>Comprobante<select id="comprobanteSelect" class="form-control"><option value="boleta">Boleta</option><option value="factura">Factura</option><option value="ticket">Ticket</option></select></label>
                <label>Descuento global<input type="number" id="descuentoGlobal" class="form-control" min="0" step="0.01" value="0"></label>
            </div>
            <div class="payments-title"><span>Formas de pago</span><button type="button" id="addPayment"><i class="fa-solid fa-plus"></i> Combinar pago</button></div>
            <div id="paymentRows" class="payment-rows"></div>
            <div id="vueltoRow" class="change-row" hidden><span>Vuelto</span><strong id="vueltoAmt">{{ $simboloMoneda }} 0.00</strong></div>
            <button type="button" id="reviewSale" class="checkout-button" @disabled(! $cajaAbierta)><i class="fa-solid fa-shield-check"></i><span>{{ $cajaAbierta ? 'Revisar y confirmar' : 'Abre la caja para vender' }}</span></button>
        </div>
    </aside>
</div>

<dialog id="confirmSale" class="confirm-dialog">
    <div class="confirm-icon"><i class="fa-solid fa-receipt"></i></div>
    <h2>Confirmar venta</h2>
    <p>Revisa los datos antes de descontar el inventario.</p>
    <dl><div><dt>Artículos</dt><dd id="confirmItems">0</dd></div><div><dt>Pago</dt><dd id="confirmPayment">—</dd></div><div><dt>Total</dt><dd id="confirmTotal">{{ $simboloMoneda }} 0.00</dd></div></dl>
    <div class="dialog-actions"><button type="button" id="cancelSale" class="btn btn-secondary">Seguir editando</button><button type="button" id="submitSale" class="btn btn-primary" aria-describedby="submitSaleHelp"><i class="fa-solid fa-check"></i> Registrar venta</button></div><span id="submitSaleHelp" class="sr-only">Esta acción descuenta stock y registra el pago una sola vez.</span>
</dialog>

<dialog id="clientDialog" class="confirm-dialog client-dialog">
    <div class="confirm-icon"><i class="fa-solid fa-user-plus"></i></div><h2>Nuevo cliente</h2><p>Regístralo sin salir de la venta.</p>
    <form id="quickClientForm">
        <div class="quick-client-grid"><label>Nombre<input name="nombre" class="form-control" required maxlength="100"></label><label>Apellido<input name="apellido" class="form-control" required maxlength="100"></label><label>Documento<input name="dni" class="form-control" maxlength="30"></label><label>Teléfono<input name="telefono" class="form-control" maxlength="30"></label><label class="wide">Correo<input name="email" type="email" class="form-control" maxlength="150"></label></div>
        <div id="clientFormError" class="form-error"></div><div class="dialog-actions"><button type="button" id="cancelClient" class="btn btn-secondary">Cancelar</button><button type="submit" id="saveClient" class="btn btn-primary">Guardar cliente</button></div>
    </form>
</dialog>

<form id="ventaForm" method="POST" action="{{ route('ventas.store') }}" hidden aria-busy="false">
    @csrf
    <input type="hidden" name="operacion_id" value="{{ old('operacion_id', (string) Illuminate\Support\Str::uuid()) }}">
    <div id="formItems"></div>
    <input type="hidden" name="cliente_id" id="hCliente"><input type="hidden" name="tipo_comprobante" id="hComprobante">
    <input type="hidden" name="metodo_pago" id="hPago"><input type="hidden" name="monto_pagado" id="hMontoPagado">
    <input type="hidden" name="descuento_global" id="hDescuentoGlobal">
</form>
@endsection

@push('scripts')
@php
    $posConfig = ['currency' => $simboloMoneda, 'taxRate' => (float) auth()->user()->tienda->igv, 'taxIncluded' => (bool) auth()->user()->tienda->precios_incluyen_igv, 'createUrl' => route('ventas.create'), 'clientSearchUrl' => route('ventas.clientes.buscar'), 'clientStoreUrl' => route('ventas.clientes.store'), 'catalogUrl' => route('ventas.catalogo'), 'csrf' => csrf_token()];
@endphp
<script>window.posConfig = @json($posConfig);</script>
<script src="{{ asset('js/pos.js') }}" defer></script>
@endpush
