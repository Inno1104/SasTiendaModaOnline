document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('supplierStateDialog');
    if (!dialog) return;

    const message = dialog.querySelector('[data-state-message]');
    const consequence = dialog.querySelector('[data-state-consequence]');
    const products = dialog.querySelector('[data-state-products]');
    const purchases = dialog.querySelector('[data-state-purchases]');
    const confirmButton = dialog.querySelector('[data-state-confirm]');
    let pendingForm = null;

    document.querySelectorAll('[data-supplier-state]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            pendingForm = form;
            const nextState = form.dataset.nextState || 'nuevo estado';

            message.textContent = `¿Deseas cambiar “${form.dataset.name || 'este proveedor'}” a estado ${nextState}?`;
            products.textContent = String(Number(form.dataset.products || 0));
            purchases.textContent = String(Number(form.dataset.purchases || 0));
            consequence.textContent = nextState === 'inactivo'
                ? 'No podrá usarse en productos ni compras nuevas. Todos los registros históricos se conservarán.'
                : 'Volverá a estar disponible para productos y compras nuevas.';
            confirmButton.classList.toggle('btn-danger', nextState === 'inactivo');
            confirmButton.classList.toggle('btn-primary', nextState !== 'inactivo');

            if (typeof dialog.showModal === 'function') dialog.showModal();
            else if (window.confirm(`${message.textContent}\n${consequence.textContent}`)) form.submit();
        });
    });

    dialog.querySelector('[data-state-cancel]')?.addEventListener('click', () => {
        pendingForm = null;
        dialog.close();
    });
    confirmButton?.addEventListener('click', () => {
        if (!pendingForm) return;
        const form = pendingForm;
        pendingForm = null;
        dialog.close();
        form.submit();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            pendingForm = null;
            dialog.close();
        }
    });
    dialog.addEventListener('cancel', () => {
        pendingForm = null;
    });
});
