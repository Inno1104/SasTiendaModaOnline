document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-print-cash]')?.addEventListener('click', () => window.print());
    document.querySelectorAll('[data-cash-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"], button:not([type])');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.classList.add('is-loading');
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando...';
        });
    });
    const dialog = document.getElementById('cashCloseDialog');
    if (!dialog) return;

    const openButton = document.getElementById('openCashClose');
    const cancelButton = document.getElementById('cancelCashClose');
    const countedInput = document.getElementById('efectivo_contado');
    const notesInput = document.getElementById('observaciones_cierre');
    const differenceOutput = document.getElementById('cashDifference');
    const statusOutput = document.getElementById('cashReconciliationStatus');
    const expected = Number(dialog.dataset.expected || 0);

    const refreshPreview = () => {
        const counted = Number(countedInput.value || 0);
        const difference = Math.round((counted - expected) * 100) / 100;
        const status = difference === 0 ? 'exacto' : (difference > 0 ? 'sobrante' : 'faltante');
        differenceOutput.textContent = `${difference < 0 ? '-' : difference > 0 ? '+' : ''} ${dialog.dataset.symbol} ${Math.abs(difference).toFixed(2)}`;
        differenceOutput.className = `cash-difference ${status}`;
        statusOutput.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        statusOutput.className = `reconciliation-status ${status}`;
        notesInput.required = difference !== 0;
    };

    openButton?.addEventListener('click', () => { refreshPreview(); dialog.showModal(); });
    cancelButton?.addEventListener('click', () => dialog.close());
    countedInput?.addEventListener('input', refreshPreview);
    if (dialog.dataset.openOnLoad === '1') { refreshPreview(); dialog.showModal(); }
});
