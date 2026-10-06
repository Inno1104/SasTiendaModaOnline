<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Consultar mi pedido · {{ $store->nombre }}</title><link rel="stylesheet" href="{{ asset('css/order-lookup.css') }}?v={{ filemtime(public_path('css/order-lookup.css')) }}"></head>
<body style="--lookup-accent:{{ $store->color_primario ?: '#e8398c' }}">
<header class="lookup-header"><a href="{{ $store->publicUrl() }}"><span>@if($store->logo)<img src="{{ asset('storage/'.$store->logo) }}" alt="">@else{{ mb_substr($store->nombre,0,1) }}@endif</span><div><strong>{{ $store->nombre }}</strong><small>Tienda online</small></div></a><a href="{{ $store->publicUrl() }}">← Volver a la tienda</a></header>
<main class="lookup-shell">
    <section class="lookup-intro"><span>Seguimiento de compra</span><h1>Consulta el estado de tu pedido</h1><p>Ingresa la información utilizada durante tu compra para acceder de forma segura al seguimiento.</p><ul><li><i>✓</i> Revisa el progreso en tiempo real</li><li><i>✓</i> Consulta la guía cuando sea enviado</li><li><i>✓</i> Tus datos se utilizan solo para validar el pedido</li></ul></section>
    <section class="lookup-card"><header><span>⌕</span><div><h2>Buscar mi pedido</h2><p>Los dos campos son obligatorios.</p></div></header>
        @error('pedido')<div class="lookup-error" role="alert">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('storefront.orders.lookup.store',$store->slug) }}">@csrf
            <label for="numero_pedido"><span>Número de pedido</span><div><i>#</i><input id="numero_pedido" name="numero_pedido" value="{{ old('numero_pedido') }}" maxlength="40" autocomplete="off" placeholder="Ej. WEB-1-2026-000007" required></div>@error('numero_pedido')<small>{{ $message }}</small>@enderror</label>
            <label for="cedula"><span>Número de cédula o identificación</span><div><i>◇</i><input id="cedula" name="cedula" value="{{ old('cedula') }}" inputmode="numeric" pattern="[0-9]{8,13}" minlength="8" maxlength="13" autocomplete="off" placeholder="Ingresa entre 8 y 13 dígitos" required></div>@error('cedula')<small>{{ $message }}</small>@enderror</label>
            <button type="submit">Consultar pedido <span>→</span></button>
        </form>
        <footer><i>i</i><p>Encontrarás el número en la confirmación de tu compra. Si tienes inconvenientes, comunícate directamente con la tienda.</p></footer>
    </section>
</main></body></html>
