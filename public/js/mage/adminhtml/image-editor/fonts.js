/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const GENERIC_FONTS = [
    { label: 'Sans-serif', value: 'sans-serif' },
    { label: 'Serif', value: 'serif' },
    { label: 'Monospace', value: 'monospace' },
    { label: 'Cursive', value: 'cursive' },
];

const NAMED_FONTS = [
    { label: 'Arial', value: 'Arial', fallback: 'sans-serif' },
    { label: 'Helvetica', value: 'Helvetica', fallback: 'sans-serif' },
    { label: 'Verdana', value: 'Verdana', fallback: 'sans-serif' },
    { label: 'Trebuchet MS', value: 'Trebuchet MS', fallback: 'sans-serif' },
    { label: 'Georgia', value: 'Georgia', fallback: 'serif' },
    { label: 'Times New Roman', value: 'Times New Roman', fallback: 'serif' },
    { label: 'Garamond', value: 'Garamond', fallback: 'serif' },
    { label: 'Courier New', value: 'Courier New', fallback: 'monospace' },
    { label: 'Impact', value: 'Impact', fallback: 'sans-serif' },
    { label: 'Comic Sans MS', value: 'Comic Sans MS', fallback: 'cursive' },
];

function isFontAvailable(family) {
    const testString = 'mmmmmmmmmmlli';
    const size = '72px';
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    ctx.font = `${size} monospace`;
    const monoWidth = ctx.measureText(testString).width;
    ctx.font = `${size} serif`;
    const serifWidth = ctx.measureText(testString).width;

    ctx.font = `${size} '${family}', monospace`;
    const testMono = ctx.measureText(testString).width;
    ctx.font = `${size} '${family}', serif`;
    const testSerif = ctx.measureText(testString).width;

    return testMono !== monoWidth || testSerif !== serifWidth;
}

let _cachedList = null;

export function getFontList() {
    if (_cachedList) return _cachedList;

    const available = NAMED_FONTS.filter(f => isFontAvailable(f.value));
    _cachedList = [
        ...GENERIC_FONTS,
        ...available.map(f => ({
            label: f.label,
            value: `'${f.value}', ${f.fallback}`,
        })),
    ];
    return _cachedList;
}

export function createFontSelect(currentValue, onChange) {
    const fonts = getFontList();
    const group = document.createElement('div');
    group.className = 'maho-ie-select';
    const label = document.createElement('label');
    label.textContent = 'Font';
    const select = document.createElement('select');

    for (const font of fonts) {
        const opt = document.createElement('option');
        opt.value = font.value;
        opt.textContent = font.label;
        opt.style.fontFamily = font.value;
        if (font.value === currentValue) opt.selected = true;
        select.appendChild(opt);
    }

    select.addEventListener('change', () => onChange(select.value));
    group.append(label, select);
    return group;
}
