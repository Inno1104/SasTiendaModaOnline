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
</head>
<body style="--store-color: {{ $store->color_primario }}">
    <header class="public-store-header">
        <a href="{{ $store->publicUrl() }}" class="public-store-brand">
            <img src="{{ $store->logo ? asset('storage/'.$store->logo) : asset('images/store-logo-default.svg') }}" alt="Logo de {{ $store->nombre }}">
            <span><strong>{{ $store->nombre }}</strong><small>Tienda online</small></span>
        </a>
        <nav aria-label="Navegación principal"><a href="#catalogo">Catálogo</a><a href="#carrito">Carrito ({{ $cart['units'] }})</a><a href="#contacto">Contacto</a><a href="#horarios">Horarios</a><a href="#condiciones">Condiciones</a></nav>
    </header>

    <main>
        @if(session('online_order'))
            <section id="pedido-confirmado" class="public-order-success" role="status">
                <span aria-hidden="true">✓</span>
                <div><small>Pedido recibido</small><h2>{{ session('online_order.numero') }}</h2><p>Estado: <strong>{{ session('online_order.estado') }}</strong> · Total: <strong>{{ session('online_order.total') }}</strong></p><p>Reservamos las variantes hasta las <strong>{{ session('online_order.reserva_expira') }}</strong>. Completa el pago antes de esa hora.</p></div>
            </section>
        @endif
        <section class="public-store-hero">
            <div><span class="public-store-eyebrow">Bienvenido</span><h1>{{ $store->nombre }}</h1><p>{{ $store->descripcion_publica ?: 'Descubre nuestros productos y consulta su disponibilidad en tiempo real.' }}</p><a href="#catalogo" class="public-store-button">Ver catálogo</a></div>
            <div class="public-store-logo"><img src="{{ $store->logo ? asset('storage/'.$store->logo) : asset('images/store-logo-default.svg') }}" alt="{{ $store->nombre }}"></div>
        </section>

        <section id="catalogo" class="public-catalog" aria-labelledby="public-catalog-title">
            @if(session('cart_success'))<div class="public-cart-alert success" role="status">{{ session('cart_success') }}</div>@endif
            @if($errors->any())<div class="public-cart-alert error" role="alert"><strong>No pudimos actualizar el carrito.</strong><span>{{ $errors->first() }}</span></div>@endif
            <div class="public-catalog-heading"><div><span class="public-store-eyebrow">Colección disponible</span><h2 id="public-catalog-title">Nuestro catálogo</h2><p>Precios y existencias actualizados por talla y color.</p></div><strong>{{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}</strong></div>

            <form method="GET" action="{{ $store->publicUrl() }}" class="public-catalog-search" role="search">
                <label><span class="sr-only">Buscar productos</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Buscar por producto, categoría o marca..."></label>
                @if($filters['categoria'] ?? null)<input type="hidden" name="categoria" value="{{ $filters['categoria'] }}">@endif
                <button type="submit">Buscar</button>
                @if(($filters['q'] ?? null) || ($filters['categoria'] ?? null))<a href="{{ $store->publicUrl() }}#catalogo">Limpiar</a>@endif
            </form>

            <nav class="public-categories" aria-label="Categorías del catálogo">
                <a href="{{ $store->publicUrl() }}#catalogo" class="{{ empty($filters['categoria']) ? 'active' : '' }}">Todos <span>{{ $categories->sum('productos_publicos_count') }}</span></a>
                @foreach($categories as $category)
                    <a href="{{ $store->publicUrl() }}?categoria={{ $category->slug }}#catalogo" class="{{ ($filters['categoria'] ?? null) === $category->slug ? 'active' : '' }}">{{ $category->nombre }} <span>{{ $category->productos_publicos_count }}</span></a>
                @endforeach
            </nav>

            <div class="public-products-grid">
                @forelse($products as $product)
                    @php
                        $stock = $product->variantes->sum(fn ($variant) => $variant->stockDisponibleVenta());
                        $hasOffer = $product->precio_oferta !== null && (float) $product->precio_oferta > 0 && (float) $product->precio_oferta < (float) $product->precio_venta;
                        $discount = $hasOffer ? (int) round((1 - ((float) $product->precio_oferta / (float) $product->precio_venta)) * 100) : 0;
                    @endphp
                    <article class="public-product-card">
                        <div class="public-product-media">
                            @if($product->imagen)<img src="{{ asset('storage/'.$product->imagen) }}" alt="{{ $product->nombre }}" loading="lazy">@else<div><span aria-hidden="true">✦</span><small>Sin imagen</small></div>@endif
                            @if($hasOffer)<b>-{{ $discount }}%</b>@elseif($product->destacado)<b>Destacado</b>@endif
                            <span class="public-stock-badge {{ $stock > 0 ? 'available' : 'out' }}">{{ $stock > 0 ? $stock.' disponibles' : 'Agotado' }}</span>
                        </div>
                        <div class="public-product-body">
                            <small>{{ $product->categoria?->nombre }}@if($product->marca) · {{ $product->marca->nombre }}@endif</small>
                            <h3>{{ $product->nombre }}</h3>
                            @if($product->descripcion)<p>{{ \Illuminate\Support\Str::limit($product->descripcion, 90) }}</p>@endif
                            <div class="public-product-price">
                                <strong>{{ $store->simbolo_moneda }} {{ number_format($product->precioActual(), 2) }}</strong>
                                @if($hasOffer)<del>{{ $store->simbolo_moneda }} {{ number_format((float) $product->precio_venta, 2) }}</del>@endif
                            </div>
                            <div class="public-variants" aria-label="Variantes de {{ $product->nombre }}">
                                @forelse($product->variantes as $variant)
                                    @php($variantAvailable = $variant->stockDisponibleVenta())
                                    <span class="{{ $variantAvailable > 0 ? '' : 'sold-out' }}" title="{{ $variantAvailable > 0 ? $variantAvailable.' disponibles' : 'Agotada' }}"><b>{{ $variant->talla }}</b> / {{ $variant->color }} <small>{{ $variantAvailable }}</small></span>
                                @empty
                                    <span class="sold-out">Sin variantes disponibles</span>
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('storefront.cart.store', $store->slug) }}" class="public-add-cart-form">
                                @csrf
                                <label>
                                    <span class="sr-only">Talla y color de {{ $product->nombre }}</span>
                                    <select name="variante_id" required {{ $stock < 1 ? 'disabled' : '' }}>
                                        @foreach($product->variantes as $variant)
                                            @php($variantAvailable = $variant->stockDisponibleVenta())
                                            <option value="{{ $variant->id }}" {{ $variantAvailable < 1 ? 'disabled' : '' }}>{{ $variant->talla }} / {{ $variant->color }} · {{ $variantAvailable }} disponibles</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span class="sr-only">Cantidad</span>
                                    <input type="number" name="cantidad" value="1" min="1" max="99" inputmode="numeric" required {{ $stock < 1 ? 'disabled' : '' }}>
                                </label>
                                <button type="submit" {{ $stock < 1 ? 'disabled' : '' }}>{{ $stock > 0 ? 'Agregar' : 'Agotado' }}</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="public-catalog-empty"><span aria-hidden="true">⌕</span><h3>No encontramos productos</h3><p>Prueba con otra búsqueda o categoría.</p><a href="{{ $store->publicUrl() }}#catalogo">Ver todo el catálogo</a></div>
                @endforelse
            </div>

            @if($products->hasPages())<div class="public-catalog-pagination">{{ $products->onEachSide(1)->links() }}</div>@endif
        </section>

        <section id="carrito" class="public-cart" aria-labelledby="public-cart-title">
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
                <div class="public-cart-alert warning" role="status">Ajustamos el carrito porque cambió la disponibilidad de una variante.</div>
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
                                <form method="POST" action="{{ route('storefront.cart.update', ['slug' => $store->slug, 'variant' => $item['variant_id']]) }}" class="public-cart-quantity">
                                    @csrf
                                    @method('PATCH')
                                    <label><span class="sr-only">Cantidad de {{ $item['name'] }}</span><input type="number" name="cantidad" value="{{ $item['quantity'] }}" min="1" max="{{ min(99, $item['stock']) }}" required></label>
                                    <button type="submit">Actualizar</button>
                                </form>
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

                @if(session('checkout_success'))<div class="public-cart-alert success" role="status">{{ session('checkout_success') }}</div>@endif
                @if($errors->hasAny(['cliente.*', 'entrega.*', 'fiscal.*', 'acepta_privacidad', 'carrito']))
                    <div class="public-cart-alert error" role="alert"><strong>Revisa los datos del pedido.</strong><span>{{ $errors->first() }}</span></div>
                @endif

                <form method="POST" action="{{ route('storefront.checkout.data.store', $store->slug) }}" class="public-checkout-form">
                    @csrf
                    <fieldset>
                        <legend><span>1</span> Datos de contacto</legend>
                        <div class="public-checkout-fields two-columns">
                            <label>Nombres *<input type="text" name="cliente[nombre]" value="{{ old('cliente.nombre', data_get($checkout, 'cliente.nombre')) }}" maxlength="100" autocomplete="given-name" required>@error('cliente.nombre')<small>{{ $message }}</small>@enderror</label>
                            <label>Apellidos *<input type="text" name="cliente[apellido]" value="{{ old('cliente.apellido', data_get($checkout, 'cliente.apellido')) }}" maxlength="100" autocomplete="family-name" required>@error('cliente.apellido')<small>{{ $message }}</small>@enderror</label>
                            <label>Correo electrónico *<input type="email" name="cliente[email]" value="{{ old('cliente.email', data_get($checkout, 'cliente.email')) }}" maxlength="150" autocomplete="email" required>@error('cliente.email')<small>{{ $message }}</small>@enderror</label>
                            <label>Teléfono / WhatsApp *<input type="tel" name="cliente[telefono]" value="{{ old('cliente.telefono', data_get($checkout, 'cliente.telefono')) }}" maxlength="21" autocomplete="tel" placeholder="+593991234567" required>@error('cliente.telefono')<small>{{ $message }}</small>@enderror</label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend><span>2</span> Dirección de entrega</legend>
                        <div class="public-checkout-fields three-columns">
                            <label>País *<select name="entrega[pais_codigo]" autocomplete="country" required>@foreach($countries as $code => $country)<option value="{{ $code }}" @selected(old('entrega.pais_codigo', data_get($checkout, 'entrega.pais_codigo')) === $code)>{{ $country }}</option>@endforeach</select>@error('entrega.pais_codigo')<small>{{ $message }}</small>@enderror</label>
                            <label>Provincia / Estado *<input type="text" name="entrega[provincia]" value="{{ old('entrega.provincia', data_get($checkout, 'entrega.provincia')) }}" maxlength="100" autocomplete="address-level1" required>@error('entrega.provincia')<small>{{ $message }}</small>@enderror</label>
                            <label>Ciudad / Cantón *<input type="text" name="entrega[ciudad]" value="{{ old('entrega.ciudad', data_get($checkout, 'entrega.ciudad')) }}" maxlength="100" autocomplete="address-level2" required>@error('entrega.ciudad')<small>{{ $message }}</small>@enderror</label>
                            <label class="span-two">Dirección completa *<input type="text" name="entrega[direccion]" value="{{ old('entrega.direccion', data_get($checkout, 'entrega.direccion')) }}" maxlength="255" autocomplete="street-address" placeholder="Calle principal, número y sector" required>@error('entrega.direccion')<small>{{ $message }}</small>@enderror</label>
                            <label>Código postal<input type="text" name="entrega[codigo_postal]" value="{{ old('entrega.codigo_postal', data_get($checkout, 'entrega.codigo_postal')) }}" maxlength="20" autocomplete="postal-code">@error('entrega.codigo_postal')<small>{{ $message }}</small>@enderror</label>
                            <label class="span-three">Referencia para la entrega<input type="text" name="entrega[referencia]" value="{{ old('entrega.referencia', data_get($checkout, 'entrega.referencia')) }}" maxlength="255" placeholder="Edificio, piso, color de puerta o punto de referencia">@error('entrega.referencia')<small>{{ $message }}</small>@enderror</label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend><span>3</span> Datos fiscales</legend>
                        @php($invoiceRequested = (bool) old('fiscal.requiere_factura', data_get($checkout, 'fiscal.requiere_factura', false)))
                        <label class="public-checkout-toggle"><input type="hidden" name="fiscal[requiere_factura]" value="0"><input type="checkbox" name="fiscal[requiere_factura]" value="1" @checked($invoiceRequested)> Necesito factura con datos fiscales</label>
                        <div class="public-checkout-fields two-columns public-fiscal-fields">
                            <label>Tipo de identificación<select name="fiscal[tipo_identificacion]"><option value="">Seleccionar</option><option value="cedula_dni" @selected(old('fiscal.tipo_identificacion', data_get($checkout, 'fiscal.tipo_identificacion')) === 'cedula_dni')>Cédula / DNI</option><option value="ruc_nit" @selected(old('fiscal.tipo_identificacion', data_get($checkout, 'fiscal.tipo_identificacion')) === 'ruc_nit')>RUC / NIT</option><option value="pasaporte" @selected(old('fiscal.tipo_identificacion', data_get($checkout, 'fiscal.tipo_identificacion')) === 'pasaporte')>Pasaporte</option><option value="otro" @selected(old('fiscal.tipo_identificacion', data_get($checkout, 'fiscal.tipo_identificacion')) === 'otro')>Otro</option></select>@error('fiscal.tipo_identificacion')<small>{{ $message }}</small>@enderror</label>
                            <label>Número de identificación<input type="text" name="fiscal[identificacion]" value="{{ old('fiscal.identificacion', data_get($checkout, 'fiscal.identificacion')) }}" maxlength="30">@error('fiscal.identificacion')<small>{{ $message }}</small>@enderror</label>
                            <label>Nombre / Razón social<input type="text" name="fiscal[razon_social]" value="{{ old('fiscal.razon_social', data_get($checkout, 'fiscal.razon_social')) }}" maxlength="180">@error('fiscal.razon_social')<small>{{ $message }}</small>@enderror</label>
                            <label>Dirección fiscal<input type="text" name="fiscal[direccion_fiscal]" value="{{ old('fiscal.direccion_fiscal', data_get($checkout, 'fiscal.direccion_fiscal')) }}" maxlength="255">@error('fiscal.direccion_fiscal')<small>{{ $message }}</small>@enderror</label>
                        </div>
                    </fieldset>

                    <div class="public-checkout-footer">
                        <label><input type="checkbox" name="acepta_privacidad" value="1" @checked(old('acepta_privacidad')) required> Autorizo el uso de estos datos para gestionar esta compra y su entrega.</label>
                        @error('acepta_privacidad')<small>{{ $message }}</small>@enderror
                        <button type="submit">Guardar y continuar</button>
                        <p>Los datos se almacenan cifrados temporalmente. Aún no se genera ni cobra ningún pedido.</p>
                    </div>
                </form>

                @if(data_get($checkout, 'updated_at'))
                    <form method="POST" action="{{ route('storefront.orders.store', $store->slug) }}" class="public-order-confirmation">
                        @csrf
                        <input type="hidden" name="operacion_id" value="{{ $orderOperationId }}">
                        <div><strong>Crear pedido por {{ $store->simbolo_moneda }} {{ number_format($cart['subtotal'], 2) }}</strong><p>El pedido quedará en “Esperando pago” y sus variantes se reservarán durante 15 minutos.</p><label><input type="checkbox" name="confirma_pedido" value="1" required> Revisé productos, cantidades y dirección de entrega.</label>@error('confirma_pedido')<small>{{ $message }}</small>@enderror @error('operacion_id')<small>{{ $message }}</small>@enderror @error('pedido')<small>{{ $message }}</small>@enderror</div>
                        <button type="submit">Confirmar pedido</button>
                    </form>
                @endif
            </section>
        @endif

        <section class="public-store-grid">
            <article id="contacto" class="public-store-card"><h2>Contacto</h2><dl>
                @if($contact['direccion'])<div><dt>Dirección</dt><dd>{{ $contact['direccion'] }}</dd></div>@endif
                @if($contact['ubicacion']['ciudad'])<div><dt>Ubicación</dt><dd>{{ collect([$contact['ubicacion']['ciudad'], $contact['ubicacion']['provincia'], $contact['ubicacion']['pais']])->filter()->join(', ') }}</dd></div>@endif
                @if($contact['telefono'])<div><dt>Teléfono</dt><dd><a href="tel:{{ $contact['telefono'] }}">{{ $contact['telefono'] }}</a></dd></div>@endif
                @if($contact['whatsapp'])<div><dt>WhatsApp</dt><dd><a href="https://wa.me/{{ preg_replace('/\D+/', '', $contact['whatsapp']) }}" target="_blank" rel="noopener">{{ $contact['whatsapp'] }}</a></dd></div>@endif
                @if($contact['email'])<div><dt>Correo</dt><dd><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd></div>@endif
            </dl></article>

            <article id="horarios" class="public-store-card"><h2>Horarios de atención</h2><dl>
                @foreach($days as $day => $label)
                    @php($schedule = data_get($store->horarios, $day, []))
                    <div><dt>{{ $label }}</dt><dd>{{ data_get($schedule, 'activo') ? data_get($schedule, 'apertura').' – '.data_get($schedule, 'cierre') : 'Cerrado' }}</dd></div>
                @endforeach
            </dl></article>
        </section>

        @if($store->condiciones_venta)
            <section id="condiciones" class="public-store-conditions"><span class="public-store-eyebrow">Información importante</span><h2>Condiciones de compra</h2><div>
                @foreach($conditionLabels as $field => $label)
                    @if(data_get($store->condiciones_venta, $field))<article><h3>{{ $label }}</h3><p>{{ data_get($store->condiciones_venta, $field) }}</p></article>@endif
                @endforeach
            </div></section>
        @endif
    </main>

    <footer><strong>{{ $store->nombre }}</strong><span>© {{ now()->year }} · Todos los derechos reservados.</span></footer>
</body>
</html>
