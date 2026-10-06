<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Comprobante de venta</title></head>
<body style="margin:0;background:#f5f6fb;font-family:Arial,sans-serif;color:#111831">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
    <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #e5e7ef">
        <tr><td style="padding:28px 32px;background:#fff0f7;border-bottom:3px solid #ec2f8c">
            <div style="font-size:13px;font-weight:bold;color:#ec2f8c;text-transform:uppercase;letter-spacing:1px">Pago verificado</div>
            <h1 style="margin:8px 0 4px;font-size:26px">Tu pago fue confirmado</h1>
            <p style="margin:0;color:#667085">Pedido {{ $order->numero }}</p>
        </td></tr>
        <tr><td style="padding:30px 32px">
            <p>Hola <strong>{{ $order->cliente_nombre }}</strong>,</p>
            <p style="line-height:1.6;color:#475467">Confirmamos que recibimos correctamente tu pago.@if($includeReceipt) Adjuntamos el comprobante de venta correspondiente a tu compra.@endif</p>
            <table role="presentation" width="100%" cellpadding="10" cellspacing="0" style="margin:22px 0;background:#f8f9fc;border-radius:12px">
                <tr><td style="color:#667085">Comprobante</td><td align="right"><strong>{{ $sale->numero_venta }}</strong></td></tr>
                <tr><td style="color:#667085">Tipo</td><td align="right"><strong>{{ ucfirst($sale->tipo_comprobante) }}</strong></td></tr>
                <tr><td style="color:#667085">Total</td><td align="right" style="font-size:18px;color:#d91c72"><strong>{{ $order->simbolo_moneda }} {{ number_format((float) $order->total, 2) }}</strong></td></tr>
            </table>
            @if($includeReceipt)<p style="line-height:1.6;color:#475467">Puedes abrir el archivo adjunto en tu navegador e imprimirlo o guardarlo desde allí. También estará disponible al consultar tu pedido.</p>@endif
            <p style="margin-top:28px">Gracias por tu compra,<br><strong>{{ $sale->tienda->nombre }}</strong></p>
        </td></tr>
    </table>
</td></tr></table>
</body>
</html>
