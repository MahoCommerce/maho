/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

export class FrameTool {
    constructor(editor) {
        this.editor = editor;
        this._pushed = false;
    }

    get name() { return 'frame'; }
    get label() { return 'Frame'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="7" y="7" width="10" height="10"/></svg>';
    }

    activate() {
        this._pushed = false;
        if (!this.editor.frame) {
            this.editor.frame = { type: 'solid', color: '#ffffff', width: 20 };
            this.editor.requestRender();
        }
    }

    deactivate() {}
    onPointerDown() {}
    onPointerMove() {}
    onPointerUp() {}
    onKeyDown() {}
    renderOverlay() {}

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-options-group';
        const frame = this.editor.frame;

        const types = [
            { type: 'none', label: 'None' },
            { type: 'solid', label: 'Solid' },
            { type: 'stroke', label: 'Stroke' },
            { type: 'shadow', label: 'Shadow' },
            { type: 'polaroid', label: 'Polaroid' },
        ];

        const typeLabel = document.createElement('span');
        typeLabel.style.cssText = 'font-size:11px;color:#a1a1aa';
        typeLabel.textContent = 'Style:';
        el.appendChild(typeLabel);

        for (const t of types) {
            const btn = document.createElement('button');
            btn.className = 'maho-ie-opt-btn' + (frame && frame.type === t.type ? ' active' : '') + (!frame && t.type === 'none' ? ' active' : '');
            btn.textContent = t.label;
            btn.addEventListener('click', () => {
                el.querySelectorAll('.maho-ie-opt-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this._pushed = false;
                if (t.type === 'none') {
                    this.editor.pushUndo();
                    this.editor.frame = null;
                } else {
                    this.editor.pushUndo();
                    const defaults = {
                        type: t.type,
                        color: frame?.color || '#ffffff',
                        width: frame?.width || 20,
                    };
                    if (t.type === 'stroke') {
                        defaults.strokeWidth = frame?.strokeWidth || 3;
                        defaults.padding = frame?.padding || 10;
                        defaults.position = frame?.position || 'outside';
                    }
                    this.editor.frame = defaults;
                }
                this.editor.setOptions(this.renderOptions());
                this.editor.requestRender();
            });
            el.appendChild(btn);
        }

        if (frame && frame.type !== 'none') {
            el.appendChild(this._divider());

            if (frame.type === 'stroke') {
                this._addStrokeOptions(el, frame);
            } else {
                // Width slider (solid, shadow, polaroid)
                this._addSlider(el, 'Width', 5, 100, frame.width, (v) => {
                    frame.width = v;
                    this.editor.requestRender();
                });
            }

            // Color (not for shadow)
            if (frame.type !== 'shadow') {
                this._addColor(el, 'Color', frame.color, (v) => {
                    frame.color = v;
                    this.editor.requestRender();
                });
            }
        }

        return el;
    }

    _addStrokeOptions(el, frame) {
        // Position toggle
        const posLabel = document.createElement('span');
        posLabel.style.cssText = 'font-size:11px;color:#a1a1aa';
        posLabel.textContent = 'Position:';
        el.appendChild(posLabel);

        for (const pos of ['inside', 'outside']) {
            const btn = document.createElement('button');
            btn.className = 'maho-ie-opt-btn' + (frame.position === pos ? ' active' : '');
            btn.textContent = pos.charAt(0).toUpperCase() + pos.slice(1);
            btn.addEventListener('click', () => {
                frame.position = pos;
                this.editor.setOptions(this.renderOptions());
                this.editor.requestRender();
            });
            el.appendChild(btn);
        }

        el.appendChild(this._divider());

        // Stroke thickness
        this._addSlider(el, 'Thickness', 1, 20, frame.strokeWidth, (v) => {
            frame.strokeWidth = v;
            this.editor.requestRender();
        });

        // Padding / gap from edge
        this._addSlider(el, 'Padding', 0, 60, frame.padding, (v) => {
            frame.padding = v;
            this.editor.requestRender();
        });
    }

    _addSlider(el, label, min, max, value, onChange) {
        const group = document.createElement('div');
        group.className = 'maho-ie-slider';
        const lbl = document.createElement('label');
        lbl.textContent = label;
        const val = document.createElement('span');
        val.className = 'maho-ie-slider-value';
        val.textContent = value;
        const input = document.createElement('input');
        input.type = 'range';
        input.min = min;
        input.max = max;
        input.value = value;
        input.addEventListener('input', () => {
            if (!this._pushed) { this.editor.pushUndo(); this._pushed = true; }
            const v = parseInt(input.value);
            val.textContent = v;
            onChange(v);
        });
        group.append(lbl, input, val);
        el.appendChild(group);
    }

    _addColor(el, label, value, onChange) {
        const group = document.createElement('div');
        group.className = 'maho-ie-color';
        const lbl = document.createElement('label');
        lbl.textContent = label;
        const input = document.createElement('input');
        input.type = 'color';
        input.value = value;
        input.addEventListener('input', () => {
            if (!this._pushed) { this.editor.pushUndo(); this._pushed = true; }
            onChange(input.value);
        });
        group.append(lbl, input);
        el.appendChild(group);
    }

    _divider() {
        const d = document.createElement('div');
        d.className = 'maho-ie-options-divider';
        return d;
    }

    destroy() {}
}
