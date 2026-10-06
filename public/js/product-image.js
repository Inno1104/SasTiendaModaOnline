document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-existing-image]').forEach((item) => {
        const picker = item.querySelector('[data-existing-image-picker]');
        const input = item.querySelector('[data-existing-image-input]');
        const remove = item.querySelector('[data-existing-image-delete]');
        const image = picker?.querySelector('img');
        if (!picker || !input || !image || !remove) return;
        picker.addEventListener('click', () => { if (!remove.checked) input.click(); });
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type) || file.size > 4 * 1024 * 1024) {
                input.value = '';
                window.alert('La imagen debe ser JPG, PNG o WEBP y pesar máximo 4 MB.');
                return;
            }
            const url = URL.createObjectURL(file);
            image.src = url;
            image.onload = () => URL.revokeObjectURL(url);
            item.classList.add('is-replaced');
        });
        remove.addEventListener('change', () => {
            item.classList.toggle('is-deleted', remove.checked);
            picker.disabled = remove.checked;
            if (remove.checked) {
                input.value = '';
                item.classList.remove('is-replaced');
            }
        });
    });
    const gallery = document.querySelector('[data-product-gallery]');
    if (gallery) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const maxBytes = 4 * 1024 * 1024;
        const error = document.getElementById('imageClientError');

        const bindItem = (item) => {
            const input = item.querySelector('[data-gallery-input]');
            const picker = item.querySelector('[data-gallery-picker]');
            const image = picker.querySelector('img');
            const placeholder = picker.querySelector('span');
            picker.addEventListener('click', () => input.click());
            input.addEventListener('change', () => {
                const file = input.files?.[0];
                if (!file) return;
                if (!allowedTypes.includes(file.type) || file.size > maxBytes) {
                    input.value = '';
                    error.textContent = 'Cada imagen debe ser JPG, PNG o WEBP y pesar máximo 4 MB.';
                    error.hidden = false;
                    return;
                }
                error.hidden = true;
                const url = URL.createObjectURL(file);
                image.src = url;
                image.hidden = false;
                placeholder.hidden = true;
                image.onload = () => URL.revokeObjectURL(url);
            });
        };
        gallery.querySelectorAll('[data-gallery-item]').forEach(bindItem);

        const syncGroupAssignment = (group) => {
            const option = group.querySelector('[data-gallery-group-variant]')?.selectedOptions[0];
            const size = option?.dataset.size || '';
            const color = option?.dataset.color || '';
            group.querySelectorAll('[data-gallery-size]').forEach((input) => { input.value = size; });
            group.querySelectorAll('[data-gallery-color]').forEach((input) => { input.value = color; });
        };

        const bindGroup = (group) => {
            group.querySelector('[data-gallery-group-variant]')?.addEventListener('change', () => syncGroupAssignment(group));
        };
        gallery.querySelectorAll('[data-gallery-group]').forEach(bindGroup);

        document.querySelector('[data-add-gallery-group]')?.addEventListener('click', () => {
            const sourceGroup = gallery.querySelector('[data-gallery-group]');
            if (!sourceGroup) return;
            const start = gallery.querySelectorAll('[data-gallery-item]').length;
            const group = sourceGroup.cloneNode(true);
            group.querySelector('[data-gallery-group-variant]').value = '';
            group.querySelectorAll('[data-gallery-item]').forEach((item, offset) => {
                const index = start + offset;
                item.querySelector('img').hidden = true;
                item.querySelector('img').removeAttribute('src');
                item.querySelector('.product-gallery-preview span').hidden = false;
                const input = item.querySelector('[data-gallery-input]');
                input.value = '';
                input.name = `imagenes[${index}][archivo]`;
                item.querySelector('[data-gallery-size]').name = `imagenes[${index}][talla]`;
                item.querySelector('[data-gallery-color]').name = `imagenes[${index}][color]`;
                bindItem(item);
            });
            gallery.append(group);
            bindGroup(group);
            syncGroupAssignment(group);
            refreshAssignments();
        });

        const refreshAssignments = () => {
            const existingVariants = [...document.querySelectorAll('[data-gallery-existing-variant]')].map((row) => ({
                size: row.querySelector('[data-existing-size]')?.value.trim() || row.dataset.size?.trim() || '',
                color: row.querySelector('[data-existing-color]')?.value.trim() || row.dataset.color?.trim() || '',
            }));
            const newVariants = [...document.querySelectorAll('[data-variant-row]')].flatMap((row) => {
                const rawSize = row.querySelector('[data-variant-size]')?.value.trim() || '';
                const color = row.querySelector('[data-variant-color]')?.value.trim() || '';
                const parsedSizes = rawSize.split(/[,;|]+/).map((size) => size.trim()).filter(Boolean);
                return parsedSizes.length > 1 ? [{ size: '', color }] : [{ size: parsedSizes[0] || '', color }];
            });
            const variants = [...new Map([...existingVariants, ...newVariants]
                .filter(({ color }) => color)
                .map((variant) => [`${variant.size.toLocaleLowerCase()}|${variant.color.toLocaleLowerCase()}`, variant])).values()];
            const refresh = (select) => {
                const current = select.value;
                const options = variants.map(({ size, color }) => {
                    const option = new Option(`${size || 'Todas las tallas'} / ${color}`, `${size}\u0000${color}`);
                    option.dataset.size = size;
                    option.dataset.color = color;
                    return option;
                });
                select.replaceChildren(new Option('Todas las variantes', ''), ...options);
                if (options.some((option) => option.value === current)) select.value = current;
            };
            gallery.querySelectorAll('[data-gallery-group-variant]').forEach((select) => {
                refresh(select);
                syncGroupAssignment(select.closest('[data-gallery-group]'));
            });
        };
        document.getElementById('variantsContainer')?.addEventListener('input', refreshAssignments);
        document.querySelector('.existing-variants-list')?.addEventListener('input', refreshAssignments);
        document.getElementById('addVariant')?.addEventListener('click', () => setTimeout(refreshAssignments));
        document.getElementById('productForm')?.addEventListener('submit', (event) => {
            let valid = true;
            gallery.querySelectorAll('[data-gallery-group]').forEach((group) => {
                const select = group.querySelector('[data-gallery-group-variant]');
                const hasFiles = [...group.querySelectorAll('[data-gallery-input]')].some((input) => input.files?.length);
                select.setCustomValidity(hasFiles && !select.value ? 'Selecciona la talla y el color de estas fotografías.' : '');
                if (!select.checkValidity()) valid = false;
            });
            if (!valid) {
                event.preventDefault();
                event.stopImmediatePropagation();
                document.getElementById('productForm')?.reportValidity();
            }
        }, true);
        refreshAssignments();
        return;
    }

    const input = document.getElementById('imagenInput');
    const previewButton = document.getElementById('img-preview');
    const selectButton = document.getElementById('selectImage');
    const preview = document.getElementById('img-shown');
    const placeholder = document.getElementById('img-placeholder');
    const error = document.getElementById('imageClientError');

    if (!input || !previewButton || !preview || !placeholder) return;

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maxBytes = 4 * 1024 * 1024;
    const openPicker = () => input.click();

    previewButton.addEventListener('click', openPicker);
    selectButton?.addEventListener('click', openPicker);
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;

        if (!allowedTypes.includes(file.type) || file.size > maxBytes) {
            input.value = '';
            if (error) {
                error.textContent = 'Selecciona una imagen JPG, PNG o WEBP de máximo 4 MB.';
                error.hidden = false;
            }
            return;
        }

        if (error) error.hidden = true;
        const objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.hidden = false;
        preview.style.display = 'block';
        placeholder.hidden = true;
        placeholder.style.display = 'none';
        preview.onload = () => URL.revokeObjectURL(objectUrl);
    });
});
