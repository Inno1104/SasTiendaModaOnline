(() => {
    'use strict';

    const formatButtons = document.querySelectorAll('[data-receipt-format]');
    formatButtons.forEach(button => button.addEventListener('click', () => {
        const format = button.dataset.receiptFormat;
        document.body.classList.toggle('format-a4', format === 'a4');
        document.body.classList.toggle('format-ticket', format === 'ticket');
        formatButtons.forEach(candidate => candidate.classList.toggle('active', candidate === button));
    }));

    document.getElementById('printReceipt')?.addEventListener('click', () => window.print());
    document.getElementById('printCurrentReceipt')?.addEventListener('click', () => window.print());

    const detailLayout = document.querySelector('.receipt-detail-layout');
    const detailToggle = document.getElementById('toggleReceiptFormat');
    detailToggle?.addEventListener('click', () => {
        const ticketMode = !detailLayout.classList.contains('format-ticket');
        detailLayout.classList.toggle('format-ticket', ticketMode);
        detailLayout.classList.toggle('ticket-preview', ticketMode);
        detailToggle.setAttribute('aria-pressed', String(ticketMode));
        detailToggle.querySelector('span').textContent = ticketMode ? 'Volver a A4' : 'Ticket 80 mm';
        detailToggle.querySelector('i').className = ticketMode ? 'fa-regular fa-file-lines' : 'fa-solid fa-receipt';
    });
})();
