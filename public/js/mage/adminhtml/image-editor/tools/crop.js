/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const HANDLE_SIZE = 8;
const MIN_CROP = 10;

export class CropTool {
    constructor(editor) {
        this.editor = editor;
        this.region = null;
        this._dragging = null;
        this._startRegion = null;
        this._startPos = null;
    }

    get name() { return 'crop'; }
    get label() { return 'Crop'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.13 1 6 16a2 2 0 0 0 2 2h15"/><path d="M1 6.13 16 6a2 2 0 0 1 2 2v15"/></svg>';
    }

    activate() {
        const w = this.editor.baseCanvas.width;
        const h = this.editor.baseCanvas.height;
        this.region = { x: 0, y: 0, w, h };
    }

    deactivate() {
        this.region = null;
    }

    onPointerDown(ix, iy) {
        if (!this.region) return;

        const handle = this._hitHandle(ix, iy);
        if (handle) {
            this._dragging = handle;
        } else if (this._insideRegion(ix, iy)) {
            this._dragging = 'move';
        } else {
            this._dragging = 'new';
            this.region = { x: ix, y: iy, w: 0, h: 0 };
        }
        this._startRegion = { ...this.region };
        this._startPos = { x: ix, y: iy };
    }

    onPointerMove(ix, iy) {
        // Update cursor based on what's under the pointer
        if (!this._dragging && this.region) {
            const handle = this._hitHandle(ix, iy);
            if (handle) {
                this.editor.setCursor(handleCursor(handle));
            } else if (this._insideRegion(ix, iy)) {
                this.editor.setCursor('move');
            } else {
                this.editor.setCursor('crosshair');
            }
        }

        if (!this._dragging) return;

        const dx = ix - this._startPos.x;
        const dy = iy - this._startPos.y;
        const sr = this._startRegion;
        const imgW = this.editor.baseCanvas.width;
        const imgH = this.editor.baseCanvas.height;

        if (this._dragging === 'move') {
            this.region.x = clamp(sr.x + dx, 0, imgW - sr.w);
            this.region.y = clamp(sr.y + dy, 0, imgH - sr.h);
        } else if (this._dragging === 'new') {
            this.region.w = ix - this.region.x;
            this.region.h = iy - this.region.y;
        } else {
            this._resizeHandle(this._dragging, dx, dy, sr, imgW, imgH);
        }

        this._normalize();
        this.editor.requestRender();
    }

    onPointerUp() {
        this._dragging = null;
        this._startRegion = null;
        this._startPos = null;
    }

    onKeyDown(e) {
        if (e.key === 'Enter') {
            this._apply();
        }
    }

    renderOverlay(ctx, scale, offsetX, offsetY) {
        if (!this.region) return;
        const r = this.region;
        const imgW = this.editor.baseCanvas.width;
        const imgH = this.editor.baseCanvas.height;

        const rx = r.x * scale + offsetX;
        const ry = r.y * scale + offsetY;
        const rw = r.w * scale;
        const rh = r.h * scale;
        const fullW = imgW * scale;
        const fullH = imgH * scale;

        // Dim outside crop
        ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
        // Top
        ctx.fillRect(offsetX, offsetY, fullW, ry - offsetY);
        // Bottom
        ctx.fillRect(offsetX, ry + rh, fullW, (offsetY + fullH) - (ry + rh));
        // Left
        ctx.fillRect(offsetX, ry, rx - offsetX, rh);
        // Right
        ctx.fillRect(rx + rw, ry, (offsetX + fullW) - (rx + rw), rh);

        // Crop border
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 1;
        ctx.strokeRect(rx, ry, rw, rh);

        // Rule of thirds
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 0.5;
        for (let i = 1; i <= 2; i++) {
            ctx.beginPath();
            ctx.moveTo(rx + (rw / 3) * i, ry);
            ctx.lineTo(rx + (rw / 3) * i, ry + rh);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(rx, ry + (rh / 3) * i);
            ctx.lineTo(rx + rw, ry + (rh / 3) * i);
            ctx.stroke();
        }

        // Handles
        ctx.fillStyle = '#fff';
        for (const pos of ['tl', 'tr', 'bl', 'br', 't', 'b', 'l', 'r']) {
            const { hx, hy } = this._handlePos(pos, rx, ry, rw, rh);
            ctx.fillRect(hx - HANDLE_SIZE / 2, hy - HANDLE_SIZE / 2, HANDLE_SIZE, HANDLE_SIZE);
        }
    }

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-options-group';

        // Preset aspect ratios grouped with separators
        const groups = [
            [
                { label: 'Free', value: null },
                { label: '1:1', value: 1 },
            ],
            [
                { label: '4:3', value: 4 / 3 },
                { label: '3:2', value: 3 / 2 },
                { label: '16:9', value: 16 / 9 },
                { label: '21:9', value: 21 / 9 },
            ],
            [
                { label: '2:3', value: 2 / 3 },
                { label: '4:5', value: 4 / 5 },
                { label: '9:16', value: 9 / 16 },
            ],
        ];

        const selectRatio = (activeBtn, value) => {
            el.querySelectorAll('.maho-ie-opt-btn').forEach(b => b.classList.remove('active'));
            activeBtn.classList.add('active');
            customInputs.style.display = 'none';
            if (value) {
                this._applyAspectRatio(value);
            }
        };

        for (let gi = 0; gi < groups.length; gi++) {
            for (const p of groups[gi]) {
                const btn = document.createElement('button');
                btn.className = 'maho-ie-opt-btn';
                btn.textContent = p.label;
                btn.addEventListener('click', () => selectRatio(btn, p.value));
                if (!p.value) btn.classList.add('active');
                el.appendChild(btn);
            }
            const div = document.createElement('div');
            div.className = 'maho-ie-options-divider';
            el.appendChild(div);
        }

        // Custom aspect ratio
        const customBtn = document.createElement('button');
        customBtn.className = 'maho-ie-opt-btn';
        customBtn.textContent = 'Custom';
        customBtn.addEventListener('click', () => {
            el.querySelectorAll('.maho-ie-opt-btn').forEach(b => b.classList.remove('active'));
            customBtn.classList.add('active');
            customInputs.style.display = 'flex';
            applyCustom();
        });
        el.appendChild(customBtn);

        const customInputs = document.createElement('div');
        customInputs.style.cssText = 'display:none;align-items:center;gap:4px';

        const wInput = document.createElement('input');
        wInput.type = 'number';
        wInput.min = 1;
        wInput.max = 9999;
        wInput.value = 16;
        wInput.className = 'maho-ie-number-input';

        const separator = document.createElement('span');
        separator.textContent = ':';
        separator.style.color = '#aaa';

        const hInput = document.createElement('input');
        hInput.type = 'number';
        hInput.min = 1;
        hInput.max = 9999;
        hInput.value = 9;
        hInput.className = 'maho-ie-number-input';

        const applyCustom = () => {
            const w = parseInt(wInput.value);
            const h = parseInt(hInput.value);
            if (w > 0 && h > 0) {
                this._applyAspectRatio(w / h);
            }
        };

        wInput.addEventListener('change', applyCustom);
        hInput.addEventListener('change', applyCustom);

        customInputs.append(wInput, separator, hInput);
        el.appendChild(customInputs);

        // Divider
        const div = document.createElement('div');
        div.className = 'maho-ie-options-divider';
        el.appendChild(div);

        // Apply
        const applyBtn = document.createElement('button');
        applyBtn.className = 'maho-ie-opt-btn';
        applyBtn.innerHTML = 'Apply Crop';
        applyBtn.addEventListener('click', () => this._apply());
        el.appendChild(applyBtn);

        return el;
    }

    _apply() {
        const r = this.region;
        if (!r || r.w < MIN_CROP || r.h < MIN_CROP) return;

        this.editor.pushUndo();
        const src = this.editor.baseCanvas;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(r.w);
        canvas.height = Math.round(r.h);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(src, -Math.round(r.x), -Math.round(r.y));

        // Offset annotations and redactions to match the cropped coordinate space
        for (const a of this.editor.annotations) {
            offsetAnnotation(a, -r.x, -r.y);
        }
        for (const rd of this.editor.redactions) {
            rd.x -= r.x;
            rd.y -= r.y;
        }

        this.editor.replaceBase(canvas);

        this.region = { x: 0, y: 0, w: canvas.width, h: canvas.height };
        this.editor.requestRender();
    }

    _applyAspectRatio(ratio) {
        const imgW = this.editor.baseCanvas.width;
        const imgH = this.editor.baseCanvas.height;
        let w = imgW;
        let h = imgW / ratio;
        if (h > imgH) {
            h = imgH;
            w = imgH * ratio;
        }
        this.region = {
            x: (imgW - w) / 2,
            y: (imgH - h) / 2,
            w,
            h,
        };
        this.editor.requestRender();
    }

    _insideRegion(x, y) {
        const r = this.region;
        return x >= r.x && x <= r.x + r.w && y >= r.y && y <= r.y + r.h;
    }

    _hitHandle(ix, iy) {
        const r = this.region;
        const scale = this.editor.scale;
        const threshold = (HANDLE_SIZE + 4) / scale;

        const positions = {
            tl: { x: r.x, y: r.y },
            tr: { x: r.x + r.w, y: r.y },
            bl: { x: r.x, y: r.h + r.y },
            br: { x: r.x + r.w, y: r.y + r.h },
            t: { x: r.x + r.w / 2, y: r.y },
            b: { x: r.x + r.w / 2, y: r.y + r.h },
            l: { x: r.x, y: r.y + r.h / 2 },
            r: { x: r.x + r.w, y: r.y + r.h / 2 },
        };

        for (const [name, pos] of Object.entries(positions)) {
            if (Math.abs(ix - pos.x) < threshold && Math.abs(iy - pos.y) < threshold) {
                return name;
            }
        }
        return null;
    }

    _handlePos(pos, rx, ry, rw, rh) {
        const map = {
            tl: { hx: rx, hy: ry },
            tr: { hx: rx + rw, hy: ry },
            bl: { hx: rx, hy: ry + rh },
            br: { hx: rx + rw, hy: ry + rh },
            t: { hx: rx + rw / 2, hy: ry },
            b: { hx: rx + rw / 2, hy: ry + rh },
            l: { hx: rx, hy: ry + rh / 2 },
            r: { hx: rx + rw, hy: ry + rh / 2 },
        };
        return map[pos];
    }

    _resizeHandle(handle, dx, dy, sr, imgW, imgH) {
        const r = this.region;
        if (handle.includes('l')) {
            r.x = clamp(sr.x + dx, 0, sr.x + sr.w - MIN_CROP);
            r.w = sr.w - (r.x - sr.x);
        }
        if (handle.includes('r')) {
            r.w = clamp(sr.w + dx, MIN_CROP, imgW - sr.x);
        }
        if (handle.includes('t')) {
            r.y = clamp(sr.y + dy, 0, sr.y + sr.h - MIN_CROP);
            r.h = sr.h - (r.y - sr.y);
        }
        if (handle.includes('b')) {
            r.h = clamp(sr.h + dy, MIN_CROP, imgH - sr.y);
        }
    }

    _normalize() {
        const r = this.region;
        if (r.w < 0) {
            r.x += r.w;
            r.w = -r.w;
        }
        if (r.h < 0) {
            r.y += r.h;
            r.h = -r.h;
        }
    }

    destroy() {}
}

function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
}

function offsetAnnotation(a, dx, dy) {
    switch (a.type) {
        case 'rect':
        case 'text':
        case 'image':
            a.x += dx;
            a.y += dy;
            break;
        case 'ellipse':
            a.cx += dx;
            a.cy += dy;
            break;
        case 'line':
        case 'arrow':
            a.x1 += dx;
            a.y1 += dy;
            a.x2 += dx;
            a.y2 += dy;
            break;
        case 'freehand':
            for (const p of a.points) {
                p.x += dx;
                p.y += dy;
            }
            break;
    }
}

function handleCursor(handle) {
    const map = {
        tl: 'nwse-resize', tr: 'nesw-resize', bl: 'nesw-resize', br: 'nwse-resize',
        t: 'ns-resize', b: 'ns-resize', l: 'ew-resize', r: 'ew-resize',
    };
    return map[handle] || 'pointer';
}
