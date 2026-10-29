// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

import { ModelWorker, ModelCancelled } from '../ml-runtime.js';

const MODEL_ID = 'MahoCommerce/Real_HAT_GAN_sharper-ONNX';
const MODEL_SCALE = 4;
// The ONNX export has a fixed input of TILE × TILE pixels. HAT does not run in fp16.
const TILE = 192;
const TILE_PAD = 16;
const TILE_CORE = TILE - 2 * TILE_PAD;
const MAX_INPUT_SIDE = 1024;

/**
 * Put the TILE × TILE area at (x, y) of the RGBA pixels into planar RGB values from 0 to 1.
 * A pixel outside the image takes the value of the nearest edge pixel.
 */
function readTile(rgba, width, height, x, y) {
    const plane = TILE * TILE;
    const data = new Float32Array(3 * plane);
    for (let ty = 0; ty < TILE; ty++) {
        const sy = Math.min(Math.max(y + ty, 0), height - 1);
        for (let tx = 0; tx < TILE; tx++) {
            const sx = Math.min(Math.max(x + tx, 0), width - 1);
            const s = (sy * width + sx) * 4;
            const d = ty * TILE + tx;
            data[d] = rgba[s] / 255;
            data[plane + d] = rgba[s + 1] / 255;
            data[2 * plane + d] = rgba[s + 2] / 255;
        }
    }
    return data;
}

/** Write the w × h area at (x, y) of the planar RGB model output into an opaque ImageData. */
function writeTile(output, x, y, w, h) {
    const side = TILE * MODEL_SCALE;
    const plane = side * side;
    const image = new ImageData(w, h);
    for (let oy = 0; oy < h; oy++) {
        for (let ox = 0; ox < w; ox++) {
            const s = (y + oy) * side + x + ox;
            const d = (oy * w + ox) * 4;
            image.data[d] = output[s] * 255;
            image.data[d + 1] = output[plane + s] * 255;
            image.data[d + 2] = output[2 * plane + s] * 255;
            image.data[d + 3] = 255;
        }
    }
    return image;
}

function formatRemaining(ms) {
    const minutes = Math.round(ms / 60000);
    return minutes < 1 ? 'less than a minute left' : `about ${minutes} min left`;
}

function hasTransparency(rgba) {
    for (let i = 3; i < rgba.length; i += 4) {
        if (rgba[i] < 255) return true;
    }
    return false;
}

export class UpscaleTool {
    constructor(editor) {
        this.editor = editor;
        this._processing = false;
        this._cancelled = false;
        this._scale = 2;
        this._statusEl = null;
        this._runBtn = null;
        this._model = new ModelWorker('image-to-image', MODEL_ID, 'fp32');
        this._tileStatus = null;
    }

    get name() { return 'upscale'; }
    get label() { return 'Upscale'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="13" width="8" height="8" rx="1"/><path d="M14 3h7v7"/><path d="M21 3l-8 8"/></svg>';
    }

    activate() {}
    deactivate() {
        if (this._processing) this._cancelled = true;
    }
    onPointerDown() {}
    onPointerMove() {}
    onPointerUp() {}
    onKeyDown() {}
    renderOverlay() {}

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-options-group';

        const { width, height } = this.editor.baseCanvas;
        const tooLarge = Math.max(width, height) > MAX_INPUT_SIDE;

        const scaleSelect = document.createElement('div');
        scaleSelect.className = 'maho-ie-select';
        const scaleLabel = document.createElement('label');
        scaleLabel.textContent = 'Scale';
        const scaleDropdown = document.createElement('select');
        scaleDropdown.innerHTML = '<option value="2">2×</option><option value="4">4×</option>';
        scaleDropdown.value = String(this._scale);
        scaleDropdown.disabled = this._processing;
        scaleSelect.append(scaleLabel, scaleDropdown);
        el.appendChild(scaleSelect);

        const size = document.createElement('span');
        size.style.cssText = 'font-size:11px;color:#a1a1aa';
        const updateSize = () => {
            size.textContent = `${width} × ${height} → ${width * this._scale} × ${height * this._scale} px`;
        };
        updateSize();
        scaleDropdown.addEventListener('change', () => {
            this._scale = Number(scaleDropdown.value);
            updateSize();
        });
        el.appendChild(size);

        const runBtn = document.createElement('button');
        runBtn.className = 'maho-ie-opt-btn';
        runBtn.textContent = this._processing ? 'Cancel' : 'Upscale';
        runBtn.disabled = tooLarge && !this._processing;
        runBtn.addEventListener('click', () => {
            if (this._processing) {
                this._cancelled = true;
                this._setStatus('Canceling…');
                this._model.terminate();
            } else {
                this._runUpscale();
            }
        });
        this._runBtn = runBtn;
        el.appendChild(runBtn);

        const status = document.createElement('span');
        status.style.cssText = 'font-size:11px;color:#a1a1aa';
        if (tooLarge && !this._processing) {
            status.textContent = `Upscale works on images up to ${MAX_INPUT_SIDE} px on the longest side. Resize the image first.`;
        }
        this._statusEl = status;
        el.appendChild(status);

        return el;
    }

    async _runUpscale() {
        if (this._processing) return;
        this._processing = true;
        this._cancelled = false;
        this.editor.setOptions(this.renderOptions());
        this._setStatus('Loading model…');

        this._model.onProgress = (p) => {
            if (p.status === 'progress' && p.total) {
                const pct = Math.round((p.loaded / p.total) * 100);
                this._setStatus(`Downloading model… ${pct}%`);
            } else if (p.status === 'ready') {
                this._setStatus(this._tileStatus?.() ?? 'Model ready, processing…');
            }
        };

        try {
            const source = this.editor.baseCanvas;
            const result = await this._upscale(source, this._scale);
            if (this._cancelled || this.editor.baseCanvas !== source) {
                result.width = 0;
                throw new ModelCancelled();
            }
            this.editor.pushUndo();
            this.editor.replaceBase(result);
            this._setStatus(`Upscaled to ${result.width} × ${result.height} px`);
        } catch (err) {
            if (err instanceof ModelCancelled) {
                this._setStatus('Canceled');
            } else {
                console.error('Upscale failed:', err);
                this._setStatus('Error: ' + err.message);
            }
        } finally {
            this._model.onProgress = null;
            this._model.onFallback = null;
            this._tileStatus = null;
            this._processing = false;
            if (this.editor.activeTool === this) {
                const status = this._statusEl?.textContent;
                this.editor.setOptions(this.renderOptions());
                this._setStatus(status);
            }
        }
    }

    async _upscale(source, scale) {
        const { width, height } = source;
        const rgba = source.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, width, height).data;

        const out = document.createElement('canvas');
        out.width = width * scale;
        out.height = height * scale;
        const outCtx = out.getContext('2d');
        outCtx.imageSmoothingQuality = 'high';

        const tileCanvas = document.createElement('canvas');
        const tileCtx = tileCanvas.getContext('2d');

        const cols = Math.ceil(width / TILE_CORE);
        const rows = Math.ceil(height / TILE_CORE);
        const total = cols * rows;

        // The estimate of the remaining time uses only the tiles of the current device.
        let timedTiles = 0;
        let timedSince = 0;
        this._model.onFallback = () => {
            timedTiles = 0;
            this._setStatus('The GPU failed. Loading the model on the CPU…');
        };

        try {
            for (let row = 0; row < rows; row++) {
                for (let col = 0; col < cols; col++) {
                    if (this._cancelled) throw new ModelCancelled();
                    const index = row * cols + col;
                    this._tileStatus = () => {
                        let status = `${this._model.onCpu ? 'Upscaling on the CPU' : 'Upscaling'}… tile ${index + 1} of ${total}`;
                        if (timedTiles > 0) {
                            status += ', ' + formatRemaining((performance.now() - timedSince) / timedTiles * (total - index));
                        }
                        return status;
                    };
                    this._setStatus(this._tileStatus());

                    const x = col * TILE_CORE;
                    const y = row * TILE_CORE;
                    const w = Math.min(TILE_CORE, width - x);
                    const h = Math.min(TILE_CORE, height - y);

                    const data = readTile(rgba, width, height, x - TILE_PAD, y - TILE_PAD);
                    const tileStart = performance.now();
                    const output = await this._model.run({ data, dims: [1, 3, TILE, TILE] });
                    if (timedTiles === 0) timedSince = tileStart;
                    timedTiles++;
                    const tile = writeTile(output, TILE_PAD * MODEL_SCALE, TILE_PAD * MODEL_SCALE, w * MODEL_SCALE, h * MODEL_SCALE);

                    tileCanvas.width = tile.width;
                    tileCanvas.height = tile.height;
                    tileCtx.putImageData(tile, 0, 0);
                    outCtx.drawImage(tileCanvas, x * scale, y * scale, w * scale, h * scale);
                }
            }
        } catch (e) {
            out.width = 0;
            throw e;
        } finally {
            tileCanvas.width = 0;
        }

        // The model has no alpha channel: scale the alpha of the source and apply it to the result.
        if (hasTransparency(rgba)) {
            outCtx.globalCompositeOperation = 'destination-in';
            outCtx.drawImage(source, 0, 0, out.width, out.height);
            outCtx.globalCompositeOperation = 'source-over';
        }

        return out;
    }

    _setStatus(text) {
        if (this._statusEl) this._statusEl.textContent = text;
    }

    destroy() {
        this._cancelled = true;
        this._model.terminate();
    }
}
