/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const MODEL_ID = 'onnx-community/BEN2-ONNX';
const MODEL_DTYPE = 'fp16';
const MAX_PROCESSING_SIZE = 1024;

let pipelineInstance = null;

async function hasWebGPU() {
    if (!navigator.gpu) return false;
    try {
        const adapter = await navigator.gpu.requestAdapter();
        return !!adapter;
    } catch {
        return false;
    }
}

async function getSegmenter(onProgress) {
    if (pipelineInstance) {
        return pipelineInstance;
    }

    const { pipeline } = await import('https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.8.1');

    const device = await hasWebGPU() ? 'webgpu' : 'wasm';

    pipelineInstance = await pipeline('background-removal', MODEL_ID, {
        dtype: MODEL_DTYPE,
        device,
        progress_callback: onProgress,
    });

    return pipelineInstance;
}

function downscale(canvas, maxSize) {
    const { width, height } = canvas;
    if (width <= maxSize && height <= maxSize) {
        return { canvas, scale: 1 };
    }

    const ratio = Math.min(maxSize / width, maxSize / height);
    const w = Math.round(width * ratio);
    const h = Math.round(height * ratio);

    const small = document.createElement('canvas');
    small.width = w;
    small.height = h;
    small.getContext('2d').drawImage(canvas, 0, 0, w, h);

    return { canvas: small, scale: ratio };
}

function applyMask(source, maskCanvas) {
    const { width, height } = source;
    const out = document.createElement('canvas');
    out.width = width;
    out.height = height;
    const ctx = out.getContext('2d');

    // Draw original image
    ctx.drawImage(source, 0, 0);

    // Upscale mask to original size and use as alpha
    const maskUpscaled = document.createElement('canvas');
    maskUpscaled.width = width;
    maskUpscaled.height = height;
    const maskCtx = maskUpscaled.getContext('2d');
    maskCtx.drawImage(maskCanvas, 0, 0, width, height);

    // Extract alpha from the mask result and apply to original
    const srcData = ctx.getImageData(0, 0, width, height);
    const maskData = maskCtx.getImageData(0, 0, width, height);

    for (let i = 3; i < srcData.data.length; i += 4) {
        srcData.data[i] = maskData.data[i];
    }

    ctx.putImageData(srcData, 0, 0);
    return out;
}

export class RemoveBackgroundTool {
    constructor(editor) {
        this.editor = editor;
        this._processing = false;
        this._statusEl = null;
        this._removeBtn = null;
    }

    get name() { return 'rembg'; }
    get label() { return 'Remove BG'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5z"/><path d="M7 7h4v4H7z" fill="currentColor" opacity="0.3"/><path d="M13 7h4v4h-4z"/><path d="M7 13h4v4H7z"/><path d="M13 13h4v4h-4z" fill="currentColor" opacity="0.3"/></svg>';
    }

    activate() {}
    deactivate() {}
    onPointerDown() {}
    onPointerMove() {}
    onPointerUp() {}
    onKeyDown() {}
    renderOverlay() {}

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-options-group';

        const btn = document.createElement('button');
        btn.className = 'maho-ie-opt-btn';
        btn.textContent = 'Remove Background';
        btn.addEventListener('click', () => this._run());
        this._removeBtn = btn;
        el.appendChild(btn);

        const status = document.createElement('span');
        status.style.cssText = 'font-size:11px;color:#a1a1aa';
        this._statusEl = status;
        el.appendChild(status);

        return el;
    }

    async _run() {
        if (this._processing) return;
        this._processing = true;
        this._removeBtn.disabled = true;
        this._removeBtn.textContent = 'Processing…';
        this._setStatus('Loading model…');

        try {
            const segmenter = await getSegmenter((p) => {
                if (p.status === 'progress' && p.total) {
                    const pct = Math.round((p.loaded / p.total) * 100);
                    this._setStatus(`Downloading model… ${pct}%`);
                } else if (p.status === 'ready') {
                    this._setStatus('Model ready, processing…');
                }
            });

            this._setStatus('Removing background…');

            const source = this.editor.baseCanvas;
            const { canvas: inputCanvas, scale } = downscale(source, MAX_PROCESSING_SIZE);
            const blob = await new Promise(resolve => inputCanvas.toBlob(resolve, 'image/png'));
            const result = await segmenter(blob);
            const maskCanvas = result[0].toCanvas();

            let outputCanvas;
            if (scale < 1) {
                outputCanvas = applyMask(source, maskCanvas);
            } else {
                outputCanvas = maskCanvas;
            }

            this.editor.pushUndo();
            this.editor.replaceBase(outputCanvas);
            this._setStatus('Background removed');
        } catch (err) {
            console.error('Background removal failed:', err);
            this._setStatus('Error: ' + err.message);
        } finally {
            this._processing = false;
            this._removeBtn.disabled = false;
            this._removeBtn.textContent = 'Remove Background';
        }
    }

    _setStatus(text) {
        if (this._statusEl) this._statusEl.textContent = text;
    }

    destroy() {}
}
