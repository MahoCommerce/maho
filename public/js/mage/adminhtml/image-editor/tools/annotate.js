/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import { drawAnnotation } from '../export.js';
import { createFontSelect } from '../fonts.js';

const HANDLE_SIZE = 7;

export class AnnotateTool {
    constructor(editor) {
        this.editor = editor;
        this.mode = 'freehand';
        this.strokeColor = '#ff0000';
        this.fillColor = 'transparent';
        this.lineWidth = 3;
        this.fontSize = 24;
        this.fontFamily = 'sans-serif';
        this.opacity = 1;
        this.selected = null;
        this._state = 'idle';
        this._drawing = null;
        this._dragStart = null;
        this._dragOriginal = null;
        this._activeHandle = null;
        this._textOverlay = null;
        this._pushed = false;
        this._nextAnnotationId = 1;
    }

    get name() { return 'annotate'; }
    get label() { return 'Annotate'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="2" x2="22" y2="6"/><path d="M7.5 20.5 2 22l1.5-5.5L17 3l4 4L7.5 20.5z"/></svg>';
    }

    activate() {
        this.selected = null;
        this._state = 'idle';
        this._pushed = false;
    }

    deactivate() {
        this._closeTextOverlay();
        this.selected = null;
        this._state = 'idle';
    }

    onPointerDown(ix, iy) {
        this._pushed = false;

        // Check handle hit on selected annotation
        if (this.selected) {
            const handle = this._hitHandle(ix, iy, this.selected);
            if (handle) {
                this.editor.pushUndo();
                this._state = 'resizing';
                this._activeHandle = handle;
                this._dragStart = { x: ix, y: iy };
                this._dragOriginal = { ...this.selected };
                return;
            }
        }

        // Check annotation hit
        const hit = this._hitTest(ix, iy);
        if (hit) {
            this.editor.pushUndo();
            this.selected = hit;
            this._state = 'moving';
            this._dragStart = { x: ix, y: iy };
            this._dragOriginal = { ...hit };
            if (hit.points) this._dragOriginal.points = hit.points.map(p => ({ ...p }));
            this.editor.requestRender();
            return;
        }

        // Image mode: open file picker instead of drawing
        if (this.mode === 'image') {
            this.selected = null;
            this._openImagePicker(ix, iy);
            return;
        }

        // Start drawing new annotation
        this.selected = null;
        this._closeTextOverlay();
        this._startDrawing(ix, iy);
    }

    onPointerMove(ix, iy, e) {
        // Update cursor on hover
        if (this._state === 'idle') {
            if (this.selected && this._hitHandle(ix, iy, this.selected)) {
                this.editor.setCursor('pointer');
            } else if (this._hitTest(ix, iy)) {
                this.editor.setCursor('move');
            } else {
                this.editor.setCursor('crosshair');
            }
        }

        const constrain = e?.shiftKey ?? false;
        if (this._state === 'drawing' && this._drawing) {
            this._updateDrawing(ix, iy, constrain);
            this.editor.requestRender();
        } else if (this._state === 'moving' && this.selected) {
            this._moveAnnotation(ix, iy);
            this.editor.requestRender();
        } else if (this._state === 'resizing' && this.selected) {
            this._resizeAnnotation(ix, iy, constrain);
            this.editor.requestRender();
        }
    }

    onPointerUp(ix, iy) {
        if (this._state === 'drawing' && this._drawing) {
            this._finishDrawing(ix, iy);
        }
        this._state = 'idle';
        this._dragStart = null;
        this._dragOriginal = null;
        this._activeHandle = null;
    }

    onDoubleClick(ix, iy) {
        const hit = this._hitTest(ix, iy);
        if (hit && hit.type === 'text') {
            this.selected = hit;
            this._editExistingText(hit);
        }
    }

    onKeyDown(e) {
        if ((e.key === 'Delete' || e.key === 'Backspace') && this.selected && !this._textOverlay) {
            this.editor.pushUndo();
            const idx = this.editor.annotations.indexOf(this.selected);
            if (idx >= 0) this.editor.annotations.splice(idx, 1);
            this.selected = null;
            this.editor.requestRender();
        }
    }

    renderOverlay(ctx, scale, offsetX, offsetY) {
        // Draw in-progress annotation
        if (this._drawing) {
            ctx.save();
            ctx.translate(offsetX, offsetY);
            drawAnnotation(ctx, this._drawing, scale);
            ctx.restore();
        }

        // Selection handles
        if (this.selected) {
            this._drawSelectionHandles(ctx, this.selected, scale, offsetX, offsetY);
        }
    }

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-options-group';

        const modes = [
            { mode: 'rect', label: 'Rectangle', icon: this._rectIcon() },
            { mode: 'ellipse', label: 'Ellipse', icon: this._ellipseIcon() },
            { mode: 'arrow', label: 'Arrow', icon: this._arrowIcon() },
            { mode: 'line', label: 'Line', icon: this._lineIcon() },
            { mode: 'freehand', label: 'Freehand', icon: this._freehandIcon() },
            { mode: 'text', label: 'Text', icon: this._textIcon() },
            { mode: 'image', label: 'Image', icon: this._imageIcon() },
        ];

        for (const m of modes) {
            const btn = document.createElement('button');
            btn.className = 'maho-ie-opt-btn' + (this.mode === m.mode ? ' active' : '');
            btn.innerHTML = m.icon;
            btn.title = m.label;
            btn.addEventListener('click', () => {
                this.mode = m.mode;
                this.selected = null;
                this._closeTextOverlay();
                this.editor.setOptions(this.renderOptions());
                this.editor.requestRender();
            });
            el.appendChild(btn);
        }

        // Divider
        el.appendChild(this._divider());

        // Stroke color (not for image mode)
        if (this.mode !== 'image') {
            const strokeGroup = document.createElement('div');
            strokeGroup.className = 'maho-ie-color';
            const strokeLabel = document.createElement('label');
            strokeLabel.textContent = 'Color';
            const strokeInput = document.createElement('input');
            strokeInput.type = 'color';
            strokeInput.value = this.strokeColor;
            strokeInput.addEventListener('input', () => {
                this.strokeColor = strokeInput.value;
                if (this.selected) {
                    if (!this._pushed) { this.editor.pushUndo(); this._pushed = true; }
                    if (this.selected.strokeColor !== undefined) this.selected.strokeColor = this.strokeColor;
                    if (this.selected.color !== undefined) this.selected.color = this.strokeColor;
                    this.editor.requestRender();
                }
                if (this._pendingAnnotation) {
                    this._pendingAnnotation.color = this.strokeColor;
                    this._updateTextOverlayStyle();
                }
            });
            strokeGroup.append(strokeLabel, strokeInput);
            el.appendChild(strokeGroup);
        }

        // Font (text mode only)
        if (this.mode === 'text') {
            const fontSelect = createFontSelect(this.fontFamily, (value) => {
                this.fontFamily = value;
                if (this.selected && this.selected.fontFamily !== undefined) {
                    if (!this._pushed) { this.editor.pushUndo(); this._pushed = true; }
                    this.selected.fontFamily = value;
                    this.editor.requestRender();
                }
                if (this._pendingAnnotation) {
                    this._pendingAnnotation.fontFamily = value;
                    this._updateTextOverlayStyle();
                }
            });
            el.appendChild(fontSelect);
        }

        if (this.mode === 'text') {
            // Font size (text mode)
            const fsGroup = document.createElement('div');
            fsGroup.className = 'maho-ie-slider';
            const fsLabel = document.createElement('label');
            fsLabel.textContent = 'Size';
            const fsInput = document.createElement('input');
            fsInput.type = 'range';
            fsInput.min = '12';
            fsInput.max = '120';
            fsInput.value = this.fontSize;
            const fsVal = document.createElement('span');
            fsVal.className = 'maho-ie-slider-value';
            fsVal.textContent = this.fontSize;
            fsInput.addEventListener('input', () => {
                this.fontSize = parseInt(fsInput.value);
                fsVal.textContent = this.fontSize;
                if (this.selected && this.selected.fontSize !== undefined) {
                    if (!this._pushed) { this.editor.pushUndo(); this._pushed = true; }
                    this.selected.fontSize = this.fontSize;
                    this.editor.requestRender();
                }
                if (this._pendingAnnotation) {
                    this._pendingAnnotation.fontSize = this.fontSize;
                    this._updateTextOverlayStyle();
                }
            });
            fsGroup.append(fsLabel, fsInput, fsVal);
            el.appendChild(fsGroup);
        } else if (this.mode !== 'image') {
            // Line width (shape modes)
            const lwGroup = document.createElement('div');
            lwGroup.className = 'maho-ie-slider';
            const lwLabel = document.createElement('label');
            lwLabel.textContent = 'Size';
            const lwInput = document.createElement('input');
            lwInput.type = 'range';
            lwInput.min = '1';
            lwInput.max = '20';
            lwInput.value = this.lineWidth;
            const lwVal = document.createElement('span');
            lwVal.className = 'maho-ie-slider-value';
            lwVal.textContent = this.lineWidth;
            lwInput.addEventListener('input', () => {
                this.lineWidth = parseInt(lwInput.value);
                lwVal.textContent = this.lineWidth;
                if (this.selected && this.selected.lineWidth !== undefined) {
                    if (!this._pushed) { this.editor.pushUndo(); this._pushed = true; }
                    this.selected.lineWidth = this.lineWidth;
                    this.editor.requestRender();
                }
            });
            lwGroup.append(lwLabel, lwInput, lwVal);
            el.appendChild(lwGroup);
        }

        // Opacity (all modes)
        const opGroup = document.createElement('div');
        opGroup.className = 'maho-ie-slider';
        const opLabel = document.createElement('label');
        opLabel.textContent = 'Opacity';
        const opVal = document.createElement('span');
        opVal.className = 'maho-ie-slider-value';
        const currentOpacity = this.selected?.opacity ?? this.opacity;
        opVal.textContent = Math.round(currentOpacity * 100) + '%';
        const opInput = document.createElement('input');
        opInput.type = 'range';
        opInput.min = '5';
        opInput.max = '100';
        opInput.value = Math.round(currentOpacity * 100);
        opInput.addEventListener('input', () => {
            const val = parseInt(opInput.value) / 100;
            this.opacity = val;
            opVal.textContent = opInput.value + '%';
            if (this.selected) {
                if (!this._pushed) { this.editor.pushUndo(); this._pushed = true; }
                this.selected.opacity = val;
                this.editor.requestRender();
            }
        });
        opGroup.append(opLabel, opInput, opVal);
        el.appendChild(opGroup);

        // Delete selected
        const delBtn = document.createElement('button');
        delBtn.className = 'maho-ie-opt-btn maho-ie-opt-btn-danger';
        delBtn.textContent = 'Delete';
        delBtn.title = 'Delete selected annotation';
        delBtn.addEventListener('click', () => {
            if (this.selected) {
                this.editor.pushUndo();
                const idx = this.editor.annotations.indexOf(this.selected);
                if (idx >= 0) this.editor.annotations.splice(idx, 1);
                this.selected = null;
                this.editor.requestRender();
            }
        });
        el.appendChild(delBtn);

        return el;
    }

    _startDrawing(ix, iy) {
        this._state = 'drawing';
        const base = { lineWidth: this.lineWidth, opacity: this.opacity, id: this._nextAnnotationId++ };

        switch (this.mode) {
            case 'rect':
                this._drawing = { ...base, type: 'rect', x: ix, y: iy, w: 0, h: 0, strokeColor: this.strokeColor, fillColor: this.fillColor };
                break;
            case 'ellipse':
                this._drawing = { ...base, type: 'ellipse', cx: ix, cy: iy, rx: 0, ry: 0, strokeColor: this.strokeColor, fillColor: this.fillColor };
                break;
            case 'arrow':
                this._drawing = { ...base, type: 'arrow', x1: ix, y1: iy, x2: ix, y2: iy, color: this.strokeColor };
                break;
            case 'line':
                this._drawing = { ...base, type: 'line', x1: ix, y1: iy, x2: ix, y2: iy, color: this.strokeColor };
                break;
            case 'freehand':
                this._drawing = { ...base, type: 'freehand', points: [{ x: ix, y: iy }], color: this.strokeColor };
                break;
            case 'text':
                this._drawing = { ...base, type: 'text', x: ix, y: iy, text: '', color: this.strokeColor, fontSize: this.fontSize, fontFamily: this.fontFamily, bold: false, italic: false };
                break;
        }
    }

    _updateDrawing(ix, iy, constrain = false) {
        const d = this._drawing;
        if (!d) return;

        switch (d.type) {
            case 'rect':
                d.w = ix - d.x;
                d.h = iy - d.y;
                if (constrain) {
                    const size = Math.max(Math.abs(d.w), Math.abs(d.h));
                    d.w = size * Math.sign(d.w || 1);
                    d.h = size * Math.sign(d.h || 1);
                }
                break;
            case 'ellipse':
                d.rx = ix - d.cx;
                d.ry = iy - d.cy;
                if (constrain) {
                    const r = Math.max(Math.abs(d.rx), Math.abs(d.ry));
                    d.rx = r * Math.sign(d.rx || 1);
                    d.ry = r * Math.sign(d.ry || 1);
                }
                break;
            case 'arrow':
            case 'line':
                d.x2 = ix;
                d.y2 = iy;
                break;
            case 'freehand':
                d.points.push({ x: ix, y: iy });
                break;
        }
    }

    _finishDrawing(ix, iy) {
        const d = this._drawing;
        this._drawing = null;
        if (!d) return;

        // Normalize negative dimensions for rect
        if (d.type === 'rect') {
            if (d.w < 0) { d.x += d.w; d.w = -d.w; }
            if (d.h < 0) { d.y += d.h; d.h = -d.h; }
            if (d.w < 3 && d.h < 3) return;
        }

        if (d.type === 'ellipse') {
            if (Math.abs(d.rx) < 3 && Math.abs(d.ry) < 3) return;
        }

        if ((d.type === 'line' || d.type === 'arrow')) {
            const dist = Math.hypot(d.x2 - d.x1, d.y2 - d.y1);
            if (dist < 3) return;
        }

        if (d.type === 'text') {
            this._openTextOverlay(d);
            return;
        }

        if (d.type === 'freehand' && d.points.length < 2) return;

        this.editor.pushUndo();
        this.editor.annotations.push(d);
        this.selected = d;
        this.editor.requestRender();
    }

    _editExistingText(annotation) {
        this._openTextOverlay(annotation, true);
    }

    _openTextOverlay(annotation, editing = false) {
        this._closeTextOverlay();
        this._pendingAnnotation = annotation;
        this._editingExisting = editing;
        const wrap = this.editor.canvasWrap;
        const canvas = this.editor.canvas;
        const scale = this.editor.scale;
        const offsetX = this.editor.offsetX;
        const offsetY = this.editor.offsetY;

        const canvasRect = canvas.getBoundingClientRect();
        const tx = annotation.x * scale + offsetX + canvasRect.left - wrap.getBoundingClientRect().left;
        const ty = annotation.y * scale + offsetY + canvasRect.top - wrap.getBoundingClientRect().top;

        const overlay = document.createElement('div');
        overlay.className = 'maho-ie-text-overlay';
        overlay.style.left = tx + 'px';
        overlay.style.top = ty + 'px';

        const input = document.createElement('div');
        input.className = 'maho-ie-text-input';
        input.contentEditable = 'true';
        input.style.fontSize = (annotation.fontSize * scale) + 'px';
        input.style.color = annotation.color;
        input.style.fontFamily = annotation.fontFamily || 'sans-serif';

        if (editing && annotation.text) {
            input.textContent = annotation.text;
        }

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this._commitText(annotation, input.innerText);
            } else if (e.key === 'Escape') {
                this._closeTextOverlay();
                this.editor.requestRender();
            }
            e.stopPropagation();
        });

        overlay.appendChild(input);
        wrap.style.position = 'relative';
        wrap.appendChild(overlay);
        this._textOverlay = overlay;
        this.editor.requestRender();
        input.focus();

        if (editing) {
            const range = document.createRange();
            range.selectNodeContents(input);
            range.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
    }

    _updateTextOverlayStyle() {
        if (!this._textOverlay || !this._pendingAnnotation) return;
        const el = this._textOverlay.querySelector('.maho-ie-text-input');
        if (!el) return;
        const a = this._pendingAnnotation;
        const scale = this.editor.scale;
        el.style.color = a.color;
        el.style.fontFamily = a.fontFamily || 'sans-serif';
        el.style.fontSize = (a.fontSize * scale) + 'px';
    }

    _commitText(annotation, text) {
        const editing = this._editingExisting;
        this._closeTextOverlay();
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        if (!text.trim()) return;

        this.editor.pushUndo();
        annotation.text = text;
        if (!editing) {
            this.editor.annotations.push(annotation);
        }
        this.selected = annotation;
        this.editor.requestRender();
    }

    _closeTextOverlay() {
        if (this._textOverlay) {
            this._textOverlay.remove();
            this._textOverlay = null;
        }
        this._pendingAnnotation = null;
        this._editingExisting = false;
    }

    _hitTest(ix, iy) {
        const annotations = this.editor.annotations;
        // Iterate in reverse so top-most is hit first
        for (let i = annotations.length - 1; i >= 0; i--) {
            const a = annotations[i];
            if (this._pointInAnnotation(ix, iy, a)) return a;
        }
        return null;
    }

    _pointInAnnotation(px, py, a) {
        const threshold = Math.max(5, (a.lineWidth || 2) * 2);

        switch (a.type) {
            case 'rect':
                return px >= a.x - threshold && px <= a.x + a.w + threshold &&
                       py >= a.y - threshold && py <= a.y + a.h + threshold;
            case 'ellipse': {
                const dx = (px - a.cx) / (Math.abs(a.rx) + threshold);
                const dy = (py - a.cy) / (Math.abs(a.ry) + threshold);
                return dx * dx + dy * dy <= 1;
            }
            case 'line':
            case 'arrow':
                return distToSegment(px, py, a.x1, a.y1, a.x2, a.y2) < threshold;
            case 'freehand':
                for (let i = 1; i < a.points.length; i++) {
                    if (distToSegment(px, py, a.points[i - 1].x, a.points[i - 1].y, a.points[i].x, a.points[i].y) < threshold) {
                        return true;
                    }
                }
                return false;
            case 'text': {
                const h = (a.fontSize || 24) * 1.2 * (a.text || '').split('\n').length;
                const w = (a.text || '').length * (a.fontSize || 24) * 0.6;
                return px >= a.x && px <= a.x + w && py >= a.y && py <= a.y + h;
            }
            case 'image':
                return px >= a.x - threshold && px <= a.x + a.w + threshold &&
                       py >= a.y - threshold && py <= a.y + a.h + threshold;
        }
        return false;
    }

    _moveAnnotation(ix, iy) {
        const dx = ix - this._dragStart.x;
        const dy = iy - this._dragStart.y;
        const orig = this._dragOriginal;
        const a = this.selected;

        switch (a.type) {
            case 'rect':
                a.x = orig.x + dx;
                a.y = orig.y + dy;
                break;
            case 'ellipse':
                a.cx = orig.cx + dx;
                a.cy = orig.cy + dy;
                break;
            case 'line':
            case 'arrow':
                a.x1 = orig.x1 + dx;
                a.y1 = orig.y1 + dy;
                a.x2 = orig.x2 + dx;
                a.y2 = orig.y2 + dy;
                break;
            case 'freehand':
                for (let i = 0; i < a.points.length; i++) {
                    a.points[i].x = orig.points[i].x + dx;
                    a.points[i].y = orig.points[i].y + dy;
                }
                break;
            case 'text':
            case 'image':
                a.x = orig.x + dx;
                a.y = orig.y + dy;
                break;
        }
    }

    _hitHandle(ix, iy, a) {
        const scale = this.editor.scale;
        const threshold = (HANDLE_SIZE + 4) / scale;
        const handles = this._getHandles(a);

        for (const h of handles) {
            if (Math.abs(ix - h.x) < threshold && Math.abs(iy - h.y) < threshold) {
                return h.id;
            }
        }
        return null;
    }

    _getHandles(a) {
        switch (a.type) {
            case 'rect':
            case 'image':
                return [
                    { id: 'tl', x: a.x, y: a.y },
                    { id: 'tr', x: a.x + a.w, y: a.y },
                    { id: 'bl', x: a.x, y: a.y + a.h },
                    { id: 'br', x: a.x + a.w, y: a.y + a.h },
                ];
            case 'ellipse':
                return [
                    { id: 'r', x: a.cx + a.rx, y: a.cy },
                    { id: 'b', x: a.cx, y: a.cy + a.ry },
                    { id: 'l', x: a.cx - a.rx, y: a.cy },
                    { id: 't', x: a.cx, y: a.cy - a.ry },
                ];
            case 'line':
            case 'arrow':
                return [
                    { id: 'p1', x: a.x1, y: a.y1 },
                    { id: 'p2', x: a.x2, y: a.y2 },
                ];
            default:
                return [];
        }
    }

    _resizeAnnotation(ix, iy, constrain = false) {
        const a = this.selected;
        const h = this._activeHandle;

        switch (a.type) {
            case 'rect':
            case 'image': {
                if (h === 'tl') { a.w += a.x - ix; a.h += a.y - iy; a.x = ix; a.y = iy; }
                if (h === 'tr') { a.w = ix - a.x; a.h += a.y - iy; a.y = iy; }
                if (h === 'bl') { a.w += a.x - ix; a.x = ix; a.h = iy - a.y; }
                if (h === 'br') { a.w = ix - a.x; a.h = iy - a.y; }
                if (constrain) {
                    const aspect = this._dragOriginal.w / this._dragOriginal.h;
                    const size = Math.max(Math.abs(a.w), Math.abs(a.h));
                    if (Math.abs(a.w) / aspect > Math.abs(a.h)) {
                        a.h = Math.sign(a.h || 1) * Math.abs(a.w) / aspect;
                    } else {
                        a.w = Math.sign(a.w || 1) * Math.abs(a.h) * aspect;
                    }
                }
                if (a.w < 0) { a.x += a.w; a.w = -a.w; }
                if (a.h < 0) { a.y += a.h; a.h = -a.h; }
                break;
            }
            case 'ellipse':
                if (h === 'r') a.rx = ix - a.cx;
                if (h === 'l') a.rx = a.cx - ix;
                if (h === 'b') a.ry = iy - a.cy;
                if (h === 't') a.ry = a.cy - iy;
                if (constrain) {
                    const r = Math.max(Math.abs(a.rx), Math.abs(a.ry));
                    a.rx = r * Math.sign(a.rx || 1);
                    a.ry = r * Math.sign(a.ry || 1);
                }
                break;
            case 'line':
            case 'arrow':
                if (h === 'p1') { a.x1 = ix; a.y1 = iy; }
                if (h === 'p2') { a.x2 = ix; a.y2 = iy; }
                break;
        }
    }

    _drawSelectionHandles(ctx, a, scale, offsetX, offsetY) {
        const handles = this._getHandles(a);
        ctx.fillStyle = '#fff';
        ctx.strokeStyle = '#0090ff';
        ctx.lineWidth = 1.5;

        for (const h of handles) {
            const hx = h.x * scale + offsetX;
            const hy = h.y * scale + offsetY;
            ctx.fillRect(hx - HANDLE_SIZE / 2, hy - HANDLE_SIZE / 2, HANDLE_SIZE, HANDLE_SIZE);
            ctx.strokeRect(hx - HANDLE_SIZE / 2, hy - HANDLE_SIZE / 2, HANDLE_SIZE, HANDLE_SIZE);
        }
    }

    _openImagePicker(ix, iy) {
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) return;
            const img = new Image();
            img.onload = () => {
                URL.revokeObjectURL(img.src);
                if (this.editor.activeTool !== this) return;
                const imgW = this.editor.baseCanvas.width;
                const defaultW = imgW * 0.2;
                const defaultH = defaultW * (img.height / img.width);
                this.editor.pushUndo();
                const annotation = {
                    type: 'image',
                    x: ix - defaultW / 2,
                    y: iy - defaultH / 2,
                    w: defaultW,
                    h: defaultH,
                    image: img,
                    opacity: this.opacity,
                    id: this._nextAnnotationId++,
                };
                this.editor.annotations.push(annotation);
                this.selected = annotation;
                this.editor.setOptions(this.renderOptions());
                this.editor.requestRender();
            };
            img.onerror = () => URL.revokeObjectURL(img.src);
            img.src = URL.createObjectURL(file);
        });
        fileInput.click();
    }

    _divider() {
        const d = document.createElement('div');
        d.className = 'maho-ie-options-divider';
        return d;
    }

    _rectIcon() { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>'; }
    _ellipseIcon() { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>'; }
    _arrowIcon() { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="19" x2="19" y2="5"/><polyline points="12 5 19 5 19 12"/></svg>'; }
    _lineIcon() { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="19" x2="19" y2="5"/></svg>'; }
    _freehandIcon() { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>'; }
    _textIcon() { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 7 4 4 20 4 20 7"/><line x1="12" y1="4" x2="12" y2="20"/><line x1="8" y1="20" x2="16" y2="20"/></svg>'; }
    _imageIcon() { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>'; }

    destroy() {
        this._closeTextOverlay();
    }
}

function distToSegment(px, py, x1, y1, x2, y2) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const lenSq = dx * dx + dy * dy;
    if (lenSq === 0) return Math.hypot(px - x1, py - y1);
    let t = ((px - x1) * dx + (py - y1) * dy) / lenSq;
    t = Math.max(0, Math.min(1, t));
    return Math.hypot(px - (x1 + t * dx), py - (y1 + t * dy));
}
