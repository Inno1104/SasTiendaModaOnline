document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('categoryDeleteDialog');
    if (!dialog) return;

    const message = dialog.querySelector('[data-delete-message]');
    const warning = dialog.querySelector('[data-delete-warning]');
    const warningText = warning?.querySelector('span');
    const confirmButton = dialog.querySelector('[data-delete-confirm]');
    let pendingForm = null;

    document.querySelectorAll('[data-category-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            pendingForm = form;
            const products = Number(form.dataset.products || 0);
            const subcategories = Number(form.dataset.subcategories || 0);
            const hasDependencies = products > 0 || subcategories > 0;

            message.textContent = `¿Deseas eliminar “${form.dataset.name}”? Esta acción no se puede deshacer.`;
            warning.hidden = !hasDependencies;
            if (warningText) warningText.textContent = hasDependencies
                ? `No se puede eliminar: tiene ${products} producto(s) y ${subcategories} subcategoría(s).`
                : '';
            confirmButton.disabled = hasDependencies;

            if (typeof dialog.showModal === 'function') dialog.showModal();
            else if (!hasDependencies && window.confirm(message.textContent)) form.submit();
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
