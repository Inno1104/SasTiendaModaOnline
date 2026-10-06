document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('brandDeleteDialog');
    if (!dialog) return;

    const message = dialog.querySelector('[data-delete-message]');
    const warning = dialog.querySelector('[data-delete-warning]');
    const warningText = warning?.querySelector('span');
    const confirmButton = dialog.querySelector('[data-delete-confirm]');
    let pendingForm = null;

    document.querySelectorAll('[data-brand-delete]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            pendingForm = form;
            const products = Number(form.dataset.products || 0);
            const hasDependencies = products > 0;

            message.textContent = `¿Deseas eliminar “${form.dataset.name}”? Esta acción no se puede deshacer.`;
            warning.hidden = !hasDependencies;
            if (warningText) warningText.textContent = hasDependencies
                ? `No se puede eliminar mientras tenga ${products} producto(s) asociado(s).`
                : '';
            confirmButton.disabled = hasDependencies;

            if (typeof dialog.showModal === 'function') dialog.showModal();
            else if (!hasDependencies && await window.appConfirm(message.textContent)) form.submit();
        });
    });

    dialog.querySelector('[data-delete-cancel]')?.addEventListener('click', () => dialog.close());
    confirmButton?.addEventListener('click', () => {
        if (!pendingForm || confirmButton.disabled) return;
        const form = pendingForm;
        pendingForm = null;
        dialog.close();
        form.submit();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});
