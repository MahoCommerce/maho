/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import { CropTool } from './tools/crop.js';
import { ResizeTool } from './tools/resize.js';
import { TransformTool } from './tools/transform.js';
import { AdjustTool } from './tools/adjust.js';
import { AnnotateTool } from './tools/annotate.js';
import { RedactTool } from './tools/redact.js';
import { WatermarkTool } from './tools/watermark.js';
import { FrameTool } from './tools/frame.js';
import { renderToCanvas, exportBlob, buildFilterString, drawAnnotation } from './export.js';

const MAX_UNDO = 30;
const MIN_ZOOM = 0.1;
const MAX_ZOOM = 20;
const ZOOM_STEP = 1.15;

export class MahoImageEditor {
    constructor(container, options) {
        this.container = container;
        this.options = options;
        this.baseCanvas = null;
        this.canvas = null;
        this.ctx = null;
        this.canvasWrap = null;

        // View state
        this.zoom = 1;
        this._baseScale = 1;
        this.scale = 1;
        this.panX = 0;
        this.panY = 0;
        this.offsetX = 0;
        this.offsetY = 0;

        // Panning state
        this._panning = false;
        this._panStart = null;
        this._panOrigin = null;
        this._spaceHeld = false;

        this.adjustments = {
            brightness: 100,
            contrast: 100,
            hue: 0,
            saturation: 100,
            warmth: 0,
        };
        this.annotations = [];
        this.redactions = [];
        this.watermark = null;
        this.frame = null;

        this.activeTool = null;
        this.tools = [];
        this._undoStack = [];
        this._redoStack = [];
        this._renderPending = false;
        this._undoBtn = null;
        this._redoBtn = null;
        this._zoomDisplay = null;
        this._optionsPanel = null;
        this._resizeObserver = null;
    }

    async open() {
        this._buildUI();
        this._loadStylesheet();
        await this._loadImage(this.options.source);
        this._initTools();
        this._bindEvents();
        this._fitCanvas();
        this.requestRender();
        this.container.focus();
    }

    destroy() {
        for (const tool of this.tools) tool.destroy();
        if (this._resizeObserver) this._resizeObserver.disconnect();
        this.container.innerHTML = '';
    }

    pushUndo() {
        this._undoStack.push(this._snapshot());
        if (this._undoStack.length > MAX_UNDO) this._undoStack.shift();
        this._redoStack = [];
        this._updateUndoButtons();
    }

    undo() {
        if (!this._undoStack.length) return;
        this._redoStack.push(this._snapshot());
        this._restore(this._undoStack.pop());
        this._updateUndoButtons();
        this.requestRender();
        if (this.activeTool) {
            this.setOptions(this.activeTool.renderOptions());
        }
    }

    redo() {
        if (!this._redoStack.length) return;
        this._undoStack.push(this._snapshot());
        this._restore(this._redoStack.pop());
        this._updateUndoButtons();
        this.requestRender();
        if (this.activeTool) {
            this.setOptions(this.activeTool.renderOptions());
        }
    }

    replaceBase(canvas) {
        this.baseCanvas = canvas;
        this.zoom = 1;
        this.panX = 0;
        this.panY = 0;
        this._fitCanvas();
        this.requestRender();
    }

    requestRender() {
        if (this._renderPending) return;
        this._renderPending = true;
        requestAnimationFrame(() => {
            this._renderPending = false;
            this._render();
        });
    }

    setOptions(el) {
        if (this._optionsPanel) {
            this._optionsPanel.innerHTML = '';
            if (el) this._optionsPanel.appendChild(el);
        }
    }

    setCursor(cursor) {
        this.canvas.style.cursor = cursor;
    }

    _confirmClose() {
        if (this._undoStack.length === 0) {
            this.options.onClose?.();
            return;
        }
        if (confirm('You have unsaved changes. Are you sure you want to close?')) {
            this.options.onClose?.();
        }
    }

    zoomIn() {
        this._setZoomCentered(this.zoom * ZOOM_STEP);
    }

    zoomOut() {
        this._setZoomCentered(this.zoom / ZOOM_STEP);
    }

    zoomToFit() {
        this.zoom = 1;
        this.panX = 0;
        this.panY = 0;
        this._updateScaleAndOffsets();
        this._updateZoomDisplay();
        this.requestRender();
    }

    zoomToActual() {
        this._setZoomCentered(1 / this._baseScale);
    }

    _setZoomCentered(newZoom) {
        const cx = this.canvas.width / 2;
        const cy = this.canvas.height / 2;
        this._zoomAtPoint(newZoom, cx, cy);
    }

    _zoomAtPoint(newZoom, canvasX, canvasY) {
        newZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, newZoom));
        if (newZoom === this.zoom) return;

        const oldScale = this.scale;
        const oldOffsetX = this.offsetX;
        const oldOffsetY = this.offsetY;

        // Image coords under cursor before zoom
        const ix = (canvasX - oldOffsetX) / oldScale;
        const iy = (canvasY - oldOffsetY) / oldScale;

        this.zoom = newZoom;
        const newScale = this._baseScale * this.zoom;
        this.scale = newScale;

        // Compute new center offset (without pan)
        const imgW = this.baseCanvas.width;
        const imgH = this.baseCanvas.height;
        const newCenterX = (this.canvas.width - imgW * newScale) / 2;
        const newCenterY = (this.canvas.height - imgH * newScale) / 2;

        // Adjust pan so cursor stays over the same image point
        this.panX = canvasX - ix * newScale - newCenterX;
        this.panY = canvasY - iy * newScale - newCenterY;

        this._updateOffsets();
        this._updateZoomDisplay();
        this.requestRender();
    }

    _updateScaleAndOffsets() {
        if (!this.baseCanvas) return;
        this.scale = this._baseScale * this.zoom;
        this._updateOffsets();
    }

    _updateOffsets() {
        const imgW = this.baseCanvas.width;
        const imgH = this.baseCanvas.height;
        this.offsetX = (this.canvas.width - imgW * this.scale) / 2 + this.panX;
        this.offsetY = (this.canvas.height - imgH * this.scale) / 2 + this.panY;
    }

    _updateZoomDisplay() {
        if (this._zoomDisplay) {
            const pct = Math.round(this.zoom * this._baseScale * 100 / this._baseScale);
            this._zoomDisplay.textContent = Math.round(this.zoom * 100) + '%';
        }
    }

    _buildUI() {
        this.container.innerHTML = '';
        this.container.className = 'maho-ie';

        // Header
        const header = document.createElement('div');
        header.className = 'maho-ie-header';

        const closeBtn = document.createElement('button');
        closeBtn.className = 'maho-ie-btn-close';
        closeBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        closeBtn.addEventListener('click', () => this._confirmClose());

        const title = document.createElement('div');
        title.className = 'maho-ie-title';
        title.textContent = this._getFilename();

        const actions = document.createElement('div');
        actions.className = 'maho-ie-header-actions';

        this._undoBtn = document.createElement('button');
        this._undoBtn.className = 'maho-ie-btn maho-ie-btn-ghost';
        this._undoBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9"/><polyline points="3 3 3 9 9 9"/></svg> Undo';
        this._undoBtn.disabled = true;
        this._undoBtn.addEventListener('click', () => this.undo());

        this._redoBtn = document.createElement('button');
        this._redoBtn.className = 'maho-ie-btn maho-ie-btn-ghost';
        this._redoBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9"/><polyline points="21 3 21 9 15 9"/></svg> Redo';
        this._redoBtn.disabled = true;
        this._redoBtn.addEventListener('click', () => this.redo());

        // Zoom controls
        const zoomGroup = document.createElement('div');
        zoomGroup.className = 'maho-ie-zoom';

        const zoomOutBtn = document.createElement('button');
        zoomOutBtn.className = 'maho-ie-zoom-btn';
        zoomOutBtn.textContent = '\u2212'; // minus sign
        zoomOutBtn.title = 'Zoom out';
        zoomOutBtn.addEventListener('click', () => this.zoomOut());

        this._zoomDisplay = document.createElement('span');
        this._zoomDisplay.className = 'maho-ie-zoom-level';
        this._zoomDisplay.textContent = '100%';
        this._zoomDisplay.title = 'Click to fit';
        this._zoomDisplay.style.cursor = 'pointer';
        this._zoomDisplay.addEventListener('click', () => this.zoomToFit());

        const zoomInBtn = document.createElement('button');
        zoomInBtn.className = 'maho-ie-zoom-btn';
        zoomInBtn.textContent = '+';
        zoomInBtn.title = 'Zoom in';
        zoomInBtn.addEventListener('click', () => this.zoomIn());

        const fitBtn = document.createElement('button');
        fitBtn.className = 'maho-ie-zoom-btn';
        fitBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>';
        fitBtn.title = 'Fit to view';
        fitBtn.addEventListener('click', () => this.zoomToFit());

        zoomGroup.append(zoomOutBtn, this._zoomDisplay, zoomInBtn, fitBtn);

        this._saveBtn = document.createElement('button');
        this._saveBtn.className = 'maho-ie-btn maho-ie-btn-primary';
        this._saveBtn.textContent = 'Save';
        this._saveBtn.addEventListener('click', () => this._save());

        actions.append(this._undoBtn, this._redoBtn, zoomGroup, this._saveBtn);
        header.append(closeBtn, title, actions);

        // Body
        const body = document.createElement('div');
        body.className = 'maho-ie-body';

        // Sidebar
        this._sidebar = document.createElement('div');
        this._sidebar.className = 'maho-ie-sidebar';

        // Main
        const main = document.createElement('div');
        main.className = 'maho-ie-main';

        this.canvasWrap = document.createElement('div');
        this.canvasWrap.className = 'maho-ie-canvas-wrap';

        this.canvas = document.createElement('canvas');
        this.canvasWrap.appendChild(this.canvas);

        this._optionsPanel = document.createElement('div');
        this._optionsPanel.className = 'maho-ie-options';

        main.append(this.canvasWrap, this._optionsPanel);
        body.append(this._sidebar, main);
        this.container.append(header, body);

        this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });
    }

    _loadStylesheet() {
        const baseUrl = import.meta.url;
        const cssUrl = new URL('styles.css', baseUrl).href;
        if (!document.querySelector(`link[href="${cssUrl}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = cssUrl;
            document.head.appendChild(link);
        }
    }

    async _loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                this.baseCanvas = document.createElement('canvas');
                this.baseCanvas.width = img.naturalWidth;
                this.baseCanvas.height = img.naturalHeight;
                const ctx = this.baseCanvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                resolve();
            };
            img.onerror = () => reject(new Error('Failed to load image'));
            img.src = src;
        });
    }

    _initTools() {
        this.tools = [
            new CropTool(this),
            new ResizeTool(this),
            new TransformTool(this),
            new AdjustTool(this),
            new AnnotateTool(this),
            new RedactTool(this),
            new WatermarkTool(this),
            new FrameTool(this),
        ];

        this._sidebar.innerHTML = '';
        for (const tool of this.tools) {
            const btn = document.createElement('button');
            btn.className = 'maho-ie-tool-btn';
            btn.innerHTML = tool.icon + '<span>' + tool.label + '</span>';
            btn.addEventListener('click', () => this._setActiveTool(tool));
            tool._sidebarBtn = btn;
            this._sidebar.appendChild(btn);
        }
    }

    _setActiveTool(tool) {
        const wasActive = this.activeTool === tool;

        if (this.activeTool) {
            this.activeTool.deactivate();
            this.activeTool._sidebarBtn?.classList.remove('active');
        }

        if (wasActive) {
            this.activeTool = null;
            this.setOptions(null);
            this.requestRender();
            return;
        }

        this.activeTool = tool;
        tool._sidebarBtn?.classList.add('active');
        tool.activate();
        this.setOptions(tool.renderOptions());
        this.requestRender();
    }

    _bindEvents() {
        // Canvas pointer events
        this.canvas.addEventListener('pointerdown', (e) => this._onPointerDown(e));
        this.canvas.addEventListener('pointermove', (e) => this._onPointerMove(e));
        this.canvas.addEventListener('pointerup', (e) => this._onPointerUp(e));
        this.canvas.addEventListener('pointerleave', (e) => this._onPointerUp(e));
        this.canvas.addEventListener('dblclick', (e) => this._onDblClick(e));

        // Prevent browser zoom from trackpad pinch
        this.canvasWrap.addEventListener('wheel', (e) => {
            if (e.ctrlKey) e.preventDefault();
        }, { passive: false });

        // Keyboard
        this.container.tabIndex = 0;
        this.container.addEventListener('keydown', (e) => {
            // Undo/Redo
            if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
                e.preventDefault();
                if (e.shiftKey) this.redo(); else this.undo();
                return;
            }

            // Zoom shortcuts
            if ((e.ctrlKey || e.metaKey) && (e.key === '=' || e.key === '+')) {
                e.preventDefault();
                this.zoomIn();
                return;
            }
            if ((e.ctrlKey || e.metaKey) && e.key === '-') {
                e.preventDefault();
                this.zoomOut();
                return;
            }
            if ((e.ctrlKey || e.metaKey) && e.key === '0') {
                e.preventDefault();
                this.zoomToFit();
                return;
            }
            if ((e.ctrlKey || e.metaKey) && e.key === '1') {
                e.preventDefault();
                this.zoomToActual();
                return;
            }

            // Escape — close editor with confirmation
            if (e.key === 'Escape') {
                e.preventDefault();
                this._confirmClose();
                return;
            }

            // Space for pan mode
            if (e.key === ' ' && !e.repeat) {
                e.preventDefault();
                this._spaceHeld = true;
                this.canvasWrap.classList.add('panning');
                return;
            }

            if (this.activeTool) this.activeTool.onKeyDown(e);
        });

        this.container.addEventListener('keyup', (e) => {
            if (e.key === ' ') {
                this._spaceHeld = false;
                if (!this._panning) {
                    this.canvasWrap.classList.remove('panning');
                }
            }
        });

        // Resize observer
        this._resizeObserver = new ResizeObserver(() => {
            this._fitCanvas();
            this.requestRender();
        });
        this._resizeObserver.observe(this.canvasWrap);
    }

    _onPointerDown(e) {
        // Middle mouse button or space+click starts panning
        if (e.button === 1 || this._spaceHeld) {
            e.preventDefault();
            this._panning = true;
            this._panStart = { x: e.clientX, y: e.clientY };
            this._panOrigin = { x: this.panX, y: this.panY };
            this.canvasWrap.classList.add('panning-active');
            this.canvas.setPointerCapture(e.pointerId);
            return;
        }

        if (!this.activeTool) return;
        const { ix, iy } = this._toImageCoords(e);
        this.activeTool.onPointerDown(ix, iy, e);
    }

    _onDblClick(e) {
        if (!this.activeTool?.onDoubleClick) return;
        const { ix, iy } = this._toImageCoords(e);
        this.activeTool.onDoubleClick(ix, iy, e);
    }

    _onPointerMove(e) {
        if (this._panning) {
            this.panX = this._panOrigin.x + (e.clientX - this._panStart.x);
            this.panY = this._panOrigin.y + (e.clientY - this._panStart.y);
            this._updateOffsets();
            this.requestRender();
            return;
        }

        // Reset cursor — tools override via setCursor() in their onPointerMove
        if (!this._spaceHeld) {
            this.canvas.style.cursor = '';
        }

        if (!this.activeTool) return;
        const { ix, iy } = this._toImageCoords(e);
        this.activeTool.onPointerMove(ix, iy, e);
    }

    _onPointerUp(e) {
        if (this._panning) {
            this._panning = false;
            this._panStart = null;
            this._panOrigin = null;
            this.canvasWrap.classList.remove('panning-active');
            if (!this._spaceHeld) {
                this.canvasWrap.classList.remove('panning');
            }
            return;
        }

        if (!this.activeTool) return;
        const { ix, iy } = this._toImageCoords(e);
        this.activeTool.onPointerUp(ix, iy, e);
    }

    _toImageCoords(e) {
        const rect = this.canvas.getBoundingClientRect();
        const cx = e.clientX - rect.left;
        const cy = e.clientY - rect.top;
        return {
            ix: (cx - this.offsetX) / this.scale,
            iy: (cy - this.offsetY) / this.scale,
        };
    }

    _fitCanvas() {
        if (!this.baseCanvas || !this.canvasWrap) return;

        const wrapRect = this.canvasWrap.getBoundingClientRect();
        const cw = Math.round(wrapRect.width);
        const ch = Math.round(wrapRect.height);
        if (cw === 0 || ch === 0) return;

        this.canvas.width = cw;
        this.canvas.height = ch;

        const padding = 32;
        const imgW = this.baseCanvas.width;
        const imgH = this.baseCanvas.height;
        this._baseScale = Math.min((cw - padding) / imgW, (ch - padding) / imgH, 1);

        this._updateScaleAndOffsets();
        this._updateZoomDisplay();
    }

    _render() {
        const ctx = this.ctx;
        const w = this.canvas.width;
        const h = this.canvas.height;

        ctx.clearRect(0, 0, w, h);
        if (!this.baseCanvas) return;

        ctx.save();

        // Frame background (solid/polaroid drawn before image; outside stroke too)
        if (this.frame && (this.frame.type === 'solid' || this.frame.type === 'polaroid'
            || (this.frame.type === 'stroke' && this.frame.position === 'outside'))) {
            this._drawFramePreview(ctx);
        }

        // When resize tool is active, stretch the image to match the target size
        let drawW = this.baseCanvas.width * this.scale;
        let drawH = this.baseCanvas.height * this.scale;
        if (this.activeTool?.name === 'resize') {
            drawW = this.activeTool.width * this.scale;
            drawH = this.activeTool.height * this.scale;
        }

        // Base image with adjustments
        const filter = buildFilterString(this.adjustments);
        ctx.filter = filter;
        ctx.drawImage(this.baseCanvas, this.offsetX, this.offsetY, drawW, drawH);
        ctx.filter = 'none';

        // Redactions
        for (const r of this.redactions) {
            const rx = r.x * this.scale + this.offsetX;
            const ry = r.y * this.scale + this.offsetY;
            const rw = r.w * this.scale;
            const rh = r.h * this.scale;

            if (r.style === 'pixelate') {
                this._drawPixelatedPreview(ctx, rx, ry, rw, rh);
            } else {
                ctx.fillStyle = '#000';
                ctx.fillRect(rx, ry, rw, rh);
            }
        }

        // Annotations
        ctx.save();
        ctx.translate(this.offsetX, this.offsetY);
        const editingAnnotation = this.activeTool?._editingExisting ? this.activeTool._pendingAnnotation : null;
        for (const a of this.annotations) {
            if (a === editingAnnotation) continue;
            drawAnnotation(ctx, a, this.scale);
        }
        ctx.restore();

        // Watermark
        if (this.watermark) {
            this._drawWatermarkPreview(ctx);
        }

        ctx.restore();

        // Frame foreground (shadow and inside stroke drawn after image)
        if (this.frame && (this.frame.type === 'shadow'
            || (this.frame.type === 'stroke' && this.frame.position === 'inside'))) {
            this._drawFramePreview(ctx);
        }

        // Active tool overlay
        if (this.activeTool) {
            this.activeTool.renderOverlay(ctx, this.scale, this.offsetX, this.offsetY);
        }
    }

    _drawPixelatedPreview(ctx, rx, ry, rw, rh) {
        const blockSize = Math.max(4, Math.floor(Math.min(rw, rh) / 8));
        try {
            const imgData = ctx.getImageData(rx, ry, rw, rh);
            const data = imgData.data;
            const w = Math.floor(rw);
            const h = Math.floor(rh);

            for (let by = 0; by < h; by += blockSize) {
                for (let bx = 0; bx < w; bx += blockSize) {
                    let r = 0, g = 0, b = 0, count = 0;
                    const bw = Math.min(blockSize, w - bx);
                    const bh = Math.min(blockSize, h - by);
                    for (let py = 0; py < bh; py++) {
                        for (let px = 0; px < bw; px++) {
                            const i = ((by + py) * w + (bx + px)) * 4;
                            if (i < data.length) {
                                r += data[i]; g += data[i + 1]; b += data[i + 2]; count++;
                            }
                        }
                    }
                    if (count) {
                        ctx.fillStyle = `rgb(${Math.round(r / count)},${Math.round(g / count)},${Math.round(b / count)})`;
                        ctx.fillRect(rx + bx, ry + by, bw, bh);
                    }
                }
            }
        } catch {
            ctx.fillStyle = '#000';
            ctx.fillRect(rx, ry, rw, rh);
        }
    }

    _drawWatermarkPreview(ctx) {
        const wm = this.watermark;
        const imgW = this.baseCanvas.width * this.scale;
        const imgH = this.baseCanvas.height * this.scale;

        ctx.save();
        ctx.beginPath();
        ctx.rect(this.offsetX, this.offsetY, imgW, imgH);
        ctx.clip();
        ctx.globalAlpha = wm.opacity ?? 0.5;

        if (wm.type === 'text') {
            const fontSize = (wm.fontSize || 48) * this.scale;
            ctx.font = `bold ${fontSize}px ${wm.fontFamily || 'sans-serif'}`;
            ctx.fillStyle = wm.color || '#ffffff';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'center';
            const x = (wm.x ?? 0.5) * imgW + this.offsetX;
            const y = (wm.y ?? 0.5) * imgH + this.offsetY;
            ctx.fillText(wm.content || 'Watermark', x, y);
        } else if (wm.type === 'image' && wm.image) {
            const iw = imgW * (wm.scale || 0.2);
            const ih = iw * (wm.image.height / wm.image.width);
            const x = (wm.x ?? 0.5) * imgW + this.offsetX - iw / 2;
            const y = (wm.y ?? 0.5) * imgH + this.offsetY - ih / 2;
            ctx.drawImage(wm.image, x, y, iw, ih);
        }

        ctx.restore();
    }

    _drawFramePreview(ctx) {
        const frame = this.frame;
        const fw = Math.round(frame.width * this.scale);
        const imgW = this.baseCanvas.width * this.scale;
        const imgH = this.baseCanvas.height * this.scale;
        const ox = this.offsetX;
        const oy = this.offsetY;

        ctx.save();
        if (frame.type === 'solid') {
            ctx.fillStyle = frame.color || '#ffffff';
            ctx.fillRect(ox - fw, oy - fw, imgW + fw * 2, imgH + fw * 2);
        } else if (frame.type === 'polaroid') {
            const bottomFw = fw * 3;
            ctx.fillStyle = frame.color || '#ffffff';
            ctx.fillRect(ox - fw, oy - fw, imgW + fw * 2, imgH + fw + bottomFw);
        } else if (frame.type === 'stroke') {
            const sw = Math.round((frame.strokeWidth || 3) * this.scale);
            const pad = Math.round((frame.padding || 0) * this.scale);
            ctx.strokeStyle = frame.color || '#ffffff';
            ctx.lineWidth = sw;
            if (frame.position === 'outside') {
                // Stroke sits outside the image edge, offset by padding
                const offset = pad + sw / 2;
                ctx.strokeRect(ox - offset, oy - offset,
                    imgW + offset * 2, imgH + offset * 2);
            } else {
                // Stroke sits inside the image edge, inset by padding
                const inset = pad + sw / 2;
                ctx.strokeRect(ox + inset, oy + inset,
                    imgW - inset * 2, imgH - inset * 2);
            }
        } else if (frame.type === 'shadow') {
            ctx.shadowColor = 'rgba(0,0,0,0.5)';
            ctx.shadowBlur = fw;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = fw / 4;
            ctx.strokeStyle = 'rgba(0,0,0,0)';
            ctx.lineWidth = 1;
            ctx.strokeRect(this.offsetX, this.offsetY, imgW, imgH);
        }
        ctx.restore();
    }

    async _save() {
        if (this._saving) return;

        const originalName = this.options.filename || 'image';
        const newName = prompt('Save as:', originalName);
        if (!newName) return;

        if (newName === originalName) {
            if (!confirm(`Overwrite "${originalName}"?`)) return;
        }

        this._saving = true;
        this._saveBtn.disabled = true;
        this._saveBtn.textContent = 'Saving…';

        try {
            const state = {
                adjustments: { ...this.adjustments },
                annotations: this.annotations,
                redactions: this.redactions,
                watermark: this.watermark,
                frame: this.frame,
            };

            const targetW = this.baseCanvas.width;
            const targetH = this.baseCanvas.height;
            const exportCanvas = renderToCanvas(this.baseCanvas, state, targetW, targetH);

            const mimeType = this.options.saveFormat || 'image/webp';
            const quality = this.options.saveQuality ?? 0.85;
            const blob = await exportBlob(exportCanvas, mimeType, quality);

            await this.options.onSave?.(exportCanvas, blob, newName);
        } finally {
            this._saving = false;
            this._saveBtn.disabled = false;
            this._saveBtn.textContent = 'Save';
        }
    }

    _snapshot() {
        const baseClone = document.createElement('canvas');
        baseClone.width = this.baseCanvas.width;
        baseClone.height = this.baseCanvas.height;
        baseClone.getContext('2d').drawImage(this.baseCanvas, 0, 0);

        return {
            baseCanvas: baseClone,
            adjustments: { ...this.adjustments },
            annotations: structuredClone(this.annotations),
            redactions: structuredClone(this.redactions),
            watermark: this.watermark ? { ...this.watermark } : null,
            frame: this.frame ? { ...this.frame } : null,
        };
    }

    _restore(snapshot) {
        this.baseCanvas = snapshot.baseCanvas;
        this.adjustments = snapshot.adjustments;
        this.annotations = snapshot.annotations;
        this.redactions = snapshot.redactions;
        this.watermark = snapshot.watermark;
        this.frame = snapshot.frame;
        this._fitCanvas();
    }

    _updateUndoButtons() {
        if (this._undoBtn) this._undoBtn.disabled = !this._undoStack.length;
        if (this._redoBtn) this._redoBtn.disabled = !this._redoStack.length;
    }

    _getFilename() {
        const src = this.options.source || '';
        const name = src.split('/').pop()?.split('?')[0] || 'image';
        return name;
    }
}
