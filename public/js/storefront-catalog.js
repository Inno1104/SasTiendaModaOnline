document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-dismiss-alert]').forEach((alert) => {
        window.setTimeout(() => {
            alert.classList.add('is-hiding');
            window.setTimeout(() => alert.remove(), 350);
        }, 5000);
    });

    document.querySelector('[data-catalog-sort]')?.addEventListener('change', (event) => event.currentTarget.form?.submit());

    const checkoutForm = document.querySelector('.public-checkout-form[data-customer-lookup-url]');
    if (checkoutForm) {
        const dniInput = checkoutForm.querySelector('[data-customer-dni]');
        const status = checkoutForm.querySelector('[data-customer-lookup-status]');
        const csrf = checkoutForm.querySelector('input[name="_token"]')?.value;
        const receiptType = checkoutForm.querySelector('[data-receipt-type]');
        const billingSources = [...checkoutForm.querySelectorAll('[data-billing-source]')];
        const customBilling = checkoutForm.querySelector('[data-billing-custom]');
        const billingSummary = checkoutForm.querySelector('[data-billing-summary]');
        const legacyInvoice = checkoutForm.querySelector('[data-invoice-legacy]');
        let timer;
        let controller;

        const countryField = checkoutForm.querySelector('[data-delivery-country]');
        const provinceField = checkoutForm.querySelector('[data-delivery-province]');
        const cityField = checkoutForm.querySelector('[data-delivery-city]');
        const manualProvince = checkoutForm.querySelector('[data-delivery-province-manual]');
        const manualCity = checkoutForm.querySelector('[data-delivery-city-manual]');
        const locationsNode = document.querySelector('[data-ecuador-locations]');
        const ecuadorLocations = locationsNode ? JSON.parse(locationsNode.textContent || '{}') : {};

        const optionsFor = (field, placeholder, values, selected = '') => {
            if (!field) return;
            field.replaceChildren(new Option(placeholder, ''));
            values.forEach((value) => field.add(new Option(value, value, false, value === selected)));
            field.value = values.includes(selected) ? selected : '';
        };
        const toggleLocationMode = () => {
            const usesCatalog = countryField?.value === 'EC';
            [provinceField, cityField].forEach((field) => {
                if (!field) return;
                field.hidden = !usesCatalog;
                field.disabled = !usesCatalog;
                field.required = usesCatalog;
                if (usesCatalog) field.name = field === provinceField ? 'entrega[provincia]' : 'entrega[ciudad]';
                else field.removeAttribute('name');
            });
            [manualProvince, manualCity].forEach((field) => {
                if (!field) return;
                field.hidden = usesCatalog;
                field.disabled = usesCatalog;
                field.required = !usesCatalog;
                if (!usesCatalog) field.name = field === manualProvince ? 'entrega[provincia]' : 'entrega[ciudad]';
                else field.removeAttribute('name');
            });
            if (!usesCatalog) return;
            const selectedProvince = provinceField.dataset.currentValue || provinceField.value;
            optionsFor(provinceField, 'Selecciona una provincia', Object.keys(ecuadorLocations), selectedProvince);
            const selectedCity = cityField.dataset.currentValue || cityField.value;
            optionsFor(cityField, selectedProvince ? 'Selecciona un cantón' : 'Selecciona primero una provincia', ecuadorLocations[provinceField.value] || [], selectedCity);
            delete provinceField.dataset.currentValue;
            delete cityField.dataset.currentValue;
        };
        countryField?.addEventListener('change', toggleLocationMode);
        provinceField?.addEventListener('change', () => {
            optionsFor(cityField, provinceField.value ? 'Selecciona un cantón' : 'Selecciona primero una provincia', ecuadorLocations[provinceField.value] || []);
        });
        toggleLocationMode();

        const syncBilling = () => {
            const useContact = billingSources.find((radio) => radio.checked)?.value === '1';
            const typeLabel = receiptType?.selectedOptions[0]?.textContent || 'comprobante';
            if (legacyInvoice) legacyInvoice.value = receiptType?.value === 'factura' ? '1' : '0';
            customBilling?.toggleAttribute('hidden', useContact);
            customBilling?.querySelectorAll('input, select').forEach((field) => {
                field.disabled = useContact;
                field.required = !useContact;
            });
            if (billingSummary) {
                billingSummary.textContent = useContact
                    ? `El ${typeLabel.toLowerCase()} usará el DNI, nombre y dirección indicados arriba.`
                    : `Completa los datos diferentes que aparecerán en el ${typeLabel.toLowerCase()}.`;
            }
        };
        receiptType?.addEventListener('change', syncBilling);
        billingSources.forEach((radio) => radio.addEventListener('change', syncBilling));
        syncBilling();

        const setValue = (name, value) => {
            const field = checkoutForm.elements.namedItem(name);
            if (field && value !== null && value !== undefined && value !== '') field.value = value;
        };
        const lookup = async () => {
            const dni = String(dniInput?.value || '').replace(/\s+/g, '').toUpperCase();
            if (dniInput) dniInput.value = dni;
            if (dni.length < 5) {
                if (status) status.textContent = '';
                return;
            }

            controller?.abort();
            controller = new AbortController();
            if (status) {
                status.dataset.state = 'loading';
                status.textContent = 'Buscando cliente…';
            }
            try {
                const response = await fetch(checkoutForm.dataset.customerLookupUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ dni }),
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('lookup_failed');
                const result = await response.json();
                if (!result.found) {
                    status.dataset.state = 'empty';
                    status.textContent = 'Cliente nuevo. Completa tus datos.';
                    return;
                }

                const customer = result.customer || {};
                setValue('cliente[nombre]', customer.nombre);
                setValue('cliente[apellido]', customer.apellido);
                setValue('cliente[email]', customer.email);
                setValue('cliente[telefono]', customer.telefono);
                setValue('entrega[direccion]', customer.direccion);
                setValue('entrega[ciudad]', customer.ciudad);
                status.dataset.state = 'found';
                status.textContent = 'Cliente encontrado. Datos completados automáticamente.';
            } catch (error) {
                if (error.name === 'AbortError') return;
                status.dataset.state = 'error';
                status.textContent = 'No pudimos consultar el DNI. Puedes completar los datos manualmente.';
            }
        };

        dniInput?.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(lookup, 500);
        });
        dniInput?.addEventListener('blur', () => {
            clearTimeout(timer);
            lookup();
        });
    }

    document.querySelectorAll('[data-cart-quantity-form]').forEach((form) => {
        const input = form.querySelector('input[name="cantidad"]');
        input?.addEventListener('change', () => {
            if (!input.checkValidity()) return;
            input.readOnly = true;
            form.setAttribute('aria-busy', 'true');
            form.requestSubmit();
        });
    });

    document.querySelectorAll('[data-product-card-form]').forEach((form) => {
        const select = form.querySelector('[data-variant-select]');
        const quantity = form.querySelector('[data-quantity-input]');
        const card = form.closest('.public-product-card');
        const productImage = card.querySelector('[data-product-image]');
        const galleryImages = [...card.querySelectorAll('[data-product-gallery-map] > span')];
        const sizeButtons = [...card.querySelectorAll('[data-variant-size]')];
        const colorButtons = [...card.querySelectorAll('[data-variant-color]')];

        if (!select || !quantity) return;

        const availableOptions = () => [...select.options].filter((option) => !option.disabled && Number(option.dataset.stock) > 0);
        const selectedOption = () => select.options[select.selectedIndex];

        const chooseBySize = (size) => {
            const options = availableOptions();
            const currentColor = selectedOption()?.dataset.color;
            const exact = options.find((option) => option.dataset.size === size && option.dataset.color === currentColor);
            const compatible = exact
                || options.find((option) => option.dataset.size === size)
                || options[0];

            if (compatible) {
                select.value = compatible.value;
                sync();
            }
        };

        const chooseByColor = (color) => {
            const options = availableOptions();
            const currentSize = selectedOption()?.dataset.size;
            const exact = options.find((option) => option.dataset.color === color && option.dataset.size === currentSize);
            const compatible = exact
                || options.find((option) => option.dataset.color === color)
                || options[0];

            if (compatible) {
                select.value = compatible.value;
                sync();
            }
        };

        const sync = () => {
            const option = selectedOption();
            if (!option || option.disabled) return;
            const size = option.dataset.size;
            const color = option.dataset.color;
            const stock = Math.max(1, Number(option.dataset.stock) || 1);

            quantity.max = String(Math.min(99, stock));
            quantity.value = String(Math.min(stock, Math.max(1, Number(quantity.value) || 1)));
            sizeButtons.forEach((button) => {
                button.classList.toggle('active', button.dataset.variantSize === size);
                button.disabled = !availableOptions().some((item) => item.dataset.size === button.dataset.variantSize);
                button.setAttribute('aria-pressed', String(button.dataset.variantSize === size));
            });
            colorButtons.forEach((button) => {
                button.classList.toggle('active', button.dataset.variantColor === color);
                button.disabled = !availableOptions().some((item) => item.dataset.color === button.dataset.variantColor);
                button.setAttribute('aria-pressed', String(button.dataset.variantColor === color));
            });
            if (productImage && galleryImages.length) {
                const matched = galleryImages.find((item) => item.dataset.color === color && item.dataset.size === size)
                    || galleryImages.find((item) => item.dataset.color === color && !item.dataset.size)
                    || galleryImages.find((item) => item.dataset.size === size && !item.dataset.color)
                    || galleryImages.find((item) => !item.dataset.color && !item.dataset.size)
                    || galleryImages[0];
                if (matched?.dataset.url) productImage.src = matched.dataset.url;
            }
        };

        sizeButtons.forEach((button) => button.addEventListener('click', () => chooseBySize(button.dataset.variantSize)));
        colorButtons.forEach((button) => button.addEventListener('click', () => chooseByColor(button.dataset.variantColor)));
        select.addEventListener('change', sync);
        form.querySelector('[data-quantity-minus]')?.addEventListener('click', () => {
            quantity.value = String(Math.max(1, (Number(quantity.value) || 1) - 1));
        });
        form.querySelector('[data-quantity-plus]')?.addEventListener('click', () => {
            quantity.value = String(Math.min(Number(quantity.max) || 99, (Number(quantity.value) || 1) + 1));
        });
        form.addEventListener('submit', () => {
            const submit = form.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = true;
                submit.setAttribute('aria-busy', 'true');
            }
        });

        const initial = availableOptions()[0];
        if (initial) select.value = initial.value;
        sync();
    });
});
