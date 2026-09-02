document.addEventListener('DOMContentLoaded', () => {
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
