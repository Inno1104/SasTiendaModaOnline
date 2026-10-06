<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->nombre }} · {{ $store->nombre }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
    <link rel="stylesheet" href="{{ asset('css/storefront-product.css') }}">
    <link rel="stylesheet" href="{{ asset('css/storefront-product-marketplace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/storefront-product-compact.css') }}?v={{ filemtime(public_path('css/storefront-product-compact.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/storefront-cart-icon.css') }}">
    <script src="{{ asset('js/storefront-product.js') }}?v={{ filemtime(public_path('js/storefront-product.js')) }}" defer></script>
</head>
@php
    $availableVariants = $product->variantes
        ->filter(fn ($variant) => ! preg_match('/[,;|]+/u', (string) $variant->talla))
        ->map(fn ($variant) => [
        'id' => $variant->id,
        'size' => $variant->talla,
        'sizeDetail' => $variant->detalle_talla,
        'color' => $variant->color,
        'stock' => $variant->stockDisponibleVenta(),
    ])->values();
    $images = $product->imagenes->map(fn ($image) => [
        'url' => asset('storage/'.$image->ruta),
        'size' => $image->talla,
        'color' => $image->color,
        'primary' => $image->principal,
    ])->values();
    if ($images->isEmpty() && $product->imagen) $images->push(['url' => asset('storage/'.$product->imagen), 'size' => null, 'color' => null, 'primary' => true]);
    $totalStock = $availableVariants->sum('stock');
    $hasOffer = $product->precio_oferta !== null && (float) $product->precio_oferta > 0 && (float) $product->precio_oferta < (float) $product->precio_venta;
    $discount = $hasOffer ? (int) round((1 - ((float) $product->precio_oferta / (float) $product->precio_venta)) * 100) : 0;
    $editingVariantId = request()->integer('variante');
    $editingCartItem = $cart['items']->first(fn ($item) => $item['variant_id'] === $editingVariantId && $item['product_id'] === $product->id);
@endphp
<body style="--store-primary:{{ $store->color_primario ?: '#e72e87' }};--store-color:{{ $store->color_primario ?: '#e72e87' }}">
    <header class="product-store-header">
        <a class="product-store-brand" href="{{ $store->publicUrl() }}">
            @if($store->logo)<img src="{{ asset('storage/'.$store->logo) }}" alt="Logo de {{ $store->nombre }}">@else<span><i class="fa-solid fa-bag-shopping"></i></span>@endif
            <div><strong>{{ $store->nombre }}</strong><small>Tienda online</small></div>
        </a>
        <nav aria-label="Navegación principal"><a href="{{ $store->publicUrl() }}#catalogo">Catálogo</a><a href="{{ route('storefront.orders.lookup', $store->slug) }}">Mi pedido</a><a href="{{ $store->publicUrl() }}#contacto">Contacto</a><a href="{{ $store->publicUrl() }}#condiciones">Condiciones</a><a class="detail-cart-link detail-cart-icon" href="{{ route('storefront.cart.show', $store->slug) }}" aria-label="Carrito con {{ $cart['units'] }} artículos"><i class="fa-solid fa-cart-shopping"></i><b>{{ $cart['units'] }}</b></a></nav>
    </header>

    <main class="product-detail-shell" data-product-detail data-cart-editing="{{ $editingCartItem ? 'true' : 'false' }}" data-variants='@json($availableVariants)' data-images='@json($images)'>
        <nav class="product-breadcrumb" aria-label="Migas de pan"><a href="{{ $store->publicUrl() }}">Inicio</a><span>›</span><a href="{{ $store->publicUrl() }}?categoria={{ $product->categoria?->slug }}#catalogo">{{ $product->categoria?->nombre }}</a><span>›</span><strong>{{ $product->nombre }}</strong></nav>

        <div class="product-detail-grid">
            <section class="product-detail-gallery" aria-label="Imágenes del producto">
                <div class="product-thumbnails" data-product-thumbnails></div>
                <div class="product-main-image">
                    @if($images->isNotEmpty())<img src="{{ $images->first()['url'] }}" alt="{{ $product->nombre }}" data-product-main-image>@else<div class="product-no-image"><i class="fa-regular fa-image"></i><span>Sin imagen disponible</span></div>@endif
                </div>
            </section>

            <section class="product-detail-info">
                <span class="product-detail-category">{{ $product->categoria?->nombre }}@if($product->marca) · {{ $product->marca->nombre }}@endif</span>
                <h1>{{ $product->nombre }}</h1>
                <p class="product-detail-code">{{ $product->codigo }}</p>
                @if($product->descripcion)<p class="product-detail-description">{{ $product->descripcion }}</p>@endif
                <div class="product-detail-price"><strong>{{ $store->simbolo_moneda }}{{ number_format($product->precioActual(), 2) }}</strong>@if($hasOffer)<del>{{ $store->simbolo_moneda }}{{ number_format((float) $product->precio_venta, 2) }}</del><span>{{ $discount }}% de descuento</span>@endif</div>

                <section class="product-offer-panel" aria-label="Opciones de compra">
                    <header class="product-offer-heading">
                        <strong><i class="fa-solid fa-bolt"></i> {{ $hasOffer ? 'Mega oferta' : 'Compra disponible' }}</strong>
                        <span><i></i><b class="fa-regular fa-clock" aria-hidden="true"></b> Stock actualizado en tiempo real</span>
                    </header>
                    <div class="product-offer-content">
                        <div class="product-choice-block product-color-choice"><div class="product-choice-heading"><strong>Color: <span data-selected-color>Selecciona un color</span></strong></div><div class="product-color-cards" data-product-colors></div></div>
                        <div class="product-choice-block"><div class="product-choice-heading"><strong>Talla:</strong><button type="button" class="product-size-guide" data-size-guide><i class="fa-solid fa-ruler-horizontal"></i> Guía de tallas</button></div><div class="product-size-buttons" data-product-sizes></div></div>
                        <div class="product-fit-note" data-fit-note><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Selecciona un color y una talla para consultar la combinación y su disponibilidad.</p><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></div>

                        <form method="POST" action="{{ $editingCartItem ? route('storefront.cart.update', ['slug' => $store->slug, 'variant' => $editingCartItem['variant_id']]) : route('storefront.cart.store', $store->slug) }}" class="product-detail-cart" data-detail-cart-form>
                            @csrf
                            @if($editingCartItem) @method('PATCH') @endif
                            <input type="hidden" name="{{ $editingCartItem ? 'nueva_variante_id' : 'variante_id' }}" data-selected-variant required>
                            <div class="product-stock-message"><i class="fa-solid fa-circle-check"></i><p data-detail-stock>{{ $totalStock > 0 ? $totalStock.' unidades disponibles' : 'Producto agotado' }}</p></div>
                            <div class="product-quantity-field"><strong>Cantidad:</strong><div class="product-quantity-control"><button type="button" data-quantity-minus aria-label="Disminuir cantidad">−</button><select name="cantidad" data-detail-quantity disabled aria-label="Cantidad"><option value="1">1</option></select><button type="button" data-quantity-plus aria-label="Aumentar cantidad">+</button></div><small data-quantity-limit>Selecciona una variante</small></div>
                            <button type="submit" disabled data-detail-add><i class="fa-solid fa-cart-plus"></i> Selecciona color y talla</button>
                        </form>
                    </div>
                </section>
                <div class="product-purchase-benefits"><span><i class="fa-solid fa-shield-halved"></i> Compra segura</span><span><i class="fa-solid fa-box"></i> Stock verificado</span><span><i class="fa-solid fa-headset"></i> Atención de la tienda</span></div>
            </section>
        </div>

        @if($relatedProducts->isNotEmpty())
            <section class="related-products" aria-labelledby="related-products-title">
                <header class="related-products-heading">
                    <div><span>También te puede gustar</span><h2 id="related-products-title">Productos similares</h2><p>Alternativas de la misma categoría, estilo o colección disponibles en esta tienda.</p></div>
                    <a href="{{ $store->publicUrl() }}?categoria={{ $product->categoria?->slug }}#catalogo">Ver catálogo <i class="fa-solid fa-arrow-right"></i></a>
                </header>
                <div class="related-products-grid">
                    @foreach($relatedProducts as $related)
                        @php
                            $relatedImage = $related->imagenes->first()?->ruta ?: $related->imagen;
                            $relatedStock = $related->variantes->sum(fn ($variant) => max(0, (int) $variant->stock - (int) ($variant->stock_reservado ?? 0)));
                            $relatedColors = $related->variantes->pluck('color')->filter()->unique(fn ($color) => mb_strtolower(trim($color)))->take(3);
                            $relatedHasOffer = $related->precio_oferta !== null && (float) $related->precio_oferta > 0 && (float) $related->precio_oferta < (float) $related->precio_venta;
                        @endphp
                        <article class="related-product-card">
                            <a class="related-product-image" href="{{ route('storefront.product.show', ['slug' => $store->slug, 'code' => $related->codigo]) }}">
                                @if($relatedImage)<img src="{{ asset('storage/'.$relatedImage) }}" alt="{{ $related->nombre }}" loading="lazy">@else<span><i class="fa-regular fa-image"></i> Sin imagen</span>@endif
                                @if($relatedHasOffer)<b>Oferta</b>@endif
                            </a>
                            <div class="related-product-body">
                                <small>{{ $related->categoria?->nombre }}@if($related->marca) · {{ $related->marca->nombre }}@endif</small>
                                <h3><a href="{{ route('storefront.product.show', ['slug' => $store->slug, 'code' => $related->codigo]) }}">{{ $related->nombre }}</a></h3>
                                <div class="related-product-meta"><strong>{{ $store->simbolo_moneda }}{{ number_format($related->precioActual(), 2) }}</strong>@if($relatedHasOffer)<del>{{ $store->simbolo_moneda }}{{ number_format((float) $related->precio_venta, 2) }}</del>@endif</div>
                                <div class="related-product-footer"><span><i class="fa-solid fa-box"></i> {{ $relatedStock }} disponibles</span>@if($relatedColors->isNotEmpty())<small>{{ $relatedColors->join(' · ') }}</small>@endif</div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
    @include('storefront._footer')
</body>
</html>
