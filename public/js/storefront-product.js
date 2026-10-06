document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-product-detail]');
    if (!root) return;

    const variants = JSON.parse(root.dataset.variants || '[]');
    const images = JSON.parse(root.dataset.images || '[]');
    const colorsRoot = root.querySelector('[data-product-colors]');
    const sizesRoot = root.querySelector('[data-product-sizes]');
    const thumbnailsRoot = root.querySelector('[data-product-thumbnails]');
    const mainImage = root.querySelector('[data-product-main-image]');
    const selectedColorLabel = root.querySelector('[data-selected-color]');
    const variantInput = root.querySelector('[data-selected-variant]');
    const quantity = root.querySelector('[data-detail-quantity]');
    const stockLabel = root.querySelector('[data-detail-stock]');
    const addButton = root.querySelector('[data-detail-add]');
    const fitNote = root.querySelector('[data-fit-note] p');
    const sizeGuide = root.querySelector('[data-size-guide]');
    const form = root.querySelector('[data-detail-cart-form]');
    const quantityMinus = root.querySelector('[data-quantity-minus]');
    const quantityPlus = root.querySelector('[data-quantity-plus]');
    const quantityLimit = root.querySelector('[data-quantity-limit]');
    const isEditing = root.dataset.cartEditing === 'true';
    const query = new URLSearchParams(window.location.search);
    const requestedVariantId = Number(query.get('variante'));
    const requestedQuantity = Number(query.get('cantidad'));
    let selectedColor = null;
    let selectedSize = null;

    const normalized = (value) => String(value || '').trim().toLocaleLowerCase();
    const unique = (values) => [...new Set(values.filter(Boolean))];
    const available = variants.filter((variant) => Number(variant.stock) > 0);
    const colors = unique(variants.map((variant) => variant.color));
    const sizes = unique(variants.map((variant) => variant.size));
    const imageForColor = (color) => images.find((image) => normalized(image.color) === normalized(color));

    const renderColors = () => {
        colorsRoot.replaceChildren(...colors.map((color) => {
            const button = document.createElement('button');
            const image = imageForColor(color);
            button.type = 'button';
            button.className = 'product-color-card';
            button.dataset.color = color;
            button.disabled = !available.some((variant) => variant.color === color);
            button.setAttribute('aria-label', `Elegir color ${color}`);
            if (image?.url) {
                const picture = document.createElement('img');
                picture.src = image.url;
                picture.alt = `Vista del color ${color}`;
                button.append(picture);
            } else {
                const fallback = document.createElement('span');
                fallback.className = 'product-color-fallback';
                fallback.innerHTML = '<i aria-hidden="true"></i>';
                button.append(fallback);
            }
            const label = document.createElement('span');
            label.textContent = color;
            button.append(label);
            button.addEventListener('click', () => { selectedColor = color; selectedSize = null; sync(); });
            return button;
        }));
    };

    const renderSizes = () => {
        sizesRoot.replaceChildren(...sizes.map((size) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'product-size-button';
            button.textContent = size;
            button.dataset.size = size;
            button.disabled = !selectedColor || !available.some((variant) => variant.color === selectedColor && variant.size === size);
            button.addEventListener('click', () => { selectedSize = size; sync(); });
            return button;
        }));
    };

    const relevantImages = () => {
        const selectedColorImages = images.filter((image) => normalized(image.color) === normalized(selectedColor));
        const exact = selectedColorImages.filter((image) => normalized(image.size) === normalized(selectedSize));
        const sharedByColor = selectedColorImages.filter((image) => !image.size);
        const generic = images.filter((image) => !image.color && !image.size);
        const matching = unique([...exact, ...sharedByColor, ...selectedColorImages].map((image) => image.url));

        return matching.length ? matching : unique(generic.map((image) => image.url));
    };

    const renderGallery = () => {
        const urls = relevantImages();
        thumbnailsRoot.replaceChildren(...urls.map((url, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `product-thumbnail${index === 0 ? ' active' : ''}`;
            const image = document.createElement('img');
            image.src = url;
            image.alt = `Vista ${index + 1}`;
            button.append(image);
            button.addEventListener('click', () => {
                if (mainImage) mainImage.src = url;
                thumbnailsRoot.querySelectorAll('.product-thumbnail').forEach((item) => item.classList.toggle('active', item === button));
            });
            return button;
        }));
        if (mainImage && urls[0]) mainImage.src = urls[0];
    };

    const sync = () => {
        colorsRoot.querySelectorAll('[data-color]').forEach((button) => button.classList.toggle('active', button.dataset.color === selectedColor));
        if (selectedColorLabel) selectedColorLabel.textContent = selectedColor || 'Selecciona un color';
        renderSizes();
        sizesRoot.querySelectorAll('[data-size]').forEach((button) => button.classList.toggle('active', button.dataset.size === selectedSize));
        renderGallery();

        const variant = available.find((item) => item.color === selectedColor && item.size === selectedSize);
        variantInput.value = variant?.id || '';
        quantity.disabled = !variant;
        quantityMinus.disabled = !variant;
        quantityPlus.disabled = !variant;
        addButton.disabled = !variant;
        quantity.replaceChildren(...Array.from({ length: Math.min(99, Number(variant?.stock || 1)) }, (_, index) => new Option(String(index + 1), String(index + 1))));
        if (quantityLimit) quantityLimit.textContent = variant ? `Máx. ${Math.min(99, Number(variant.stock))} unidades` : 'Selecciona una variante';
        stockLabel.textContent = variant ? `${variant.stock} unidades disponibles en ${variant.size} / ${variant.color}` : 'Selecciona un color y una talla disponibles';
        if (fitNote) {
            fitNote.textContent = variant
                ? (variant.sizeDetail || `Seleccionaste talla ${variant.size} en color ${variant.color}. Hay ${variant.stock} unidades disponibles.`)
                : 'Selecciona un color y una talla para consultar la combinación y su disponibilidad.';
        }
        addButton.innerHTML = variant
            ? `<i class="fa-solid ${isEditing ? 'fa-rotate' : 'fa-cart-plus'}"></i> ${isEditing ? 'Guardar cambios' : 'Agregar al carrito'}`
            : '<i class="fa-solid fa-cart-plus"></i> Selecciona color y talla';
    };

    renderColors();
    const requestedVariant = available.find((variant) => Number(variant.id) === requestedVariantId);
    selectedColor = requestedVariant?.color || available[0]?.color || null;
    selectedSize = requestedVariant?.size || available.find((variant) => variant.color === selectedColor)?.size || null;
    sync();
    if (requestedVariant && requestedQuantity > 0 && quantity) {
        quantity.value = String(Math.min(requestedQuantity, Number(requestedVariant.stock), 99));
    }
    sizeGuide?.addEventListener('click', () => {
        const variant = available.find((item) => item.color === selectedColor && item.size === selectedSize);
        if (fitNote) {
            fitNote.textContent = variant?.sizeDetail
                || (variant
                    ? `No hay medidas adicionales registradas para la talla ${variant.size}. Consulta a la tienda si necesitas ayuda.`
                    : 'Selecciona primero un color y una talla para consultar sus medidas.');
        }
        root.querySelector('[data-fit-note]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    quantityMinus?.addEventListener('click', () => {
        quantity.value = String(Math.max(1, (Number(quantity.value) || 1) - 1));
    });
    quantityPlus?.addEventListener('click', () => {
        quantity.value = String(Math.min(Number(quantity.options.length) || 1, (Number(quantity.value) || 1) + 1));
    });
    form?.addEventListener('submit', () => { addButton.disabled = true; addButton.setAttribute('aria-busy', 'true'); });
});
