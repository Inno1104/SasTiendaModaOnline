<footer class="public-store-footer">
    <div class="public-footer-main">
        <section class="public-footer-brand">
            <a href="{{ $store->publicUrl() }}"><span><img src="{{ $store->logo ? asset('storage/'.$store->logo) : asset('images/store-logo-default.svg') }}" alt="Logo de {{ $store->nombre }}"></span><strong>{{ $store->nombre }}</strong></a>
            <p>{{ $store->descripcion_publica ?: 'Tu tienda de moda con estilo, calidad y los mejores productos.' }}</p>
            @if($store->redes_sociales)<nav class="public-social-links" aria-label="Redes sociales">@foreach($store->redes_sociales as $network => $url)@if($url)<a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}">{{ ['facebook' => 'f', 'instagram' => '◎', 'tiktok' => '♪'][$network] ?? '↗' }}</a>@endif @endforeach</nav>@endif
        </section>
        <section><h3>Categorías</h3><nav>@foreach($categories->take(6) as $category)<a href="{{ $store->publicUrl() }}?categoria={{ $category->slug }}#catalogo">{{ $category->nombre }}</a>@endforeach</nav></section>
        <section><h3>Información</h3><nav><a href="{{ $store->publicUrl() }}#contacto">Sobre nosotros</a><a href="{{ $store->publicUrl() }}#condiciones">Política de privacidad</a><a href="{{ $store->publicUrl() }}#condiciones">Términos y condiciones</a><a href="{{ $store->publicUrl() }}#condiciones">Cambios y devoluciones</a><a href="{{ $store->publicUrl() }}#contacto">Contáctanos</a></nav></section>
        <section><h3>Ayuda</h3><nav><a href="{{ $store->publicUrl() }}#catalogo">Cómo comprar</a><a href="{{ route('storefront.cart.show', $store->slug) }}">Métodos de pago</a><a href="{{ $store->publicUrl() }}#condiciones">Envíos</a><a href="{{ route('storefront.cart.show', $store->slug) }}">Seguimiento de pedidos</a><a href="{{ $store->publicUrl() }}#contacto">Soporte al cliente</a></nav></section>
        <section class="public-footer-payments"><h3>Métodos de pago</h3><div class="payment-marks"><b>VISA</b><b>●●</b><b>AMEX</b><b>Pay</b><b>▣</b></div><div class="footer-trust"><span>▱ <small>Envíos<br>a todo el país</small></span><span>♢ <small>Compra<br>segura</small></span><span>◉ <small>Atención<br>personalizada</small></span></div></section>
    </div>
    <div class="public-footer-bottom"><span>© {{ now()->year }} {{ $store->nombre }}. Todos los derechos reservados.</span><nav><a href="{{ $store->publicUrl() }}#condiciones">Política de privacidad</a><a href="{{ $store->publicUrl() }}#condiciones">Términos y condiciones</a><a href="{{ $store->publicUrl() }}#catalogo">Mapa del sitio</a></nav></div>
</footer>
