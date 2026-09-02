(() => {
    'use strict';

    const root = document.querySelector('[data-purchase-form]');
    if (!root) return;

    const form = root.closest('form');
    const body = root.querySelector('[data-items-body]');
    const template = root.querySelector('[data-item-template]');
    const totalCell = root.querySelector('[data-purchase-total]');
    const category = root.querySelector('[data-catalog-category]');
    const submit = form.querySelector('[data-purchase-submit]');
    const currency = root.dataset.currency;
    const endpoint = root.dataset.catalogUrl;
    const oldItems = JSON.parse(document.getElementById('purchase-old-items').textContent || '[]');
    const selected = JSON.parse(document.getElementById('purchase-selected-variants').textContent || '{}');
    const timers = new WeakMap();
    let nextIndex = 0;

    const money = value => `${currency} ${value.toFixed(2)}`;
    const recalculate = () => {
        let total = 0;
        body.querySelectorAll('[data-item-row]').forEach(row => {
            const quantity = Number(row.querySelector('[data-quantity]').value) || 0;
            const price = Number(row.querySelector('[data-price]').value) || 0;
            const subtotal = quantity * price;
            row.querySelector('[data-subtotal]').textContent = money(subtotal);
            total += subtotal;
        });
        totalCell.textContent = money(total);
    };

    const optionFor = variant => {
        const option = new Option(`${variant.texto} · stock ${variant.stock ?? '—'}`, variant.id);
        option.dataset.price = variant.precio;
        return option;
    };

    const loadCatalog = async (row, query = '') => {
        const select = row.querySelector('[data-variant]');
        const status = row.querySelector('[data-search-status]');
        const url = new URL(endpoint, window.location.origin);
        if (query.trim()) url.searchParams.set('q', query.trim());
        if (category.value) url.searchParams.set('categoria_id', category.value);
        select.disabled = true;
        status.textContent = 'Buscando variantes…';
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('No se pudo consultar el catálogo.');
            const payload = await response.json();
            select.replaceChildren(new Option(payload.data.length ? 'Selecciona una variante…' : 'Sin resultados', ''));
            payload.data.forEach(variant => select.add(optionFor(variant)));
            status.textContent = `${payload.total} resultado${payload.total === 1 ? '' : 's'}`;
        } catch (error) {
            select.replaceChildren(new Option('Catálogo no disponible', ''));
            status.textContent = error.message;
        } finally {
            select.disabled = false;
        }
    };

    const bindRow = (row, item = {}) => {
        const search = row.querySelector('[data-variant-search]');
        const select = row.querySelector('[data-variant]');
        const price = row.querySelector('[data-price]');
        row.querySelector('[data-quantity]').value = item.cantidad || 1;
        price.value = item.precio ?? 0;

        const existing = selected[item.variante_id];
        if (existing) {
            const option = optionFor(existing);
            option.selected = true;
            select.replaceChildren(new Option('Selecciona una variante…', ''), option);
            row.querySelector('[data-search-status]').textContent = 'Selección recuperada';
        } else loadCatalog(row);

        search.addEventListener('input', () => {
            clearTimeout(timers.get(search));
            timers.set(search, setTimeout(() => loadCatalog(row, search.value), 300));
        });
        select.addEventListener('change', () => {
            const option = select.options[select.selectedIndex];
            if (option?.dataset.price !== undefined) price.value = option.dataset.price;
            recalculate();
        });
        row.querySelectorAll('[data-quantity],[data-price]').forEach(field => field.addEventListener('input', recalculate));
        row.querySelector('[data-remove-row]').addEventListener('click', () => {
            row.remove();
            if (!body.children.length) addRow();
            recalculate();
        });
        recalculate();
    };

    const addRow = (item = {}) => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-item-row]');
        row.querySelectorAll('[data-name]').forEach(field => {
            field.name = field.dataset.name.replace('__INDEX__', nextIndex);
        });
        nextIndex += 1;
        body.appendChild(fragment);
        bindRow(body.lastElementChild, item);
    };

    root.querySelector('[data-add-row]').addEventListener('click', () => addRow());
    category.addEventListener('change', () => body.querySelectorAll('[data-item-row]').forEach(row => loadCatalog(row, row.querySelector('[data-variant-search]').value)));
    form.addEventListener('submit', () => {
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.querySelector('span').textContent = 'Registrando…';
    });
    (oldItems.length ? oldItems : [{}]).forEach(addRow);
})();
