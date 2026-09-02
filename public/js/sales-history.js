(() => {
    'use strict';

    const dialog = document.getElementById('cancelSaleDialog');
    if (!dialog) return;

    const form = document.getElementById('cancelSaleForm');
    const reason = document.getElementById('cancelReason');
    const reasonLength = document.getElementById('reasonLength');

    document.addEventListener('click', event => {
        const button = event.target.closest('.cancel-sale');
        if (!button) return;

        form.action = button.dataset.url;
        const returning = button.dataset.mode === 'devolver';
        document.getElementById('saleReversalTitle').textContent = returning ? 'Procesar devolución' : 'Anular venta';
        document.getElementById('saleReversalReasonLabel').textContent = returning ? 'Motivo de devolución' : 'Motivo de anulación';
        document.getElementById('saleReversalSubmit').innerHTML = returning ? '<i class="fa-solid fa-rotate-left"></i> Confirmar devolución' : '<i class="fa-solid fa-ban"></i> Confirmar anulación';
        document.getElementById('cancelSaleNumber').textContent = button.dataset.number;
        document.getElementById('cancelSaleTotal').textContent = button.dataset.total;
        reason.value = '';
        reasonLength.textContent = '0';
        dialog.showModal();
        reason.focus();
    });

    reason.addEventListener('input', () => {
        reasonLength.textContent = String(reason.value.length);
    });

    document.getElementById('closeCancelSale').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
})();
