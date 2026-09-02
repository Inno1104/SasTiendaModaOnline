document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('customerStatusDialog');
    if (!dialog) return;

    const form = dialog.querySelector('form');
    const title = dialog.querySelector('[data-status-title]');
    const message = dialog.querySelector('[data-status-message]');
    const activeInput = dialog.querySelector('input[name="activo"]');
    const reasonInput = dialog.querySelector('textarea[name="motivo"]');
    const submitButton = dialog.querySelector('[data-status-submit]');

    document.querySelectorAll('[data-customer-status]').forEach((button) => {
        button.addEventListener('click', () => {
            const activating = button.dataset.active === '1';
            const action = activating ? 'Reactivar' : 'Desactivar';

            form.action = button.dataset.url;
            activeInput.value = activating ? '1' : '0';
            title.textContent = `${action} cliente`;
            message.textContent = `${action} a ${button.dataset.name}. Su historial de ventas se conservará.`;
            submitButton.textContent = action;
            submitButton.classList.toggle('btn-danger', !activating);
            reasonInput.value = '';
            dialog.showModal();
            reasonInput.focus();
        });
    });

    dialog.querySelector('[data-status-cancel]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});
