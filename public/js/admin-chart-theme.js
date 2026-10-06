(function (window, document) {
    'use strict';

    const fallbackAccent = '#e72d86';

    const normalizeHex = (value) => {
        const hex = String(value || '').trim().replace('#', '');
        if (/^[0-9a-f]{3}$/i.test(hex)) {
            return `#${hex.split('').map(character => character + character).join('')}`;
        }

        return /^[0-9a-f]{6}$/i.test(hex) ? `#${hex}` : fallbackAccent;
    };

    const hexToRgb = (value) => {
        const hex = normalizeHex(value).slice(1);
        return {
            r: parseInt(hex.slice(0, 2), 16),
            g: parseInt(hex.slice(2, 4), 16),
            b: parseInt(hex.slice(4, 6), 16),
        };
    };

    const rgbToHsl = ({ r, g, b }) => {
        r /= 255;
        g /= 255;
        b /= 255;
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const delta = max - min;
        let h = 0;

        if (delta) {
            if (max === r) h = 60 * (((g - b) / delta) % 6);
            else if (max === g) h = 60 * (((b - r) / delta) + 2);
            else h = 60 * (((r - g) / delta) + 4);
        }

        const l = (max + min) / 2;
        const s = delta ? delta / (1 - Math.abs(2 * l - 1)) : 0;
        return { h: (h + 360) % 360, s: s * 100, l: l * 100 };
    };

    const accent = normalizeHex(
        getComputedStyle(document.body).getPropertyValue('--accent') || fallbackAccent
    );
    const rgb = hexToRgb(accent);
    const hsl = rgbToHsl(rgb);
    const hueSteps = [0, 28, -28, 56, -56, 92, -92, 145];

    const tone = (index, lightnessOffset = 0) => {
        const hue = (hsl.h + hueSteps[index % hueSteps.length] + 360) % 360;
        const saturation = Math.max(48, Math.min(88, hsl.s || 68));
        const lightness = Math.max(34, Math.min(65, hsl.l + lightnessOffset));
        return `hsl(${Math.round(hue)} ${Math.round(saturation)}% ${Math.round(lightness)}%)`;
    };

    window.AdminChartTheme = Object.freeze({
        accent,
        fill(alpha = 0.1) {
            return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
        },
        tone,
        palette(count = 6) {
            return Array.from({ length: count }, (_, index) => tone(index, index % 2 ? 3 : 0));
        },
    });
})(window, document);
