document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-print-order]')?.addEventListener('click', () => window.print());

    const reason = document.querySelector('#motivo_cancelacion');
    const counter = document.querySelector('[data-character-count]');
    if (reason && counter) {
        const updateCounter = () => { counter.textContent = `${reason.value.length}/500`; };
        reason.addEventListener('input', updateCounter);
        updateCounter();
    }

    const receiptModal = document.querySelector('[data-bank-receipt-modal]');
    const receiptFrame = receiptModal?.querySelector('[data-receipt-frame]');
    const receiptLoading = receiptModal?.querySelector('[data-receipt-loading]');
    const openReceipt = document.querySelector('[data-open-bank-receipt]');

    const closeReceipt = () => {
        if (!receiptModal?.open) return;
        receiptModal.close();
        if (receiptFrame) receiptFrame.src = 'about:blank';
    };

    openReceipt?.addEventListener('click', () => {
        if (!receiptModal || !receiptFrame) return;
        receiptLoading?.removeAttribute('hidden');
        receiptFrame.src = openReceipt.dataset.receiptUrl;
        receiptModal.showModal();
    });

    receiptFrame?.addEventListener('load', () => {
        if (receiptFrame.src !== 'about:blank') receiptLoading?.setAttribute('hidden', '');
    });

    receiptModal?.querySelectorAll('[data-close-bank-receipt]').forEach((button) => button.addEventListener('click', closeReceipt));
    receiptModal?.querySelector('[data-print-bank-receipt]')?.addEventListener('click', () => {
        try {
            receiptFrame?.contentWindow?.focus();
            receiptFrame?.contentWindow?.print();
        } catch (error) {
            window.print();
        }
    });
    receiptModal?.addEventListener('click', (event) => {
        if (event.target === receiptModal) closeReceipt();
    });
    receiptModal?.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeReceipt();
    });

    document.querySelectorAll('[data-payment-verification-modal] form, [data-invoice-delivery-modal] form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.closest('dialog')?.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });
        });
    });

    const guideInput = document.querySelector('[data-shipping-guide-input]');
    const guidePreview = document.querySelector('[data-shipping-guide-preview]');
    const guideImage = guidePreview?.querySelector('img');
    const guideName = guidePreview?.querySelector('[data-shipping-guide-name]');
    let guideObjectUrl;
    guideInput?.addEventListener('change', () => {
        if (guideObjectUrl) URL.revokeObjectURL(guideObjectUrl);
        const file = guideInput.files?.[0];
        if (!file || !guidePreview || !guideImage) {
            guidePreview?.setAttribute('hidden', '');
            return;
        }
        guideObjectUrl = URL.createObjectURL(file);
        guideImage.src = guideObjectUrl;
        if (guideName) guideName.textContent = file.name;
        guidePreview.removeAttribute('hidden');
    });
});
