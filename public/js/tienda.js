document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-store-profile-form]');
    if (!form) return;

    const logoInput = form.querySelector('[data-store-logo-input]');
    const logoPreview = form.querySelector('[data-store-logo-preview]');
    const logoRemove = form.querySelector('[data-store-logo-remove]');
    const logoFeedback = form.querySelector('[data-store-logo-feedback]');
    const confirmDialog = document.querySelector('[data-logo-confirm]');
    let previewUrl = null;
    let removeConfirmed = false;

    const clearPreviewUrl = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    };

    logoInput?.addEventListener('change', () => {
        logoFeedback.textContent = '';
        const file = logoInput.files?.[0];
        if (!file) return;
        const allowedTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
        if (!allowedTypes.includes(file.type) || file.size > 3 * 1024 * 1024) {
            logoFeedback.textContent = 'Selecciona una imagen PNG, JPG, WebP o SVG de hasta 3 MB.';
            logoInput.value = '';
            logoInput.focus();
            return;
        }
        clearPreviewUrl();
        previewUrl = URL.createObjectURL(file);
        logoPreview.src = previewUrl;
        logoRemove.checked = false;
        removeConfirmed = false;
    });

    logoRemove?.addEventListener('change', () => {
        if (!logoRemove.checked || removeConfirmed) return;
        logoRemove.checked = false;
        if (confirmDialog?.showModal) confirmDialog.showModal();
    });
    confirmDialog?.querySelector('[data-logo-cancel]')?.addEventListener('click', () => {
        confirmDialog.close();
        logoRemove.focus();
    });
    confirmDialog?.querySelector('[data-logo-accept]')?.addEventListener('click', () => {
        removeConfirmed = true;
        logoRemove.checked = true;
        logoInput.value = '';
        clearPreviewUrl();
        logoPreview.src = logoPreview.dataset.defaultSrc;
        confirmDialog.close();
        logoRemove.focus();
    });
    confirmDialog?.addEventListener('cancel', () => {
        logoRemove.checked = false;
        removeConfirmed = false;
    });

    const receiptDialog = document.querySelector('[data-receipt-preview]');
    const receiptSheet = receiptDialog?.querySelector('[data-receipt-sheet]');
    const seriesNode = receiptDialog?.querySelector('[data-receipt-series]');
    const series = seriesNode ? JSON.parse(seriesNode.textContent) : {};
    const fieldValue = (name) => form.querySelector(`[name="${name}"]`)?.value.trim() || '—';
    const refreshReceipt = () => {
        receiptDialog.querySelectorAll('[data-preview-field]').forEach((target) => {
            target.textContent = fieldValue(target.dataset.previewField);
        });
        receiptDialog.querySelector('[data-preview-logo]').src = logoPreview.src;
    };

    document.querySelector('[data-receipt-preview-open]')?.addEventListener('click', () => {
        refreshReceipt();
        receiptDialog.showModal();
    });
    receiptDialog?.querySelector('[data-receipt-preview-close]')?.addEventListener('click', () => receiptDialog.close());
    receiptDialog?.addEventListener('click', (event) => {
        if (event.target === receiptDialog) receiptDialog.close();
    });
    receiptDialog?.querySelectorAll('[data-receipt-format]').forEach((button) => {
        button.addEventListener('click', () => {
            const ticket = button.dataset.receiptFormat === 'ticket';
            receiptSheet.classList.toggle('is-ticket', ticket);
            receiptSheet.classList.toggle('is-a4', !ticket);
            receiptDialog.querySelectorAll('[data-receipt-format]').forEach((item) => {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', String(active));
            });
            receiptDialog.querySelector('[data-preview-document-type]').textContent = ticket ? 'TICKET DE VENTA' : 'FACTURA DE VENTA';
            receiptDialog.querySelector('[data-preview-document-number]').textContent = `${ticket ? series.ticket : series.factura}-000001`;
        });
    });
    form.addEventListener('input', () => {
        if (receiptDialog?.open) refreshReceipt();
    });

    let submitting = false;
    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }
        if (!form.checkValidity()) return;
        submitting = true;
        const button = form.querySelector('[data-submit-button]');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        form.querySelector('[data-submit-label]').textContent = 'Guardando…';
        const icon = form.querySelector('[data-submit-icon]');
        icon.className = 'fa-solid fa-spinner is-spinning';
    });

    document.querySelector('[data-error-summary]')?.focus();
    window.addEventListener('beforeunload', clearPreviewUrl);
});
