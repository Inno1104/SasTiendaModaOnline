<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $store->descripcion_publica ?: 'Tienda online de '.$store->nombre }}">
    <meta name="theme-color" content="{{ $store->color_primario }}">
    <title>{{ $store->nombre }} · Tienda online</title>
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
    <link rel="stylesheet" href="{{ asset('css/storefront-catalog.css') }}">
    <link rel="stylesheet" href="{{ asset('css/storefront-cart-icon.css') }}">
    <script src="{{ asset('js/storefront-catalog.js') }}" defer></script>
</head>
<body style="--store-color: {{ $store->color_primario }}">
    <header class="public-store-header">
        <div class="public-store-header-inner">
            <a href="{{ $store->publicUrl() }}" class="public-store-brand" aria-label="Inicio de {{ $store->nombre }}">
                <span class="public-store-brand-logo"><img src="{{ $store->logo ? asset('storage/'.$store->logo) : asset('images/store-logo-default.svg') }}" alt="Logo de {{ $store->nombre }}"></span>
                <span><strong>{{ $store->nombre }}</strong><small>Tienda online</small></span>
            </a>
            <nav aria-label="Navegación principal">
                <a href="{{ ($cartOnly ?? false) ? $store->publicUrl().'#catalogo' : '#catalogo' }}">Catálogo</a>
                <a href="{{ route('storefront.orders.lookup', $store->slug) }}" class="public-order-lookup-link">Mi pedido</a>
                <a href="{{ route('storefront.cart.show', $store->slug) }}" class="public-store-cart-link public-store-cart-icon" aria-label="Carrito con {{ $cart['units'] }} artículos">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 10.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 1.9-1.4L21 8H7M10 20h.01M18 20h.01"/></svg>
                    <b aria-hidden="true">{{ $cart['units'] }}</b><span class="sr-only">Carrito ({{ $cart['units'] }})</span>
                </a>
                <a href="#contacto">Contacto</a><a href="#horarios">Horarios</a><a href="#condiciones">Condiciones</a>
            </nav>
        </div>
    </header>

    <main>
        @unless($cartOnly ?? false)
        @if(session('online_order'))
            <section id="pedido-confirmado" class="public-order-success" role="status">
                <span aria-hidden="true">✓</span>
                <div><small>Pedido recibido</small><h2>{{ session('online_order.numero') }}</h2><p>Estado: <strong>{{ session('online_order.estado') }}</strong> · Total: <strong>{{ session('online_order.total') }}</strong></p><p>Reservamos las variantes hasta las <strong>{{ session('online_order.reserva_expira') }}</strong>. Completa el pago antes de esa hora.</p></div>
            </section>
        @endif
        <section class="public-store-hero" aria-labelledby="public-store-title">
            <div class="public-store-hero-content">
                <span class="public-store-eyebrow"><i aria-hidden="true"></i> Bienvenido</span>
                <h1 id="public-store-title">{{ $store->nombre }}</h1>
                <p>{{ $store->descripcion_publica ?: 'Descubre nuestros productos y consulta su disponibilidad en tiempo real.' }}</p>
                <a href="#catalogo" class="public-store-button">Ver catálogo <span aria-hidden="true">→</span></a>
                <ul class="public-store-benefits" aria-label="Beneficios de la tienda">
                    <li><span aria-hidden="true">↻</span><strong>Productos<small>actualizados</small></strong></li>
                    <li><span aria-hidden="true">⚡</span><strong>Disponibilidad<small>en tiempo real</small></strong></li>
                    <li><span aria-hidden="true">✓</span><strong>Compra<small>segura</small></strong></li>
                </ul>
            </div>
            <div class="public-store-hero-visual" aria-hidden="true">
                <span class="public-store-orbit"></span>
                <span class="public-store-dots"></span>
                <div class="public-store-logo">
                    <span class="public-store-logo-glow"></span>
                    <img src="{{ $store->logo ? asset('storage/'.$store->logo) : asset('images/store-logo-default.svg') }}" alt="">
                </div>
                <span class="public-store-floating-icon public-store-floating-heart">♡</span>
                <span class="public-store-floating-icon public-store-floating-cart">🛍</span>
            </div>
        </section>

        <section id="catalogo" class="public-catalog" aria-labelledby="public-catalog-title">
            @if(session('purchase_thanks'))<div class="public-cart-alert success purchase-thanks" role="status" data-auto-dismiss-alert><strong>Compra finalizada</strong><span>{{ session('purchase_thanks') }}</span></div>@endif
            @if(session('order_access_closed'))<div class="public-cart-alert warning purchase-thanks" role="status" data-auto-dismiss-alert><strong>Seguimiento finalizado</strong><span>{{ session('order_access_closed') }}</span></div>@endif
            @if(session('cart_success'))<div class="public-cart-alert success" role="status" data-auto-dismiss-alert>{{ session('cart_success') }}</div>@endif
            @if($errors->any())<div class="public-cart-alert error" role="alert"><strong>No pudimos actualizar el carrito.</strong><span>{{ $errors->first() }}</span></div>@endif
            <div class="public-catalog-heading"><div><span class="public-store-eyebrow">Colección disponible</span><h2 id="public-catalog-title">Nuestro catálogo</h2><p>Precios y existencias actualizados por talla y color.</p></div><strong>{{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}</strong></div>

            <form method="GET" action="{{ $store->publicUrl() }}" class="public-catalog-search" role="search">
                <label><span class="sr-only">Buscar productos</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Buscar por producto, categoría o marca..."></label>
                @if($filters['categoria'] ?? null)<input type="hidden" name="categoria" value="{{ $filters['categoria'] }}">@endif
                @foreach($filters['tallas'] ?? [] as $size)<input type="hidden" name="tallas[]" value="{{ $size }}">@endforeach
                @foreach($filters['colores'] ?? [] as $color)<input type="hidden" name="colores[]" value="{{ $color }}">@endforeach
                @if(isset($filters['precio_min']))<input type="hidden" name="precio_min" value="{{ $filters['precio_min'] }}">@endif
                @if(isset($filters['precio_max']))<input type="hidden" name="precio_max" value="{{ $filters['precio_max'] }}">@endif
                <button type="submit">Buscar</button>
                <label class="public-catalog-sort"><span class="sr-only">Ordenar productos</span><select name="orden" data-catalog-sort><option value="destacados" @selected(($filters['orden'] ?? 'destacados') === 'destacados')>Destacados</option><option value="recientes" @selected(($filters['orden'] ?? '') === 'recientes')>Más recientes</option><option value="nombre" @selected(($filters['orden'] ?? '') === 'nombre')>Nombre A–Z</option><option value="precio_asc" @selected(($filters['orden'] ?? '') === 'precio_asc')>Menor precio</option><option value="precio_desc" @selected(($filters['orden'] ?? '') === 'precio_desc')>Mayor precio</option></select></label>
                @if(($filters['q'] ?? null) || ($filters['categoria'] ?? null))<a href="{{ $store->publicUrl() }}#catalogo">Limpiar</a>@endif
            </form>

            <nav class="public-categories" aria-label="Categorías del catálogo">
                <a href="{{ $store->publicUrl() }}#catalogo" class="{{ empty($filters['categoria']) ? 'active' : '' }}"><i aria-hidden="true">▦</i> Todos <span>{{ $categories->sum('productos_publicos_count') }}</span></a>
                @foreach($categories as $category)
                    <a href="{{ $store->publicUrl() }}?categoria={{ $category->slug }}#catalogo" class="{{ ($filters['categoria'] ?? null) === $category->slug ? 'active' : '' }}"><i aria-hidden="true">◇</i> {{ $category->nombre }} <span>{{ $category->productos_publicos_count }}</span></a>
                @endforeach
            </nav>

            <div class="public-catalog-layout">
                <aside class="public-catalog-filters" aria-labelledby="public-filter-title">
                    <form method="GET" action="{{ $store->publicUrl() }}#catalogo">
                        <h3 id="public-filter-title">Filtros</h3>
                        @if($filters['q'] ?? null)<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                        <input type="hidden" name="orden" value="{{ $filters['orden'] ?? 'destacados' }}">
                        <fieldset><legend>Categorías</legend>
                            <label><input type="radio" name="categoria" value="" @checked(empty($filters['categoria']))><span>Todas</span><small>{{ $categories->sum('productos_publicos_count') }}</small></label>
                            @foreach($categories as $category)<label><input type="radio" name="categoria" value="{{ $category->slug }}" @checked(($filters['categoria'] ?? null) === $category->slug)><span>{{ $category->nombre }}</span><small>{{ $category->productos_publicos_count }}</small></label>@endforeach
                        </fieldset>
                        <fieldset><legend>Precio</legend><div class="public-price-fields"><label><span>Desde</span><input type="number" name="precio_min" min="0" step="0.01" value="{{ $filters['precio_min'] ?? '' }}" placeholder="0"></label><label><span>Hasta</span><input type="number" name="precio_max" min="0" step="0.01" value="{{ $filters['precio_max'] ?? '' }}" placeholder="{{ number_format($priceLimit, 0, '.', '') }}"></label></div></fieldset>
                        @if($sizes->isNotEmpty())<fieldset><legend>Talla</legend><div class="public-filter-options">@foreach($sizes as $size)<label><input type="checkbox" name="tallas[]" value="{{ $size }}" @checked(in_array($size, $filters['tallas'] ?? [], true))><span>{{ $size }}</span></label>@endforeach</div></fieldset>@endif
                        @if($colors->isNotEmpty())<fieldset><legend>Color</legend><div class="public-color-options">@foreach($colors as $color)<label><input type="checkbox" name="colores[]" value="{{ $color }}" @checked(in_array($color, $filters['colores'] ?? [], true))><span data-color="{{ Illuminate\Support\Str::lower($color) }}" aria-hidden="true"></span>{{ $color }}</label>@endforeach</div></fieldset>@endif
                        <button type="submit">Aplicar filtros</button><a href="{{ $store->publicUrl() }}#catalogo">Limpiar</a>
                    </form>
                </aside>
                <div class="public-catalog-results">
                    <div class="public-results-toolbar"><strong>{{ $products->total() }} {{ $products->total() === 1 ? 'producto encontrado' : 'productos encontrados' }}</strong><span>Vista de catálogo</span></div>
                    <div class="public-products-grid">
                @forelse($products as $product)
                    @php
                        $stock = $product->variantes->sum(fn ($variant) => $variant->stockDisponibleVenta());
                        $hasOffer = $product->precio_oferta !== null && (float) $product->precio_oferta > 0 && (float) $product->precio_oferta < (float) $product->precio_venta;
                        $discount = $hasOffer ? (int) round((1 - ((float) $product->precio_oferta / (float) $product->precio_venta)) * 100) : 0;
                        $availableVariants = $product->variantes->filter(fn ($variant) => $variant->stockDisponibleVenta() > 0);
                        $sizesList = $availableVariants->pluck('talla')->filter()->unique()->values();
                        $colorsList = $availableVariants->pluck('color')->filter()->unique()->values();
                    @endphp
                    <article class="public-product-card">
                        <a href="{{ route('storefront.product.show', ['slug' => $store->slug, 'code' => $product->codigo]) }}" class="public-product-media" aria-label="Ver detalle de {{ $product->nombre }}">
                            @php($primaryImage = $product->imagenes->firstWhere('principal', true)?->ruta ?? $product->imagenes->first()?->ruta ?? $product->imagen)
                            @if($primaryImage)<img src="{{ asset('storage/'.$primaryImage) }}" alt="{{ $product->nombre }}" loading="lazy" data-product-image>@else<div><span aria-hidden="true">✦</span><small>Sin imagen</small></div>@endif
                            @if($hasOffer)<b><span aria-hidden="true">★</span> -{{ $discount }}%</b>@elseif($product->destacado)<b><span aria-hidden="true">★</span> Destacado</b>@endif
                            <span class="public-stock-badge {{ $stock > 0 ? 'available' : 'out' }}">{{ $stock > 0 ? $stock.' disponibles' : 'Agotado' }}</span>
                        </a>
                        <div class="public-product-body">
                            <div hidden data-product-gallery-map>@foreach($product->imagenes as $galleryImage)<span data-size="{{ $galleryImage->talla }}" data-color="{{ $galleryImage->color }}" data-url="{{ asset('storage/'.$galleryImage->ruta) }}"></span>@endforeach</div>
                            <small>{{ $product->categoria?->nombre }}@if($product->marca) · {{ $product->marca->nombre }}@endif</small>
                            <h3><a href="{{ route('storefront.product.show', ['slug' => $store->slug, 'code' => $product->codigo]) }}">{{ $product->nombre }}</a></h3>
                            <span class="public-product-code">{{ $product->codigo }}</span>
                            @if($product->descripcion)<p>{{ \Illuminate\Support\Str::limit($product->descripcion, 90) }}</p>@endif
                            <div class="public-product-price">
                                <strong>{{ $store->simbolo_moneda }} {{ number_format($product->precioActual(), 2) }}</strong>
                                @if($hasOffer)<del>{{ $store->simbolo_moneda }} {{ number_format((float) $product->precio_venta, 2) }}</del>@endif
                            </div>
                            <div class="public-variant-picker" aria-label="Opciones de {{ $product->nombre }}">
                                @if($colorsList->isNotEmpty())<div class="public-option-row"><span>Colores:</span><div class="public-color-swatches">@foreach($colorsList as $color)<button type="button" data-variant-color="{{ $color }}" data-color="{{ Illuminate\Support\Str::lower($color) }}" title="Color {{ $color }}" aria-label="Elegir color {{ $color }}"></button>@endforeach</div></div>@endif
                                @if($sizesList->isNotEmpty())<div class="public-option-row"><span>Tallas:</span><div class="public-size-options">@foreach($sizesList as $size)<button type="button" data-variant-size="{{ $size }}">{{ $size }}</button>@endforeach</div></div>@endif
                                <span class="sr-only">@foreach($product->variantes as $variant)@php($accessibleStock = $variant->stockDisponibleVenta())<span title="{{ $accessibleStock > 0 ? $accessibleStock.' disponibles' : 'Agotada' }}"><b>{{ $variant->talla }}</b> / {{ $variant->color }}</span>@endforeach</span>
                            </div>
                            <form method="POST" action="{{ route('storefront.cart.store', $store->slug) }}" class="public-add-cart-form" data-product-card-form>
                                @csrf
                                <label>
                                    <span class="sr-only">Talla y color de {{ $product->nombre }}</span>
                                    <select name="variante_id" required {{ $stock < 1 ? 'disabled' : '' }} data-variant-select>
                                        @foreach($product->variantes as $variant)
                                            @php($variantAvailable = $variant->stockDisponibleVenta())
                                            <option value="{{ $variant->id }}" data-size="{{ $variant->talla }}" data-color="{{ $variant->color }}" data-stock="{{ $variantAvailable }}" {{ $variantAvailable < 1 ? 'disabled' : '' }}>{{ $variant->talla }} / {{ $variant->color }} · {{ $variantAvailable }} disponibles</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="public-quantity-stepper">
                                    <span class="sr-only">Cantidad</span>
                                    <button type="button" data-quantity-minus aria-label="Reducir cantidad">−</button><input type="number" name="cantidad" value="1" min="1" max="99" inputmode="numeric" required {{ $stock < 1 ? 'disabled' : '' }} data-quantity-input><button type="button" data-quantity-plus aria-label="Aumentar cantidad">+</button>
                                </label>
                                <button type="submit" {{ $stock < 1 ? 'disabled' : '' }}><span aria-hidden="true">🛒</span>{{ $stock > 0 ? 'Agregar al carrito' : 'Agotado' }}</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="public-catalog-empty"><span aria-hidden="true">⌕</span><h3>No encontramos productos</h3><p>Prueba con otra búsqueda o categoría.</p><a href="{{ $store->publicUrl() }}#catalogo">Ver todo el catálogo</a></div>
                @endforelse
                    </div>

                    @if($products->hasPages())<div class="public-catalog-pagination">{{ $products->onEachSide(1)->links() }}</div>@endif
                </div>
            </div>
        </section>

        @endunless
        @if($cartOnly ?? false)
        <section id="carrito" class="public-cart public-cart-page" aria-labelledby="public-cart-title">
            <div class="public-cart-heading">
                <div><span class="public-store-eyebrow">Tu selección</span><h2 id="public-cart-title">Carrito</h2><p>Revisamos nuevamente precio y existencias antes de cada cambio.</p></div>
                @if($cart['items']->isNotEmpty())
                    <form method="POST" action="{{ route('storefront.cart.clear', $store->slug) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="public-cart-clear">Vaciar carrito</button>
                    </form>
                @endif
            </div>

            @if($cart['adjusted'])
                <div class="public-cart-alert warning" role="status" data-auto-dismiss-alert>Ajustamos el carrito porque cambió la disponibilidad de una variante.</div>
            @endif

            @if($cart['items']->isEmpty())
                <div class="public-cart-empty"><span aria-hidden="true">◇</span><h3>Tu carrito está vacío</h3><p>Selecciona una talla, color y cantidad desde el catálogo.</p><a href="#catalogo">Explorar productos</a></div>
            @else
                <div class="public-cart-layout">
                    <div class="public-cart-items">
                        @foreach($cart['items'] as $item)
                            <article class="public-cart-item">
                                <div class="public-cart-item-image">
                                    @if($item['image'])<img src="{{ asset('storage/'.$item['image']) }}" alt="{{ $item['name'] }}">@else<span aria-hidden="true">✦</span>@endif
                                </div>
                                <div class="public-cart-item-info"><small>{{ $item['code'] }}</small><h3>{{ $item['name'] }}</h3><p><b>{{ $item['size'] }}</b> / {{ $item['color'] }} · {{ $item['stock'] }} disponibles</p></div>
                                <strong>{{ $store->simbolo_moneda }} {{ number_format($item['unit_price'], 2) }}</strong>
                                <div class="public-cart-item-actions">
                                    <form method="POST" action="{{ route('storefront.cart.update', ['slug' => $store->slug, 'variant' => $item['variant_id']]) }}" class="public-cart-quantity public-cart-quantity-auto" data-cart-quantity-form>
                                        @csrf
                                        @method('PATCH')
                                        <label><span class="sr-only">Cantidad de {{ $item['name'] }}</span><input type="number" name="cantidad" value="{{ $item['quantity'] }}" min="1" max="{{ min(99, $item['stock']) }}" required></label>
                                        <button type="submit" class="sr-only">Guardar cantidad</button>
                                    </form>
                                    <a class="public-cart-edit-link" href="{{ route('storefront.product.show', ['slug' => $store->slug, 'code' => $item['code'], 'variante' => $item['variant_id'], 'cantidad' => $item['quantity']]) }}">Actualizar</a>
                                </div>
                                <strong class="public-cart-line-total">{{ $store->simbolo_moneda }} {{ number_format($item['subtotal'], 2) }}</strong>
                                <form method="POST" action="{{ route('storefront.cart.destroy', ['slug' => $store->slug, 'variant' => $item['variant_id']]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="public-cart-remove" aria-label="Eliminar {{ $item['name'] }}">Eliminar</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                    <aside class="public-cart-summary">
                        <span>Resumen</span>
                        <div><p>Unidades</p><strong>{{ $cart['units'] }}</strong></div>
                        <div><p>Subtotal</p><strong>{{ $store->simbolo_moneda }} {{ number_format($cart['subtotal'], 2) }}</strong></div>
                        <small>El stock todavía no está reservado. Se validará nuevamente al confirmar el pedido.</small>
                        <a href="#checkout" class="public-cart-checkout-link">Continuar con tus datos</a>
                    </aside>
                </div>
            @endif
        </section>

        @if($cart['items']->isNotEmpty())
            <section id="checkout" class="public-checkout" aria-labelledby="public-checkout-title">
                <div class="public-checkout-heading"><span class="public-store-eyebrow">Compra como invitado</span><h2 id="public-checkout-title">Contacto y entrega</h2><p>Usaremos estos datos únicamente para preparar y entregar tu pedido.</p></div>

                @if(session('checkout_success'))<div class="public-cart-alert success" role="status" data-auto-dismiss-alert>{{ session('checkout_success') }}</div>@endif
                @if($errors->hasAny(['cliente.*', 'entrega.*', 'fiscal.*', 'acepta_privacidad', 'carrito', 'pedido']))
                    <div class="public-cart-alert error" role="alert"><strong>Revisa los datos del pedido.</strong><span>{{ $errors->first() }}</span></div>
                @endif

                <form method="POST" action="{{ route('storefront.checkout.data.store', $store->slug) }}" class="public-checkout-form" data-customer-lookup-url="{{ route('storefront.customers.lookup', $store->slug) }}">
                    @csrf
                    <input type="hidden" name="confirmar_pedido" value="1">
                    <fieldset>
                        <legend><span>1</span> Datos de contacto</legend>
                        <div class="public-checkout-fields two-columns">
                            <label class="span-two">DNI / Documento de identidad *<input type="text" name="cliente[dni]" value="{{ old('cliente.dni', data_get($checkout, 'cliente.dni')) }}" minlength="5" maxlength="30" placeholder="Ej. 1712345678" autocomplete="off" data-customer-dni required><small data-customer-lookup-status aria-live="polite"></small>@error('cliente.dni')<small>{{ $message }}</small>@enderror</label>
                            <label>Nombres *<input type="text" name="cliente[nombre]" value="{{ old('cliente.nombre', data_get($checkout, 'cliente.nombre')) }}" maxlength="100" autocomplete="given-name" required>@error('cliente.nombre')<small>{{ $message }}</small>@enderror</label>
                            <label>Apellidos *<input type="text" name="cliente[apellido]" value="{{ old('cliente.apellido', data_get($checkout, 'cliente.apellido')) }}" maxlength="100" autocomplete="family-name" required>@error('cliente.apellido')<small>{{ $message }}</small>@enderror</label>
                            <label>Correo electrónico *<input type="email" name="cliente[email]" value="{{ old('cliente.email', data_get($checkout, 'cliente.email')) }}" maxlength="150" autocomplete="email" required>@error('cliente.email')<small>{{ $message }}</small>@enderror</label>
                            <label>Teléfono / WhatsApp *<input type="tel" name="cliente[telefono]" value="{{ old('cliente.telefono', data_get($checkout, 'cliente.telefono')) }}" maxlength="21" autocomplete="tel" placeholder="+593991234567" required>@error('cliente.telefono')<small>{{ $message }}</small>@enderror</label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend><span>2</span> Dirección de entrega</legend>
                        <div class="public-checkout-fields three-columns">
                            <label>País *<select name="entrega[pais_codigo]" autocomplete="country" required data-delivery-country>@foreach($countries as $code => $country)<option value="{{ $code }}" @selected(old('entrega.pais_codigo', data_get($checkout, 'entrega.pais_codigo')) === $code)>{{ $country }}</option>@endforeach</select>@error('entrega.pais_codigo')<small>{{ $message }}</small>@enderror</label>
                            <label>Provincia / Estado *<select name="entrega[provincia]" autocomplete="address-level1" required data-delivery-province data-current-value="{{ old('entrega.provincia', data_get($checkout, 'entrega.provincia')) }}"><option value="">Selecciona una provincia</option></select><input type="text" value="{{ old('entrega.provincia', data_get($checkout, 'entrega.provincia')) }}" maxlength="100" autocomplete="address-level1" placeholder="Escribe la provincia o estado" data-delivery-province-manual hidden disabled>@error('entrega.provincia')<small>{{ $message }}</small>@enderror</label>
                            <label>Ciudad / Cantón *<select name="entrega[ciudad]" autocomplete="address-level2" required data-delivery-city data-current-value="{{ old('entrega.ciudad', data_get($checkout, 'entrega.ciudad')) }}"><option value="">Selecciona primero una provincia</option></select><input type="text" value="{{ old('entrega.ciudad', data_get($checkout, 'entrega.ciudad')) }}" maxlength="100" autocomplete="address-level2" placeholder="Escribe la ciudad o cantón" data-delivery-city-manual hidden disabled>@error('entrega.ciudad')<small>{{ $message }}</small>@enderror</label>
                            <label class="span-two">Dirección completa *<input type="text" name="entrega[direccion]" value="{{ old('entrega.direccion', data_get($checkout, 'entrega.direccion')) }}" maxlength="255" autocomplete="street-address" placeholder="Calle principal, número y sector" required>@error('entrega.direccion')<small>{{ $message }}</small>@enderror</label>
                            <label>Código postal<input type="text" name="entrega[codigo_postal]" value="{{ old('entrega.codigo_postal', data_get($checkout, 'entrega.codigo_postal')) }}" maxlength="20" autocomplete="postal-code">@error('entrega.codigo_postal')<small>{{ $message }}</small>@enderror</label>
                            <label class="span-three">Referencia para la entrega<input type="text" name="entrega[referencia]" value="{{ old('entrega.referencia', data_get($checkout, 'entrega.referencia')) }}" maxlength="255" placeholder="Edificio, piso, color de puerta o punto de referencia">@error('entrega.referencia')<small>{{ $message }}</small>@enderror</label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend><span>3</span> Datos fiscales</legend>
                        @php($receiptType = old('fiscal.tipo_comprobante', data_get($checkout, 'fiscal.tipo_comprobante', 'boleta')))
                        @php($useContactData = (bool) old('fiscal.usar_datos_contacto', data_get($checkout, 'fiscal.usar_datos_contacto', true)))
                        <input type="hidden" name="fiscal[requiere_factura]" value="{{ $receiptType === 'factura' ? '1' : '0' }}" data-invoice-legacy>
                        <div class="public-checkout-fields two-columns public-receipt-choice">
                            <label>Tipo de comprobante *<select name="fiscal[tipo_comprobante]" data-receipt-type required><option value="ticket" @selected($receiptType === 'ticket')>Ticket</option><option value="boleta" @selected($receiptType === 'boleta')>Nota de venta / Boleta</option><option value="factura" @selected($receiptType === 'factura')>Factura</option></select>@error('fiscal.tipo_comprobante')<small>{{ $message }}</small>@enderror</label>
                            <div class="public-billing-source"><span>Datos para el comprobante *</span><label><input type="radio" name="fiscal[usar_datos_contacto]" value="1" data-billing-source @checked($useContactData)> Usar mis datos de contacto y entrega</label><label><input type="radio" name="fiscal[usar_datos_contacto]" value="0" data-billing-source @checked(!$useContactData)> Usar otros datos</label></div>
                        </div>
                        <p class="public-billing-help" data-billing-summary></p>
                        <div class="public-checkout-fields two-columns public-fiscal-fields" data-billing-custom>
                            <label>Tipo de identificación<select name="fiscal[tipo_identificacion]"><option value="">Seleccionar</option><option value="cedula_dni" @selected(old('fiscal.tipo_identificacion', data_get($checkout, 'fiscal.tipo_identificacion')) === 'cedula_dni')>Cédula / DNI</option><option value="ruc_nit" @selected(old('fiscal.tipo_identificacion', data_get($checkout, 'fiscal.tipo_identificacion')) === 'ruc_nit')>RUC / NIT</option><option value="pasaporte" @selected(old('fiscal.tipo_identificacion', data_get($checkout, 'fiscal.tipo_identificacion')) === 'pasaporte')>Pasaporte</option><option value="otro" @selected(old('fiscal.tipo_identificacion', data_get($checkout, 'fiscal.tipo_identificacion')) === 'otro')>Otro</option></select>@error('fiscal.tipo_identificacion')<small>{{ $message }}</small>@enderror</label>
                            <label>Número de identificación<input type="text" name="fiscal[identificacion]" value="{{ old('fiscal.identificacion', data_get($checkout, 'fiscal.identificacion')) }}" maxlength="30">@error('fiscal.identificacion')<small>{{ $message }}</small>@enderror</label>
                            <label>Nombre / Razón social<input type="text" name="fiscal[razon_social]" value="{{ old('fiscal.razon_social', data_get($checkout, 'fiscal.razon_social')) }}" maxlength="180">@error('fiscal.razon_social')<small>{{ $message }}</small>@enderror</label>
                            <label>Dirección fiscal<input type="text" name="fiscal[direccion_fiscal]" value="{{ old('fiscal.direccion_fiscal', data_get($checkout, 'fiscal.direccion_fiscal')) }}" maxlength="255">@error('fiscal.direccion_fiscal')<small>{{ $message }}</small>@enderror</label>
                        </div>
                    </fieldset>

                    <div class="public-checkout-footer">
                        <label><input type="checkbox" name="acepta_privacidad" value="1" @checked(old('acepta_privacidad')) required> Autorizo el uso de estos datos para gestionar esta compra y su entrega.</label>
                        @error('acepta_privacidad')<small>{{ $message }}</small>@enderror
                        <button type="submit">Confirmar pedido</button>
                        <p>Al confirmar, reservaremos las variantes durante 15 minutos y te llevaremos a los métodos de pago.</p>
                        @error('pedido')<small>{{ $message }}</small>@enderror
                    </div>
                </form>
                <script type="application/json" data-ecuador-locations>@json($ecuadorLocations, JSON_UNESCAPED_UNICODE)</script>
            </section>
        @endif

        @endif
        <section class="public-store-grid public-store-service-grid">
            @php($locationText = collect([$contact['ubicacion']['ciudad'], $contact['ubicacion']['provincia'], $contact['ubicacion']['pais']])->filter()->join(', '))
            @php($mapQuery = collect([$contact['direccion'], $locationText])->filter()->join(', '))
            <article id="contacto" class="public-store-card public-contact-card">
                <header class="service-card-heading"><span class="service-heading-icon">☏</span><div><h2>Contacto</h2><p>Estamos para ayudarte. Escríbenos o llámanos.</p></div></header>
                <dl class="contact-options">
                    @if($contact['direccion'] || $locationText)<div class="contact-option"><span class="service-icon">●</span><dt>Dirección<small>{{ $contact['direccion'] }}</small><small>{{ $locationText }}</small></dt>@if($mapQuery)<dd><a class="contact-action" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapQuery) }}" target="_blank" rel="noopener"><span>◫</span> Ver en Maps</a></dd>@endif</div>@endif
                    @if($contact['telefono'])<div class="contact-option"><span class="service-icon">☎</span><dt>Teléfono<small><a href="tel:{{ $contact['telefono'] }}">{{ $contact['telefono'] }}</a></small></dt><dd><a class="contact-action" href="tel:{{ $contact['telefono'] }}"><span>☎</span> Llamar</a></dd></div>@endif
                    @if($contact['whatsapp'])<div class="contact-option"><span class="service-icon whatsapp">◉</span><dt>WhatsApp<small>{{ $contact['whatsapp'] }}</small></dt><dd><a class="contact-action whatsapp" href="https://wa.me/{{ preg_replace('/\D+/', '', $contact['whatsapp']) }}" target="_blank" rel="noopener"><span>◉</span> Escribir</a></dd></div>@endif
                    @if($contact['email'])<div class="contact-option"><span class="service-icon">✉</span><dt>Correo<small>{{ $contact['email'] }}</small></dt><dd><a class="contact-action" href="mailto:{{ $contact['email'] }}"><span>✉</span> Enviar</a></dd></div>@endif
                </dl>
            </article>

            @php($weeklySchedules = collect($days)->map(fn($label, $day) => ['day' => $day, 'label' => $label, 'active' => (bool) data_get($store->horarios, $day.'.activo'), 'open' => data_get($store->horarios, $day.'.apertura'), 'close' => data_get($store->horarios, $day.'.cierre')]))
            @php($activeSchedules = $weeklySchedules->where('active', true))
            @php($sharedHours = $activeSchedules->map(fn($schedule) => $schedule['open'].'|'.$schedule['close'])->unique())
            @php($uniformEveryDay = $activeSchedules->count() === count($days) && $sharedHours->count() === 1)
            <article id="horarios" class="public-store-card public-schedule-card">
                <header class="service-card-heading"><span class="service-heading-icon">◷</span><div><h2>Horario de atención</h2><p>Te atendemos con gusto en el siguiente horario.</p></div></header>
                @if($uniformEveryDay)
                    @php($dailySchedule = $activeSchedules->first())
                    <div class="schedule-summary"><span class="schedule-calendar">▣</span><div><strong>Lunes a Domingo</strong><b>{{ $dailySchedule['open'] }} a {{ $dailySchedule['close'] }}</b></div><em><i></i> Atención todos los días</em></div>
                @elseif($activeSchedules->isNotEmpty())
                    <div class="schedule-week">
                        @foreach($weeklySchedules as $schedule)<div class="{{ $schedule['active'] ? 'is-open' : 'is-closed' }}"><span>{{ $schedule['label'] }}</span><strong>{{ $schedule['active'] ? $schedule['open'].' – '.$schedule['close'] : 'Cerrado' }}</strong></div>@endforeach
                    </div>
                @else
                    <div class="schedule-empty"><span>◷</span><div><strong>Horario por confirmar</strong><p>Comunícate con la tienda para coordinar tu atención.</p></div></div>
                @endif
            </article>
        </section>

        @if($store->condiciones_venta)
            <section id="condiciones" class="public-store-conditions"><span class="public-store-eyebrow">Información importante</span><h2>Condiciones de compra</h2><div>
                @foreach($conditionLabels as $field => $label)
                    @if(data_get($store->condiciones_venta, $field))<article><h3>{{ $label }}</h3><p>{{ data_get($store->condiciones_venta, $field) }}</p></article>@endif
                @endforeach
            </div></section>
        @endif
    </main>

    @include('storefront._footer')
</body>
</html>
