<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ $venta->commercial_snapshot['document']['number'] ?? $venta->numero_venta }} · {{ $store->nombre }}</title>
    <link rel="stylesheet" href="{{ asset('css/sale-receipt.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sale-receipt-theme.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="receipt-print-page format-a4">
    <header class="print-toolbar">
        <div class="print-title">
            <div><strong>Tu comprobante de venta</strong><small>Documento publicado por {{ $store->nombre }}.</small></div>
        </div>
        <div class="print-actions">
            <div class="format-switch" role="group" aria-label="Formato del comprobante">
                <button type="button" class="active" data-receipt-format="a4"><i class="fa-regular fa-file-lines"></i> Factura A4</button>
                <button type="button" data-receipt-format="ticket"><i class="fa-solid fa-receipt"></i> Ticket 80 mm</button>
            </div>
            <button type="button" id="printReceipt" class="print-button"><i class="fa-solid fa-print"></i> Imprimir</button>
        </div>
    </header>
    <main class="print-sheet">@include('ventas.partials.receipt', ['standalone' => true])</main>
    <script src="{{ asset('js/sale-receipt.js') }}"></script>
</body>
</html>
