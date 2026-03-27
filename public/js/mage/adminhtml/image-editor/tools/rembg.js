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

let pipelinePromise = null;

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
    if (pipelinePromise) {
        return pipelinePromise;
    }

    pipelinePromise = (async () => {
        try {
            const { pipeline } = await import('https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.8.1');
            const device = await hasWebGPU() ? 'webgpu' : 'wasm';
            return pipeline('background-removal', MODEL_ID, {
                dtype: MODEL_DTYPE,
                device,
                progress_callback: onProgress,
            });
        } catch (e) {
            pipelinePromise = null;
            throw e;
        }
    })();

    return pipelinePromise;
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

    ctx.drawImage(source, 0, 0);

    const maskUpscaled = document.createElement('canvas');
    maskUpscaled.width = width;
    maskUpscaled.height = height;
    const maskCtx = maskUpscaled.getContext('2d');
    maskCtx.drawImage(maskCanvas, 0, 0, width, height);

    const srcData = ctx.getImageData(0, 0, width, height);
    const maskData = maskCtx.getImageData(0, 0, width, height);

    for (let i = 3; i < srcData.data.length; i += 4) {
        srcData.data[i] = maskData.data[i];
    }

    ctx.putImageData(srcData, 0, 0);
    return out;
}

function fillBackground(source, mode, color1, color2, gradientDirection) {
    const { width, height } = source;
    const out = document.createElement('canvas');
    out.width = width;
    out.height = height;
    const ctx = out.getContext('2d');

    if (mode === 'gradient') {
        let grad;
        switch (gradientDirection) {
            case 'to-bottom':
                grad = ctx.createLinearGradient(0, 0, 0, height);
                break;
            case 'to-right':
                grad = ctx.createLinearGradient(0, 0, width, 0);
                break;
            case 'to-br':
                grad = ctx.createLinearGradient(0, 0, width, height);
                break;
            case 'radial':
                grad = ctx.createRadialGradient(width / 2, height / 2, 0, width / 2, height / 2, Math.max(width, height) / 2);
                break;
            default:
                grad = ctx.createLinearGradient(0, 0, 0, height);
        }
        grad.addColorStop(0, color1);
        grad.addColorStop(1, color2);
        ctx.fillStyle = grad;
    } else {
        ctx.fillStyle = color1;
    }

    ctx.fillRect(0, 0, width, height);
    ctx.drawImage(source, 0, 0);
    return out;
}

export class RemoveBackgroundTool {
    constructor(editor) {
        this.editor = editor;
        this._processing = false;
        this._statusEl = null;
        this._removeBtn = null;
        this._transparentCanvas = null;
        this._bgApplied = false;
    }

    get name() { return 'rembg'; }
    get label() { return 'Remove BG'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5z"/><path d="M7 7h4v4H7z" fill="currentColor" opacity="0.3"/><path d="M13 7h4v4h-4z"/><path d="M7 13h4v4H7z"/><path d="M13 13h4v4h-4z" fill="currentColor" opacity="0.3"/></svg>';
    }

    activate() {
        this._transparentCanvas = null;
        this._bgApplied = false;
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

        const removeBtn = document.createElement('button');
        removeBtn.className = 'maho-ie-opt-btn';
        if (this._processing) {
            removeBtn.textContent = 'Processing…';
            removeBtn.disabled = true;
        } else {
            removeBtn.textContent = 'Remove Background';
        }
        removeBtn.addEventListener('click', () => this._runRemove());
        this._removeBtn = removeBtn;
        el.appendChild(removeBtn);

        const divider = document.createElement('div');
        divider.className = 'maho-ie-options-divider';
        el.appendChild(divider);

        const modeSelect = document.createElement('div');
        modeSelect.className = 'maho-ie-select';
        const modeLabel = document.createElement('label');
        modeLabel.textContent = 'Fill';
        const modeDropdown = document.createElement('select');
        modeDropdown.innerHTML = '<option value="solid">Solid</option><option value="gradient">Gradient</option>';
        modeSelect.append(modeLabel, modeDropdown);
        el.appendChild(modeSelect);

        const color1Wrap = document.createElement('div');
        color1Wrap.className = 'maho-ie-color';
        const color1Label = document.createElement('label');
        color1Label.textContent = 'Color';
        const updateLabels = () => {
            color1Label.textContent = modeDropdown.value === 'gradient' ? 'From' : 'Color';
        };
        const color1Input = document.createElement('input');
        color1Input.type = 'color';
        color1Input.value = '#ffffff';
        color1Wrap.append(color1Label, color1Input);
        el.appendChild(color1Wrap);

        const gradientControls = document.createElement('div');
        gradientControls.style.cssText = 'display:none';

        const color2Wrap = document.createElement('div');
        color2Wrap.className = 'maho-ie-color';
        const color2Label = document.createElement('label');
        color2Label.textContent = 'To';
        const color2Input = document.createElement('input');
        color2Input.type = 'color';
        color2Input.value = '#e0e0e0';
        color2Wrap.append(color2Label, color2Input);
        gradientControls.appendChild(color2Wrap);

        const dirSelect = document.createElement('div');
        dirSelect.className = 'maho-ie-select';
        const dirLabel = document.createElement('label');
        dirLabel.textContent = 'Direction';
        const dirDropdown = document.createElement('select');
        dirDropdown.innerHTML =
            '<option value="to-bottom">↓ Top to bottom</option>' +
            '<option value="to-right">→ Left to right</option>' +
            '<option value="to-br">↘ Diagonal</option>' +
            '<option value="radial">◎ Radial</option>';
        dirSelect.append(dirLabel, dirDropdown);
        gradientControls.appendChild(dirSelect);

        el.appendChild(gradientControls);

        modeDropdown.addEventListener('change', () => {
            gradientControls.style.display = modeDropdown.value === 'gradient' ? '' : 'none';
            updateLabels();
        });

        const fillBtn = document.createElement('button');
        fillBtn.className = 'maho-ie-opt-btn';
        fillBtn.textContent = 'Apply Background';
        fillBtn.disabled = !this._transparentCanvas;
        fillBtn.addEventListener('click', () => {
            const source = this._transparentCanvas;
            if (!source) return;
            if (!this._bgApplied) {
                this.editor.pushUndo();
            }
            const result = fillBackground(
                source,
                modeDropdown.value,
                color1Input.value,
                color2Input.value,
                dirDropdown.value,
            );
            this.editor.replaceBase(result);
            this._bgApplied = true;
        });
        el.appendChild(fillBtn);

        const status = document.createElement('span');
        status.style.cssText = 'font-size:11px;color:#a1a1aa';
        this._statusEl = status;
        el.appendChild(status);

        return el;
    }

    async _runRemove() {
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

            this._transparentCanvas = outputCanvas;
            this._bgApplied = false;
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
