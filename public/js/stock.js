(function () {
    'use strict';

    const toggleEditor = (row, editing) => {
        const editor = row.nextElementSibling;
        const stock = row.querySelector('[data-stock-value]');
        const minimum = row.querySelector('[data-minimum-value]');
        const edit = row.querySelector('[data-stock-edit]');
        const cancel = row.querySelector('[data-stock-cancel]');
        const reason = editor?.querySelector('[data-stock-reason]');
        const save = editor?.querySelector('[data-stock-save]');

        [stock, minimum, reason, save].forEach(element => { if (element) element.disabled = !editing; });
        editor?.classList.toggle('is-hidden', !editing);
        edit?.classList.toggle('is-hidden', editing);
        cancel?.classList.toggle('is-hidden', !editing);
        row.classList.toggle('is-editing', editing);
        if (editing) stock?.focus();
    };

    const refreshState = row => {
        const stock = Number(row.querySelector('[data-stock-value]')?.value || 0);
        const minimum = Number(row.querySelector('[data-minimum-value]')?.value || 0);
        const badge = row.querySelector('[data-stock-state]');
        if (!badge) return;
        const state = stock <= 0 ? ['out', 'Agotada'] : stock <= minimum ? ['low', 'Stock bajo'] : ['available', 'Disponible'];
        badge.className = `stock-state stock-state-${state[0]}`;
        badge.innerHTML = `<i class="fa-solid fa-circle"></i> ${state[1]}`;
    };

    document.querySelectorAll('[data-stock-row]').forEach(row => {
        row.querySelector('[data-stock-edit]')?.addEventListener('click', () => toggleEditor(row, true));
        row.querySelector('[data-stock-cancel]')?.addEventListener('click', () => {
            const stock = row.querySelector('[data-stock-value]');
            const minimum = row.querySelector('[data-minimum-value]');
            if (stock) stock.value = row.dataset.currentStock;
            if (minimum) minimum.value = row.dataset.currentMinimum;
            const reason = row.nextElementSibling?.querySelector('[data-stock-reason]');
            if (reason) reason.value = '';
            refreshState(row);
            toggleEditor(row, false);
        });
        row.querySelectorAll('[data-stock-value],[data-minimum-value]').forEach(input => input.addEventListener('input', () => refreshState(row)));
    });

    document.querySelectorAll('form[data-confirm-message]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            if (!form.checkValidity()) return;
            if (!window.confirm(form.dataset.confirmMessage)) {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            const button = form.querySelector('[data-submit-button], button[type="submit"], button:not([type])');
            const label = button?.querySelector('[data-submit-text]');
            if (button) button.disabled = true;
            if (label && form.dataset.submitLabel) label.textContent = form.dataset.submitLabel;
        });
    });

    const validationSummary = document.querySelector('[data-validation-summary]');
    if (validationSummary) {
        validationSummary.focus();
    }
})();
