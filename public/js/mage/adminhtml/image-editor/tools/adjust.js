// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

import { SLIDERS, FILTERS, neutralAdjustments, isNeutral, applyAdjustments, autoAdjustments } from '../adjust-engine.js';
import { adjustCanvas } from '../export.js';

const THUMB_WIDTH = 72;
const THUMB_HEIGHT = 54;
const AUTO_SAMPLE_SIDE = 512;

export class AdjustTool {
    constructor(editor) {
        this.editor = editor;
        this._pushed = false;
        this._comparing = false;
    }

    get name() { return 'adjust'; }
    get label() { return 'Adjust'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
    }

    activate() {
        this._pushed = false;
    }

    deactivate() {
        this._stopCompare();
    }

    onPointerDown(ix) {
        if (isNeutral(this.editor.adjustments)) return;
        this._comparing = true;
        this._compareAt(ix);
    }

    onPointerMove(ix) {
        if (isNeutral(this.editor.adjustments)) return;
        this.editor.setCursor('ew-resize');
        if (this._comparing) this._compareAt(ix);
    }

    onPointerUp() {
        this._stopCompare();
    }

    onKeyDown() {}

    renderOverlay(ctx, scale, offsetX, offsetY) {
        const at = this.editor.compareAt;
        if (at === null) return;
        const base = this.editor.baseCanvas;
        const x = Math.round(offsetX + base.width * scale * at) + 0.5;
        const top = offsetY, bottom = offsetY + base.height * scale;

        ctx.save();
        ctx.strokeStyle = '#fafafa';
        ctx.lineWidth = 2;
        ctx.shadowColor = 'rgba(0,0,0,0.6)';
        ctx.shadowBlur = 4;
        ctx.beginPath();
        ctx.moveTo(x, top);
        ctx.lineTo(x, bottom);
        ctx.stroke();

        ctx.font = '600 11px ui-sans-serif, system-ui, sans-serif';
        ctx.textBaseline = 'top';
        ctx.fillStyle = '#fafafa';
        ctx.textAlign = 'right';
        ctx.fillText('Original', x - 8, top + 8);
        ctx.textAlign = 'left';
        ctx.fillText('Adjusted', x + 8, top + 8);
        ctx.restore();
    }

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-adjust';
        el.append(this._renderFilters(), this._renderControls());
        return el;
    }

    _renderFilters() {
        const strip = document.createElement('div');
        strip.className = 'maho-ie-adjust-filters';
        const thumb = this._thumbImage();

        for (const filter of FILTERS) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'maho-ie-adjust-filter';
            btn.classList.toggle('active', this.editor.adjustments.filter === filter.id);

            const canvas = document.createElement('canvas');
            canvas.width = THUMB_WIDTH;
            canvas.height = THUMB_HEIGHT;
            const image = new ImageData(new Uint8ClampedArray(thumb.data), thumb.width, thumb.height);
            canvas.getContext('2d').putImageData(applyAdjustments(image, { ...neutralAdjustments(), filter: filter.id }), 0, 0);

            const label = document.createElement('span');
            label.textContent = filter.label;

            btn.append(canvas, label);
            btn.addEventListener('click', () => {
                if (this.editor.adjustments.filter === filter.id) return;
                this.editor.pushUndo();
                this.editor.adjustments.filter = filter.id;
                this.editor.adjustments.filterStrength = 100;
                this._refresh();
            });
            strip.appendChild(btn);
        }
        return strip;
    }

    _renderControls() {
        const controls = document.createElement('div');
        controls.className = 'maho-ie-adjust-controls';

        const groups = new Map();
        for (const s of SLIDERS) {
            if (!groups.has(s.group)) {
                const group = document.createElement('div');
                group.className = 'maho-ie-adjust-group';
                const title = document.createElement('span');
                title.className = 'maho-ie-adjust-group-title';
                title.textContent = s.group;
                group.appendChild(title);
                groups.set(s.group, group);
                controls.appendChild(group);
            }
            groups.get(s.group).appendChild(this._slider(s.key, s.label, s.min, s.max, 0));
        }

        const actions = document.createElement('div');
        actions.className = 'maho-ie-adjust-group';
        const spacer = document.createElement('span');
        spacer.className = 'maho-ie-adjust-group-title';
        actions.appendChild(spacer);

        const strength = this._slider('filterStrength', 'Filter strength', 0, 100, 100);
        strength.querySelector('input').disabled = this.editor.adjustments.filter === 'none';
        actions.appendChild(strength);

        const autoBtn = document.createElement('button');
        autoBtn.type = 'button';
        autoBtn.className = 'maho-ie-opt-btn';
        autoBtn.textContent = 'Auto';
        autoBtn.title = 'Set the sliders from the light and the colors of the photo';
        autoBtn.addEventListener('click', () => {
            const sample = adjustCanvas(this.editor.baseCanvas, neutralAdjustments(), AUTO_SAMPLE_SIDE);
            const image = sample.getContext('2d').getImageData(0, 0, sample.width, sample.height);
            sample.width = 0;
            this.editor.pushUndo();
            Object.assign(this.editor.adjustments, Object.fromEntries(SLIDERS.map((s) => [s.key, 0])), autoAdjustments(image));
            this._refresh();
        });

        const resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className = 'maho-ie-opt-btn';
        resetBtn.textContent = 'Reset';
        resetBtn.addEventListener('click', () => {
            if (isNeutral(this.editor.adjustments)) return;
            this.editor.pushUndo();
            this.editor.adjustments = neutralAdjustments();
            this._refresh();
        });

        const hint = document.createElement('span');
        hint.className = 'maho-ie-adjust-hint';
        hint.textContent = 'Drag across the photo to compare it with the original. Double-click a slider to reset it.';

        actions.append(autoBtn, resetBtn, hint);
        controls.appendChild(actions);
        return controls;
    }

    _slider(key, labelText, min, max, resetValue) {
        const wrap = document.createElement('div');
        wrap.className = 'maho-ie-slider';

        const label = document.createElement('label');
        label.textContent = labelText;

        const valSpan = document.createElement('span');
        valSpan.className = 'maho-ie-slider-value';

        const input = document.createElement('input');
        input.type = 'range';
        input.min = min;
        input.max = max;
        input.step = 1;
        input.value = this.editor.adjustments[key];
        input.setAttribute('aria-label', labelText);
        valSpan.textContent = input.value;

        const update = (value) => {
            if (!this._pushed) {
                this.editor.pushUndo();
                this._pushed = true;
            }
            input.value = value;
            valSpan.textContent = input.value;
            this.editor.adjustments[key] = parseFloat(input.value);
            this.editor.requestRender();
        };
        input.addEventListener('input', () => update(input.value));
        input.addEventListener('change', () => { this._pushed = false; });
        input.addEventListener('dblclick', () => {
            if (this.editor.adjustments[key] === resetValue) return;
            update(resetValue);
            this._pushed = false;
        });

        wrap.append(label, input, valSpan);
        return wrap;
    }

    /** The center of the photo, cropped to the shape of a thumbnail. */
    _thumbImage() {
        const base = this.editor.baseCanvas;
        const scale = Math.max(THUMB_WIDTH / base.width, THUMB_HEIGHT / base.height);
        const sw = THUMB_WIDTH / scale, sh = THUMB_HEIGHT / scale;
        const canvas = document.createElement('canvas');
        canvas.width = THUMB_WIDTH;
        canvas.height = THUMB_HEIGHT;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(base, (base.width - sw) / 2, (base.height - sh) / 2, sw, sh, 0, 0, THUMB_WIDTH, THUMB_HEIGHT);
        return ctx.getImageData(0, 0, THUMB_WIDTH, THUMB_HEIGHT);
    }

    _compareAt(ix) {
        this.editor.compareAt = Math.max(0, Math.min(1, ix / this.editor.baseCanvas.width));
        this.editor.requestRender();
    }

    _stopCompare() {
        this._comparing = false;
        if (this.editor.compareAt === null) return;
        this.editor.compareAt = null;
        this.editor.requestRender();
    }

    _refresh() {
        this._pushed = false;
        this.editor.setOptions(this.renderOptions());
        this.editor.requestRender();
    }

    destroy() {}
}
