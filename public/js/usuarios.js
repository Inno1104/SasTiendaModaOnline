(() => {
    const dialog = document.querySelector('[data-user-status-dialog]');
    let pendingForm = null;

    if (dialog && typeof dialog.showModal === 'function') {
        const title = dialog.querySelector('[data-status-title]');
        const message = dialog.querySelector('[data-status-message]');
        const reason = dialog.querySelector('[data-status-reason]');
        const error = dialog.querySelector('[data-status-error]');
        const confirmButton = dialog.querySelector('[data-status-confirm]');

        document.querySelectorAll('[data-user-status-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === 'true') return;
                event.preventDefault();
                pendingForm = form;
                const activating = form.dataset.nextState === 'activo';
                title.textContent = activating ? 'Activar usuario' : 'Desactivar usuario';
                message.textContent = activating
                    ? `Se restaurará el acceso de ${form.dataset.userName}.`
                    : `Se cerrará el acceso de ${form.dataset.userName} en su siguiente solicitud.`;
                confirmButton.className = `btn ${activating ? 'btn-primary' : 'btn-danger'}`;
                confirmButton.textContent = activating ? 'Confirmar activación' : 'Confirmar desactivación';
                reason.value = '';
                error.hidden = true;
                dialog.showModal();
                reason.focus();
            });
        });

        dialog.querySelector('[data-status-cancel]').addEventListener('click', () => dialog.close());
        confirmButton.addEventListener('click', () => {
            const value = reason.value.trim();
            if (value.length < 5) {
                error.hidden = false;
                reason.setAttribute('aria-invalid', 'true');
                reason.focus();
                return;
            }
            pendingForm.querySelector('[name="motivo"]').value = value;
            pendingForm.dataset.confirmed = 'true';
            dialog.close();
            pendingForm.requestSubmit();
        });
        dialog.addEventListener('close', () => {
            reason.removeAttribute('aria-invalid');
            pendingForm = null;
        });
    }

    document.querySelectorAll('.user-form').forEach((form) => {
        const password = form.querySelector('[data-user-password]');
        const confirmation = form.querySelector('[data-user-password-confirmation]');
        const mismatch = form.querySelector('[data-password-mismatch]');
        if (!password || !confirmation || !mismatch) return;

        const validateMatch = () => {
            const differs = confirmation.value !== '' && password.value !== confirmation.value;
            confirmation.setCustomValidity(differs ? 'Las contraseñas no coinciden.' : '');
            mismatch.classList.toggle('is-hidden', !differs);
        };
        password.addEventListener('input', validateMatch);
        confirmation.addEventListener('input', validateMatch);
    });
})();
