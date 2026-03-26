/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

export class AdjustTool {
    constructor(editor) {
        this.editor = editor;
        this._pushed = false;
    }

    get name() { return 'adjust'; }
    get label() { return 'Adjust'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
    }

    activate() {
        this._pushed = false;
    }

    deactivate() {}
    onPointerDown() {}
    onPointerMove() {}
    onPointerUp() {}
    onKeyDown() {}
    renderOverlay() {}

    renderOptions() {
        const el = document.createElement('div');
        el.style.cssText = 'display:flex;gap:12px;flex-wrap:wrap;align-items:center';

        const adj = this.editor.adjustments;

        const sliders = [
            { key: 'brightness', label: 'Brightness', min: 0, max: 200, val: adj.brightness, step: 1 },
            { key: 'contrast', label: 'Contrast', min: 0, max: 200, val: adj.contrast, step: 1 },
            { key: 'hue', label: 'Hue', min: -180, max: 180, val: adj.hue, step: 1 },
            { key: 'saturation', label: 'Saturation', min: 0, max: 200, val: adj.saturation, step: 1 },
            { key: 'warmth', label: 'Warmth', min: 0, max: 100, val: adj.warmth, step: 1 },
        ];

        for (const s of sliders) {
            const wrap = document.createElement('div');
            wrap.className = 'maho-ie-slider';

            const label = document.createElement('label');
            label.textContent = s.label;

            const valSpan = document.createElement('span');
            valSpan.className = 'maho-ie-slider-value';
            valSpan.textContent = s.val;

            const input = document.createElement('input');
            input.type = 'range';
            input.min = s.min;
            input.max = s.max;
            input.value = s.val;
            input.step = s.step;
            input.addEventListener('input', () => {
                if (!this._pushed) {
                    this.editor.pushUndo();
                    this._pushed = true;
                }
                valSpan.textContent = input.value;
                this.editor.adjustments[s.key] = parseFloat(input.value);
                this.editor.requestRender();
            });

            wrap.append(label, input, valSpan);
            el.appendChild(wrap);
        }

        // Reset button
        const resetBtn = document.createElement('button');
        resetBtn.className = 'maho-ie-opt-btn';
        resetBtn.textContent = 'Reset';
        resetBtn.addEventListener('click', () => {
            this.editor.pushUndo();
            Object.assign(this.editor.adjustments, {
                brightness: 100,
                contrast: 100,
                hue: 0,
                saturation: 100,
                warmth: 0,
            });
            this._pushed = false;
            this.editor.setOptions(this.renderOptions());
            this.editor.requestRender();
        });
        el.appendChild(resetBtn);

        return el;
    }

    destroy() {}
}
