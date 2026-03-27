/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const HANDLE_SIZE = 8;
const MIN_SIZE = 16;
const MAX_PIXELS = 100_000_000; // 100 megapixels

export class ResizeTool {
    constructor(editor) {
        this.editor = editor;
        this.lockAspect = true;
        this.width = 0;
        this.height = 0;
        this.aspectRatio = 1;
        this._widthInput = null;
        this._heightInput = null;
        this._dragging = null;
        this._dragStart = null;
        this._startSize = null;
    }

    get name() { return 'resize'; }
    get label() { return 'Resize'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
    }

    activate() {
        this.width = this.editor.baseCanvas.width;
        this.height = this.editor.baseCanvas.height;
        this.aspectRatio = this.width / this.height;
    }

    deactivate() {
        this._dragging = null;
    }

    onPointerDown(ix, iy, e) {
        const handle = this._hitHandle(e);
        if (handle) {
            this._dragging = handle;
            this._dragStart = this._canvasCoords(e);
            this._startSize = { w: this.width, h: this.height };
        }
    }

    onPointerMove(ix, iy, e) {
        // Update cursor based on what's under the pointer
        if (!this._dragging) {
            const handle = this._hitHandle(e);
            if (handle) {
                this.editor.setCursor(handleCursor(handle));
            }
        }

        if (!this._dragging) return;

        const pos = this._canvasCoords(e);
        const dx = (pos.x - this._dragStart.x) / this.editor.scale;
        const dy = (pos.y - this._dragStart.y) / this.editor.scale;
        const sw = this._startSize.w;
        const sh = this._startSize.h;
        const handle = this._dragging;

        let newW = sw;
        let newH = sh;

        // Compute raw new size based on which handle is dragged
        if (handle === 'br') {
            newW = sw + dx;
            newH = sh + dy;
        } else if (handle === 'bl') {
            newW = sw - dx;
            newH = sh + dy;
        } else if (handle === 'tr') {
            newW = sw + dx;
            newH = sh - dy;
        } else if (handle === 'tl') {
            newW = sw - dx;
            newH = sh - dy;
        } else if (handle === 'r') {
            newW = sw + dx;
        } else if (handle === 'l') {
            newW = sw - dx;
        } else if (handle === 'b') {
            newH = sh + dy;
        } else if (handle === 't') {
            newH = sh - dy;
        }

        newW = Math.max(MIN_SIZE, Math.round(newW));
        newH = Math.max(MIN_SIZE, Math.round(newH));

        if (this.lockAspect) {
            // Use the axis with the larger relative change to drive the other
            const dw = Math.abs(newW - sw) / sw;
            const dh = Math.abs(newH - sh) / sh;
            if (handle === 'r' || handle === 'l') {
                newH = Math.max(MIN_SIZE, Math.round(newW / this.aspectRatio));
            } else if (handle === 'b' || handle === 't') {
                newW = Math.max(MIN_SIZE, Math.round(newH * this.aspectRatio));
            } else if (dw >= dh) {
                newH = Math.max(MIN_SIZE, Math.round(newW / this.aspectRatio));
            } else {
                newW = Math.max(MIN_SIZE, Math.round(newH * this.aspectRatio));
            }
        }

        this.width = newW;
        this.height = newH;
        this._syncInputs();
        this.editor.requestRender();
    }

    onPointerUp() {
        if (this._dragging) {
            this._dragging = null;
            this._dragStart = null;
            this._startSize = null;
        }
    }

    onKeyDown(e) {
        if (e.key === 'Enter') {
            this._apply();
        }
    }

    renderOverlay(ctx, scale, offsetX, offsetY) {
        // The image is already drawn at the target size by the editor's _render(),
        // so we just draw handles at the edges of the stretched image.
        const rw = this.width * scale;
        const rh = this.height * scale;
        const rx = offsetX;
        const ry = offsetY;

        // Border around the image
        ctx.strokeStyle = '#0090ff';
        ctx.lineWidth = 1;
        ctx.strokeRect(rx, ry, rw, rh);

        // Size label
        if (this.width !== this.editor.baseCanvas.width || this.height !== this.editor.baseCanvas.height) {
            ctx.font = '12px ui-sans-serif, system-ui, sans-serif';
            ctx.fillStyle = '#0090ff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            ctx.fillText(`${this.width} × ${this.height}`, rx + rw / 2, ry + rh + 8);
        }

        // Handles
        const handles = this._getHandlePositions(rx, ry, rw, rh);
        for (const h of handles) {
            ctx.fillStyle = '#fff';
            ctx.strokeStyle = '#0090ff';
            ctx.lineWidth = 1.5;
            ctx.fillRect(h.x - HANDLE_SIZE / 2, h.y - HANDLE_SIZE / 2, HANDLE_SIZE, HANDLE_SIZE);
            ctx.strokeRect(h.x - HANDLE_SIZE / 2, h.y - HANDLE_SIZE / 2, HANDLE_SIZE, HANDLE_SIZE);
        }
    }

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-options-group';

        // Width
        const wGroup = document.createElement('div');
        wGroup.className = 'maho-ie-input';
        const wLabel = document.createElement('label');
        wLabel.textContent = 'Width';
        this._widthInput = document.createElement('input');
        this._widthInput.type = 'number';
        this._widthInput.min = '1';
        this._widthInput.max = '10000';
        this._widthInput.value = this.width;
        this._widthInput.addEventListener('input', () => this._onWidthChange());
        wGroup.append(wLabel, this._widthInput);

        // Lock button
        const lockBtn = document.createElement('button');
        lockBtn.className = 'maho-ie-opt-btn' + (this.lockAspect ? ' active' : '');
        lockBtn.innerHTML = this._lockIcon();
        lockBtn.title = 'Lock aspect ratio';
        lockBtn.addEventListener('click', () => {
            this.lockAspect = !this.lockAspect;
            lockBtn.classList.toggle('active', this.lockAspect);
            if (this.lockAspect) {
                this.aspectRatio = this.width / this.height;
            }
        });

        // Height
        const hGroup = document.createElement('div');
        hGroup.className = 'maho-ie-input';
        const hLabel = document.createElement('label');
        hLabel.textContent = 'Height';
        this._heightInput = document.createElement('input');
        this._heightInput.type = 'number';
        this._heightInput.min = '1';
        this._heightInput.max = '10000';
        this._heightInput.value = this.height;
        this._heightInput.addEventListener('input', () => this._onHeightChange());
        hGroup.append(hLabel, this._heightInput);

        // Px label
        const pxLabel = document.createElement('span');
        pxLabel.style.cssText = 'font-size:11px;color:#71717a';
        pxLabel.textContent = 'px';

        // Apply button
        const applyBtn = document.createElement('button');
        applyBtn.className = 'maho-ie-opt-btn';
        applyBtn.textContent = 'Apply';
        applyBtn.addEventListener('click', () => this._apply());

        el.append(wGroup, lockBtn, hGroup, pxLabel, applyBtn);
        return el;
    }

    _onWidthChange() {
        this.width = Math.max(1, parseInt(this._widthInput.value) || 1);
        if (this.lockAspect) {
            this.height = Math.round(this.width / this.aspectRatio);
            this._heightInput.value = this.height;
        }
        this.editor.requestRender();
    }

    _onHeightChange() {
        this.height = Math.max(1, parseInt(this._heightInput.value) || 1);
        if (this.lockAspect) {
            this.width = Math.round(this.height * this.aspectRatio);
            this._widthInput.value = this.width;
        }
        this.editor.requestRender();
    }

    _syncInputs() {
        if (this._widthInput) this._widthInput.value = this.width;
        if (this._heightInput) this._heightInput.value = this.height;
    }

    _apply() {
        if (this.width === this.editor.baseCanvas.width && this.height === this.editor.baseCanvas.height) {
            return;
        }
        if (this.width * this.height > MAX_PIXELS) {
            alert(`Image dimensions too large (${this.width}×${this.height}). Maximum is ${MAX_PIXELS.toLocaleString()} total pixels.`);
            return;
        }
        this.editor.pushUndo();
        const src = this.editor.baseCanvas;
        const canvas = document.createElement('canvas');
        canvas.width = this.width;
        canvas.height = this.height;
        const ctx = canvas.getContext('2d');
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(src, 0, 0, this.width, this.height);
        this.editor.replaceBase(canvas);
        this.width = canvas.width;
        this.height = canvas.height;
        this._syncInputs();
    }

    _canvasCoords(e) {
        const rect = this.editor.canvas.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }

    _hitHandle(e) {
        const pos = this._canvasCoords(e);
        const scale = this.editor.scale;
        const ox = this.editor.offsetX;
        const oy = this.editor.offsetY;
        const rw = this.width * scale;
        const rh = this.height * scale;
        const threshold = HANDLE_SIZE + 6;

        const positions = {
            tl: { x: ox, y: oy },
            tr: { x: ox + rw, y: oy },
            bl: { x: ox, y: oy + rh },
            br: { x: ox + rw, y: oy + rh },
            t:  { x: ox + rw / 2, y: oy },
            b:  { x: ox + rw / 2, y: oy + rh },
            l:  { x: ox, y: oy + rh / 2 },
            r:  { x: ox + rw, y: oy + rh / 2 },
        };

        for (const [name, hp] of Object.entries(positions)) {
            if (Math.abs(pos.x - hp.x) < threshold && Math.abs(pos.y - hp.y) < threshold) {
                return name;
            }
        }
        return null;
    }

    _getHandlePositions(rx, ry, rw, rh) {
        return [
            { id: 'tl', x: rx, y: ry },
            { id: 'tr', x: rx + rw, y: ry },
            { id: 'bl', x: rx, y: ry + rh },
            { id: 'br', x: rx + rw, y: ry + rh },
            { id: 't',  x: rx + rw / 2, y: ry },
            { id: 'b',  x: rx + rw / 2, y: ry + rh },
            { id: 'l',  x: rx, y: ry + rh / 2 },
            { id: 'r',  x: rx + rw, y: ry + rh / 2 },
        ];
    }

    _lockIcon() {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
    }

    destroy() {}
}

function handleCursor(handle) {
    const map = {
        tl: 'nwse-resize', tr: 'nesw-resize', bl: 'nesw-resize', br: 'nwse-resize',
        t: 'ns-resize', b: 'ns-resize', l: 'ew-resize', r: 'ew-resize',
    };
    return map[handle] || 'pointer';
}
