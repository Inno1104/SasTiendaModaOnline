document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-fiscal-settings-form]');
    if (!form) return;
    const currency = form.querySelector('[data-currency-select]');
    const symbol = form.querySelector('[data-currency-symbol]');
    const confirmation = form.querySelector('[data-fiscal-confirmation]');
    const submit = form.querySelector('[data-submit]');

    const refreshSymbol = () => {
        symbol.textContent = currency.selectedOptions[0]?.dataset.symbol || '';
    };
    currency.addEventListener('change', refreshSymbol);
    refreshSymbol();
    form.querySelectorAll('.settings-series').forEach((input) => {
        input.addEventListener('input', () => { input.value = input.value.toUpperCase(); });
    });
    form.addEventListener('submit', async (event) => {
        if (confirmation.value !== '1') {
            event.preventDefault();
            if (!await window.appConfirm('Estos cambios afectarán los próximos cálculos y comprobantes. ¿Deseas continuar?')) return;
            confirmation.value = '1';
            form.requestSubmit();
            return;
        }
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
    });
});
