<dialog class="receipt-preview-dialog" data-receipt-preview aria-labelledby="receipt-preview-title">
    <header class="receipt-preview-toolbar">
        <div><strong id="receipt-preview-title">Modelo del comprobante</strong><small>Los datos temporales del formulario todavía no se han guardado.</small></div>
        <div class="receipt-preview-actions" role="group" aria-label="Formato del comprobante">
            <button type="button" class="is-active" data-receipt-format="a4" aria-pressed="true"><i class="fa-regular fa-file-lines"></i> Factura A4</button>
            <button type="button" data-receipt-format="ticket" aria-pressed="false"><i class="fa-solid fa-receipt"></i> Ticket 80 mm</button>
            <button type="button" class="receipt-preview-close" data-receipt-preview-close aria-label="Cerrar vista previa"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </header>
    <div class="receipt-preview-stage">
        <article class="receipt-preview-sheet is-a4" data-receipt-sheet aria-label="Comprobante de ejemplo sin valor fiscal">
            <header class="preview-document-header">
                <div class="preview-store-identity">
                    <img data-preview-logo src="{{ $tienda->logo ? asset('storage/'.$tienda->logo) : asset('images/store-logo-default.svg') }}" alt="Logo temporal de la tienda">
                    <div><h2 data-preview-field="nombre">{{ $tienda->nombre }}</h2><p><strong>RUC:</strong> <span data-preview-field="ruc">{{ $tienda->ruc ?: '—' }}</span></p><p data-preview-field="direccion">{{ $tienda->direccion ?: '—' }}</p><p><strong>Tel.:</strong> <span data-preview-field="telefono">{{ $tienda->telefono ?: '—' }}</span></p><p data-preview-field="email">{{ $tienda->email ?: '—' }}</p></div>
                </div>
                <div class="preview-document-number"><span><i class="fa-regular fa-file-lines"></i> <b data-preview-document-type>FACTURA DE VENTA</b></span><strong data-preview-document-number>{{ $tienda->serie_factura ?: 'F001' }}-000001</strong><small>{{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }}</small></div>
            </header>
            <section class="preview-commercial-data">
                <div><h3>DATOS DE FACTURACIÓN</h3><p><b>Cliente:</b> Cliente de ejemplo</p><p><b>Documento:</b> 9999999999</p><p><b>Teléfono:</b> 099 999 9999</p><p><b>Correo:</b> cliente@ejemplo.com</p></div>
                <div class="preview-sale-data"><h3>DATOS DE LA VENTA</h3><p><b>Canal:</b> Punto de venta</p><p><b>Atendido por:</b> {{ auth()->user()->name }}</p><p><b>Pago:</b> Efectivo</p><p><b>Estado:</b> Completada</p><p><b>Moneda:</b> {{ $tienda->moneda ?: 'USD' }} ({{ $tienda->simbolo_moneda ?: '$' }})</p></div>
            </section>
            <div class="preview-products-wrap"><table class="preview-products"><thead><tr><th>Producto</th><th>Cant.</th><th>P. unit.</th><th>Desc.</th><th>Importe</th></tr></thead><tbody>
                <tr><td><strong>Producto de muestra</strong><small>PRD-001 · M / Negro</small></td><td>2</td><td>{{ $tienda->simbolo_moneda ?: '$' }} 25.00</td><td>{{ $tienda->simbolo_moneda ?: '$' }} 0.00</td><td><strong>{{ $tienda->simbolo_moneda ?: '$' }} 50.00</strong></td></tr>
                <tr><td><strong>Segundo producto</strong><small>PRD-002 · L / Azul</small></td><td>1</td><td>{{ $tienda->simbolo_moneda ?: '$' }} 20.00</td><td>{{ $tienda->simbolo_moneda ?: '$' }} 0.00</td><td><strong>{{ $tienda->simbolo_moneda ?: '$' }} 20.00</strong></td></tr>
            </tbody></table></div>
            <div class="preview-receipt-bottom">
                <section class="preview-payments"><h3>PAGOS Y REVERSIONES</h3><div><span>Efectivo</span><strong>{{ $tienda->simbolo_moneda ?: '$' }} 70.00</strong></div></section>
                <section class="preview-totals"><span>Subtotal <b>{{ $tienda->simbolo_moneda ?: '$' }} 60.87</b></span><span>Impuestos <b>{{ $tienda->simbolo_moneda ?: '$' }} 9.13</b></span><strong>Total <b>{{ $tienda->simbolo_moneda ?: '$' }} 70.00</b></strong><span>Pagado <b>{{ $tienda->simbolo_moneda ?: '$' }} 70.00</b></span></section>
            </div>
            <footer><span><i class="fa-solid fa-shield-halved"></i> Comprobante de ejemplo · Sin valor fiscal</span><span>Series {{ $tienda->serie_boleta ?: 'B001' }} · {{ $tienda->serie_factura ?: 'F001' }} · {{ $tienda->serie_ticket ?: 'T001' }} · Snapshot comercial</span></footer>
        </article>
    </div>
    <script type="application/json" data-receipt-series>@json(['factura' => $tienda->serie_factura ?: 'F001', 'ticket' => $tienda->serie_ticket ?: 'T001'])</script>
</dialog>
