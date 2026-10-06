document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('[data-store-logo-input]');
    const preview = document.querySelector('[data-store-logo-preview]');
    const remove = document.querySelector('[data-store-logo-remove]');
    const feedback = document.querySelector('[data-store-logo-feedback]');
    if (!input || !preview || !remove || !feedback) return;

    const defaultLogo = preview.dataset.defaultSrc;
    let objectUrl = null;
    input.addEventListener('change', () => {
        feedback.textContent = '';
        const file = input.files?.[0];
        if (!file) return;
        const allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
        if (!allowed.includes(file.type) || file.size > 3 * 1024 * 1024) {
            feedback.textContent = 'Selecciona una imagen PNG, JPG, WebP o SVG de hasta 3 MB.';
            input.value = '';
            return;
        }
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        remove.checked = false;
    });

    remove.addEventListener('change', async () => {
        if (!remove.checked) return;
        if (!await window.appConfirm('¿Eliminar el logo actual? La tienda usará la imagen predeterminada.')) {
            remove.checked = false;
            return;
        }
        input.value = '';
        preview.src = defaultLogo;
    });

    const dialog = document.querySelector('[data-receipt-preview]');
    const profileForm = document.querySelector('[data-store-profile-form]');
    if (!dialog || !profileForm) return;
    const sheet = dialog.querySelector('[data-receipt-sheet]');
    const series = JSON.parse(dialog.querySelector('[data-receipt-series]').textContent);
    const fieldValue = (name) => profileForm.querySelector(`[name="${name}"]`)?.value.trim() || '—';
    const refreshReceipt = () => {
        dialog.querySelectorAll('[data-preview-field]').forEach((target) => {
            target.textContent = fieldValue(target.dataset.previewField);
        });
        dialog.querySelector('[data-preview-logo]').src = preview.src;
    };

    document.querySelector('[data-receipt-preview-open]')?.addEventListener('click', () => {
        refreshReceipt();
        dialog.showModal();
    });
    dialog.querySelector('[data-receipt-preview-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    dialog.querySelectorAll('[data-receipt-format]').forEach((button) => {
        button.addEventListener('click', () => {
            const ticket = button.dataset.receiptFormat === 'ticket';
            sheet.classList.toggle('is-ticket', ticket);
            sheet.classList.toggle('is-a4', !ticket);
            dialog.querySelectorAll('[data-receipt-format]').forEach((item) => {
                item.classList.toggle('is-active', item === button);
            });
            dialog.querySelector('[data-preview-document-type]').textContent = ticket ? 'TICKET' : 'FACTURA';
            dialog.querySelector('[data-preview-document-number]').textContent = `${ticket ? series.ticket : series.factura}-000001`;
        });
    });
    profileForm.addEventListener('input', () => {
        if (dialog.open) refreshReceipt();
    });
});
