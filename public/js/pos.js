(() => {
    'use strict';

    const config = window.posConfig;
    if (!config) return;

    const $ = id => document.getElementById(id);
    const cart = [];
    const payments = [{ method: 'efectivo', amount: 0, reference: '', dirty: false }];
    let submitting = false;
    let clientSearchTimer;
    const money = amount => `${config.currency} ${Number(amount).toFixed(2)}`;

    function notice(message, error = false) {
        const element = $('posNotice');
        element.textContent = message;
        element.classList.toggle('error', error);
        element.setAttribute('role', error ? 'alert' : 'status');
        element.setAttribute('aria-live', error ? 'assertive' : 'polite');
        element.classList.add('show');
        if (error) element.focus({ preventScroll: false });
        clearTimeout(notice.timer);
        notice.timer = setTimeout(() => element.classList.remove('show'), 2400);
    }

    function totals() {
        const gross = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
        const lineDiscount = cart.reduce((sum, item) => sum + Math.min(item.discount, item.price * item.quantity), 0);
        const afterLines = Math.max(0, gross - lineDiscount);
        const globalDiscount = Math.min(Math.max(0, Number($('descuentoGlobal').value) || 0), afterLines);
        const charge = afterLines - globalDiscount;
        const subtotal = config.taxIncluded && config.taxRate > 0 ? charge / (1 + config.taxRate / 100) : charge;
        const tax = config.taxIncluded ? charge - subtotal : subtotal * config.taxRate / 100;

        return { subtotal, tax, total: config.taxIncluded ? charge : subtotal + tax, discount: lineDiscount + globalDiscount };
    }

    function updateSummary() {
        const value = totals();
        const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        $('sumSubtotal').textContent = money(value.subtotal);
        $('sumIGV').textContent = money(value.tax);
        $('sumDesc').textContent = `- ${money(value.discount)}`;
        $('sumTotal').textContent = money(value.total);
        $('cartCount').textContent = `${itemCount} artículos`;
        $('cartBubble').textContent = itemCount;
        if (payments.length === 1 && !payments[0].dirty) {
            payments[0].amount = value.total;
            const amountInput = document.querySelector('[data-payment-amount="0"]');
            if (amountInput) amountInput.value = value.total.toFixed(2);
        }
        updateChange();
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function renderCart() {
        const container = $('cartItems');
        container.replaceChildren();
        if (!cart.length) {
            const empty = element('div', 'empty-cart');
            empty.innerHTML = '<i class="fa-solid fa-bag-shopping"></i><strong>Tu carrito está vacío</strong><span>Agrega una talla y color del catálogo.</span>';
            container.append(empty);
            updateSummary();
            return;
        }

        cart.forEach((item, index) => {
            const row = element('div', 'cart-item');
            const top = element('div', 'cart-item-top');
            const thumbnail = element('div', 'cart-item-thumb');
            if (item.image) {
                const image = document.createElement('img');
                image.src = item.image;
                image.alt = item.name;
                image.loading = 'lazy';
                thumbnail.append(image);
            } else {
                thumbnail.innerHTML = '<i class="fa-solid fa-shirt"></i>';
            }
            const name = element('div', 'cart-item-name', item.name);
            name.append(element('small', '', `${item.size} / ${item.color} · Stock ${item.stock}`));
            top.append(thumbnail, name, element('div', 'cart-item-total', money(item.price * item.quantity)));
            const remove = element('button', 'remove-item');
            remove.type = 'button'; remove.dataset.remove = index; remove.setAttribute('aria-label', `Quitar ${item.name}`);
            remove.innerHTML = '<i class="fa-solid fa-xmark"></i>'; top.append(remove);

            const controls = element('div', 'cart-item-controls');
            const quantity = element('div', 'quantity-control');
            const minus = element('button', '', '−'); minus.type = 'button'; minus.dataset.quantity = index; minus.dataset.delta = '-1';
            const plus = element('button', '', '+'); plus.type = 'button'; plus.dataset.quantity = index; plus.dataset.delta = '1';
            quantity.append(minus, element('span', '', item.quantity), plus);
            const discount = element('label', 'discount-control', 'Descuento de línea');
            const input = document.createElement('input'); input.type = 'number'; input.min = '0'; input.step = '0.01'; input.value = item.discount.toFixed(2); input.dataset.discount = index;
            discount.append(input); controls.append(quantity, discount); row.append(top, controls); container.append(row);
        });
        updateSummary();
    }

    function addProduct(button) {
        const productId = Number(button.dataset.productId);
        const select = $(`variant-${productId}`);
        const option = select?.selectedOptions[0];
        if (!option || !option.value || option.disabled) return notice('Selecciona una variante disponible.', true);
        const variantId = Number(option.value);
        const stock = Number(option.dataset.stock);
        const existing = cart.find(item => item.variantId === variantId);
        if (existing) {
            if (existing.quantity >= stock) return notice('No hay más stock para esta talla y color.', true);
            existing.quantity++;
        } else {
            cart.push({ productId, variantId, name: button.dataset.name, image: button.dataset.image, price: Number(button.dataset.price), size: option.dataset.talla, color: option.dataset.color, stock, quantity: 1, discount: 0 });
        }
        renderCart(); notice('Producto agregado al carrito.');
    }

    function filterProducts() {
        const query = $('productSearch').value.trim().toLocaleLowerCase();
        const category = $('categoryFilter').value;
        const brand = $('brandFilter').value;
        const stockOnly = $('stockFilter').checked;
        let visible = 0;
        document.querySelectorAll('.product-card').forEach(card => {
            const show = (!query || card.dataset.search.includes(query)) && (!category || card.dataset.category === category) && (!brand || card.dataset.brand === brand) && (!stockOnly || card.dataset.available === '1');
            card.hidden = !show; if (show) visible++;
        });
        $('productCount').textContent = `${visible} productos`;
        $('emptyCatalog').hidden = visible !== 0;
    }

    function syncCategoryShortcuts() {
        const selected = $('categoryFilter').value;
        document.querySelectorAll('[data-category-shortcut]').forEach(button => {
            button.classList.toggle('active', button.dataset.categoryShortcut === selected);
        });
    }

    function paymentTotals() {
        return {
            paid: payments.reduce((sum, payment) => sum + payment.amount, 0),
            cash: payments.filter(payment => payment.method === 'efectivo').reduce((sum, payment) => sum + payment.amount, 0),
        };
    }

    function updateChange() {
        const paid = paymentTotals();
        const change = paid.paid - totals().total;
        $('vueltoRow').hidden = paid.cash <= 0 || change < 0 || change > paid.cash;
        $('vueltoAmt').textContent = money(Math.max(0, change));
    }

    function renderPayments() {
        const container = $('paymentRows');
        container.replaceChildren();
        const methods = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia', yape: 'Yape', plin: 'Plin' };
        payments.forEach((payment, index) => {
            const row = element('div', 'payment-row');
            const method = document.createElement('select'); method.className = 'form-control'; method.dataset.paymentMethod = index; method.setAttribute('aria-label', `Método de pago ${index + 1}`);
            Object.entries(methods).forEach(([value, label]) => { const option = new Option(label, value, false, payment.method === value); method.add(option); });
            const amount = document.createElement('input'); amount.className = 'form-control'; amount.type = 'number'; amount.min = '0.01'; amount.step = '0.01'; amount.value = payment.amount.toFixed(2); amount.dataset.paymentAmount = index; amount.setAttribute('aria-label', 'Monto del pago');
            const reference = document.createElement('input'); reference.className = 'form-control'; reference.placeholder = 'Referencia obligatoria'; reference.value = payment.reference; reference.dataset.paymentReference = index; reference.maxLength = 100; reference.required = payment.method !== 'efectivo'; reference.hidden = payment.method === 'efectivo'; reference.setAttribute('aria-label', 'Referencia del pago electrónico');
            row.append(method, amount, reference);
            if (payments.length > 1) { const remove = element('button', 'remove-payment'); remove.type = 'button'; remove.dataset.paymentRemove = index; remove.setAttribute('aria-label', `Quitar pago ${index + 1}`); remove.innerHTML = '<i class="fa-solid fa-xmark"></i>'; row.append(remove); }
            container.append(row);
        });
        updateChange();
    }

    function checkoutIsValid() {
        if (!cart.length) return notice('Agrega al menos un producto.', true);
        const value = totals();
        const paid = paymentTotals();
        if (paid.paid + 0.001 < value.total) return notice('La suma de los pagos es insuficiente.', true);
        if (paid.paid - value.total > paid.cash + 0.001) return notice('Un pago electrónico no puede generar vuelto.', true);
        if (payments.some(payment => payment.method !== 'efectivo' && !payment.reference.trim())) return notice('Ingresa la referencia de cada pago electrónico.', true);

        return true;
    }

    function reviewSale() {
        if (!checkoutIsValid()) return;
        const value = totals();
        $('confirmItems').textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
        $('confirmPayment').textContent = payments.length > 1 ? `${payments.length} métodos combinados` : document.querySelector('[data-payment-method="0"]').selectedOptions[0].textContent;
        $('confirmTotal').textContent = money(value.total);
        const dialog = $('confirmSale');
        typeof dialog.showModal === 'function' ? dialog.showModal() : dialog.setAttribute('open', '');
    }

    function appendHidden(parent, name, value) {
        const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; parent.append(input);
    }

    function submitSale() {
        if (submitting) return;
        if (!checkoutIsValid()) return;
        submitting = true;
        $('submitSale').disabled = true; $('reviewSale').disabled = true; $('submitSale').textContent = 'Registrando...';
        $('confirmSale').setAttribute('aria-busy', 'true'); $('ventaForm').setAttribute('aria-busy', 'true');
        const items = $('formItems'); items.replaceChildren();
        cart.forEach((item, index) => {
            appendHidden(items, `items[${index}][producto_id]`, item.productId);
            appendHidden(items, `items[${index}][variante_id]`, item.variantId);
            appendHidden(items, `items[${index}][cantidad]`, item.quantity);
            appendHidden(items, `items[${index}][descuento]`, item.discount);
        });
        payments.forEach((payment, index) => {
            appendHidden(items, `pagos[${index}][metodo]`, payment.method);
            appendHidden(items, `pagos[${index}][monto]`, payment.amount);
            appendHidden(items, `pagos[${index}][referencia]`, payment.reference);
        });
        $('hCliente').value = $('clienteSelect').value; $('hComprobante').value = $('comprobanteSelect').value;
        $('hPago').value = payments[0].method; $('hMontoPagado').value = payments[0].amount;
        $('hDescuentoGlobal').value = $('descuentoGlobal').value || 0; $('ventaForm').submit();
    }

    function selectClient(client) {
        $('clienteSelect').value = client?.id || '';
        $('clientSearch').value = client ? `${client.nombre} ${client.apellido} (${client.codigo})` : '';
        $('clientResults').hidden = true;
    }

    async function searchClients() {
        const query = $('clientSearch').value.trim();
        if (query.length < 2) { $('clientResults').hidden = true; if (!query) $('clienteSelect').value = ''; return; }
        try {
            const response = await fetch(`${config.clientSearchUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
            const clients = (await response.json()).data || [];
            const results = $('clientResults'); results.replaceChildren();
            const direct = element('button', 'client-result', 'Cliente directo'); direct.type = 'button'; direct.addEventListener('click', () => selectClient(null)); results.append(direct);
            clients.forEach(client => { const button = element('button', 'client-result'); button.type = 'button'; button.append(element('strong', '', `${client.nombre} ${client.apellido}`), element('small', '', `${client.codigo}${client.dni ? ` · ${client.dni}` : ''}`)); button.addEventListener('click', () => selectClient(client)); results.append(button); });
            results.hidden = false;
        } catch { notice('No fue posible buscar clientes.', true); }
    }

    async function saveQuickClient(event) {
        event.preventDefault();
        const button = $('saveClient'); button.disabled = true; $('clientFormError').textContent = '';
        try {
            const response = await fetch(config.clientStoreUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: new FormData(event.currentTarget) });
            const body = await response.json();
            if (!response.ok) { const errors = body.errors ? Object.values(body.errors).flat() : [body.message]; throw new Error(errors[0] || 'Datos inválidos.'); }
            selectClient(body.data); $('clientDialog').close(); event.currentTarget.reset(); notice('Cliente creado y seleccionado.');
        } catch (error) { $('clientFormError').textContent = error.message || 'No fue posible crear el cliente.'; }
        finally { button.disabled = false; }
    }

    $('productGrid').addEventListener('click', event => { const button = event.target.closest('.add-product'); if (button) addProduct(button); });
    $('cartItems').addEventListener('click', event => {
        const remove = event.target.closest('[data-remove]'); if (remove) { cart.splice(Number(remove.dataset.remove), 1); return renderCart(); }
        const quantity = event.target.closest('[data-quantity]'); if (!quantity) return;
        const item = cart[Number(quantity.dataset.quantity)]; const delta = Number(quantity.dataset.delta);
        if (delta > 0 && item.quantity >= item.stock) return notice('Stock máximo alcanzado.', true);
        item.quantity += delta;
        item.discount = Math.min(item.discount, item.price * Math.max(0, item.quantity));
        if (item.quantity <= 0) cart.splice(Number(quantity.dataset.quantity), 1); renderCart();
    });
    $('cartItems').addEventListener('change', event => { if (!event.target.matches('[data-discount]')) return; const item = cart[Number(event.target.dataset.discount)]; item.discount = Math.min(item.price * item.quantity, Math.max(0, Number(event.target.value) || 0)); event.target.value = item.discount.toFixed(2); updateSummary(); });
    $('paymentRows').addEventListener('input', event => {
        const index = Number(event.target.dataset.paymentAmount ?? event.target.dataset.paymentReference);
        if (event.target.matches('[data-payment-amount]')) { payments[index].amount = Math.max(0, Number(event.target.value) || 0); payments[index].dirty = true; }
        if (event.target.matches('[data-payment-reference]')) payments[index].reference = event.target.value;
        updateChange();
    });
    $('paymentRows').addEventListener('change', event => {
        if (!event.target.matches('[data-payment-method]')) return;
        const index = Number(event.target.dataset.paymentMethod); payments[index].method = event.target.value; payments[index].dirty = true; renderPayments();
    });
    $('paymentRows').addEventListener('click', event => { const button = event.target.closest('[data-payment-remove]'); if (!button) return; payments.splice(Number(button.dataset.paymentRemove), 1); renderPayments(); });
    $('addPayment').addEventListener('click', () => { if (payments.length >= 5) return notice('Puedes combinar hasta 5 pagos.', true); payments.push({ method: 'tarjeta', amount: 0, reference: '', dirty: true }); renderPayments(); });
    $('productSearch').addEventListener('input', filterProducts);
    ['brandFilter', 'stockFilter'].forEach(id => $(id).addEventListener('change', filterProducts));
    $('categoryFilter').addEventListener('change', () => {
        const url = new URL(config.createUrl, window.location.origin);
        if ($('categoryFilter').value) url.searchParams.set('categoria_id', $('categoryFilter').value);
        window.location.assign(url.toString());
    });
    document.querySelector('.category-rail')?.addEventListener('click', event => {
        const button = event.target.closest('[data-category-shortcut]');
        if (!button) return;
        $('categoryFilter').value = button.dataset.categoryShortcut;
        syncCategoryShortcuts();
        filterProducts();
    });
    $('clearFilters').addEventListener('click', () => {
        if (window.location.search) return window.location.assign(config.createUrl);
        $('productSearch').value = ''; $('categoryFilter').value = ''; $('brandFilter').value = ''; $('stockFilter').checked = false; syncCategoryShortcuts(); filterProducts();
    });
    $('descuentoGlobal').addEventListener('input', updateSummary);
    $('clientSearch').addEventListener('input', () => { clearTimeout(clientSearchTimer); clientSearchTimer = setTimeout(searchClients, 250); });
    $('newClient').addEventListener('click', () => $('clientDialog').showModal()); $('cancelClient').addEventListener('click', () => $('clientDialog').close()); $('quickClientForm').addEventListener('submit', saveQuickClient);
    $('reviewSale').addEventListener('click', reviewSale); $('cancelSale').addEventListener('click', () => $('confirmSale').close()); $('submitSale').addEventListener('click', submitSale);
    window.addEventListener('pageshow', event => { if (!event.persisted) return; submitting = false; $('submitSale').disabled = false; $('reviewSale').disabled = false; $('submitSale').textContent = 'Registrar venta'; $('confirmSale').removeAttribute('aria-busy'); $('ventaForm').setAttribute('aria-busy', 'false'); });
    document.addEventListener('keydown', event => { if (event.key === 'F2') { event.preventDefault(); $('productSearch').focus(); } });
    renderCart(); renderPayments(); filterProducts(); updateChange();
})();
