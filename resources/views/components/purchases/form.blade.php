@props(['proveedores', 'categorias', 'selectedVariants'])

<form method="POST" action="{{ route('compras.store') }}">
    @csrf
    <input type="hidden" name="operacion_id" value="{{ old('operacion_id', (string) Illuminate\Support\Str::uuid()) }}">
    @if($errors->any())
        <div class="purchase-errors" role="alert" tabindex="-1">
            <strong>Revisa los datos de la compra.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="purchase-page" data-purchase-form data-catalog-url="{{ route('compras.catalogo') }}" data-currency="{{ $simboloMoneda }}">
        <script type="application/json" id="purchase-old-items">@json(array_values(old('items', [])))</script>
        <script type="application/json" id="purchase-selected-variants">@json($selectedVariants)</script>
        <section class="purchase-card" aria-labelledby="purchase-general-title">
            <div class="purchase-card__header"><h2 class="purchase-card__title" id="purchase-general-title"><i class="fa-solid fa-file-invoice"></i>Datos de la compra</h2></div>
            <div class="purchase-grid">
                <div class="purchase-field"><label for="proveedor_id">Proveedor <span class="purchase-required" aria-hidden="true">*</span></label><select id="proveedor_id" name="proveedor_id" class="form-control" required aria-describedby="proveedor_help"><option value="">Selecciona un proveedor</option>@foreach($proveedores as $proveedor)<option value="{{ $proveedor->id }}" @selected((string) old('proveedor_id') === (string) $proveedor->id)>{{ $proveedor->nombre }}</option>@endforeach</select><small id="proveedor_help" class="purchase-help">Solo se muestran proveedores activos.</small></div>
                <div class="purchase-field"><label for="fecha">Fecha <span class="purchase-required" aria-hidden="true">*</span></label><input id="fecha" type="date" name="fecha" class="form-control" value="{{ old('fecha', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required></div>
                <div class="purchase-field purchase-field--wide"><label for="observaciones">Observaciones</label><textarea id="observaciones" name="observaciones" class="form-control" maxlength="1000" rows="3" placeholder="Información adicional de la recepción">{{ old('observaciones') }}</textarea><small class="purchase-help">Máximo 1000 caracteres.</small></div>
            </div>
        </section>

        <section class="purchase-card" aria-labelledby="purchase-items-title">
            <div class="purchase-card__header"><h2 class="purchase-card__title" id="purchase-items-title"><i class="fa-solid fa-boxes-stacked"></i>Productos a ingresar</h2><label class="purchase-field purchase-catalog-filter"><span>Categoría del catálogo</span><select class="form-control" data-catalog-category><option value="">Todas las categorías</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>@endforeach</select></label></div>
            <div class="table-wrap"><table class="purchase-table"><thead><tr><th>Producto y variante</th><th>Cantidad</th><th>Precio de compra</th><th>Subtotal</th><th><span class="sr-only">Acción</span></th></tr></thead><tbody data-items-body></tbody><tfoot><tr><td colspan="3">Total de la compra</td><td class="purchase-total" data-purchase-total>{{ $simboloMoneda }} 0.00</td><td></td></tr></tfoot></table></div>
            <button type="button" class="btn btn-outline btn-sm" data-add-row><i class="fa-solid fa-plus"></i> Agregar producto</button>
            <template data-item-template><tr data-item-row><td><div class="purchase-product-picker"><label class="sr-only">Buscar variante</label><input type="search" class="form-control" data-variant-search placeholder="Nombre, SKU, código de barras, talla o color" autocomplete="off"><label class="sr-only">Variante seleccionada</label><select class="form-control" data-variant data-name="items[__INDEX__][variante_id]" required><option value="">Escribe para buscar…</option></select><span class="purchase-search-status" data-search-status role="status" aria-live="polite"></span></div></td><td><label class="sr-only">Cantidad</label><input type="number" min="1" max="2000000000" class="form-control purchase-quantity" data-quantity data-name="items[__INDEX__][cantidad]" required></td><td><label class="sr-only">Precio de compra</label><input type="number" min="0" max="99999999.99" step="0.01" class="form-control purchase-price" data-price data-name="items[__INDEX__][precio]" required></td><td class="purchase-subtotal" data-subtotal></td><td><button type="button" class="btn btn-sm btn-danger" data-remove-row aria-label="Eliminar producto"><i class="fa-solid fa-trash"></i></button></td></tr></template>
        </section>

        <div class="purchase-actions"><a href="{{ route('compras.index') }}" class="btn btn-secondary">Cancelar</a><button type="submit" class="btn btn-primary" data-purchase-submit><i class="fa-solid fa-floppy-disk"></i><span>Registrar compra y sumar stock</span></button></div>
    </div>
</form>
