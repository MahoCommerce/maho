// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

export const TRANSFORMERS_URL = 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.8.1';

// A run on the GPU that takes longer than this is a GPU that stopped without an error.
const GPU_TIMEOUT = 60000;
// The models that failed on the GPU of this browser. They run on the CPU from then on.
const CPU_MODELS_KEY = 'imageEditorCpuModels';

export async function hasWebGPU() {
    if (!navigator.gpu) return false;
    try {
        const adapter = await navigator.gpu.requestAdapter();
        return !!adapter;
    } catch {
        return false;
    }
}

function readCpuModels() {
    try {
        const models = JSON.parse(localStorage.getItem(CPU_MODELS_KEY) ?? '[]');
        return Array.isArray(models) ? models : [];
    } catch {
        return [];
    }
}

function addCpuModel(model) {
    const models = readCpuModels();
    if (models.includes(model)) return;
    try {
        localStorage.setItem(CPU_MODELS_KEY, JSON.stringify([...models, model]));
    } catch {}
}

let blobWorkerUrl = null;

/**
 * Return a same-origin URL that imports ml-worker.js. The js base URL can be on a CDN,
 * and a browser starts a worker only from the origin of the page.
 */
function workerUrl() {
    blobWorkerUrl ??= URL.createObjectURL(new Blob(
        [`import ${JSON.stringify(new URL('ml-worker.js', import.meta.url).href)};`],
        { type: 'text/javascript' },
    ));
    return blobWorkerUrl;
}

export class ModelCancelled extends Error {}

/**
 * Run an AI model in a Web Worker, so that the page stays responsive and a run can be canceled.
 * If the model fails on the GPU, run it again on the CPU and remember the CPU for this model.
 */
export class ModelWorker {
    /**
     * @param {'image-to-image'|'background-removal'} kind
     * @param {string} model
     * @param {string} dtype
     */
    constructor(kind, model, dtype) {
        this.kind = kind;
        this.model = model;
        this.dtype = dtype;
        /** @type {((progress: object) => void)|null} */
        this.onProgress = null;
        /** @type {(() => void)|null} */
        this.onFallback = null;
        this._worker = null;
        this._device = null;
        this._loading = null;
        this._calls = new Map();
        this._lastId = 0;
    }

    get onCpu() {
        return this._device === 'wasm';
    }

    async run(input) {
        try {
            await this._load();
            return await this._send({ type: 'run', input }, this._device === 'webgpu' ? GPU_TIMEOUT : 0);
        } catch (e) {
            if (e instanceof ModelCancelled || this._device !== 'webgpu') throw e;
            console.warn('The model failed on the GPU. It continues on the CPU.', e);
            addCpuModel(this.model);
            this.terminate();
            this.onFallback?.();
            await this._load();
            return this._send({ type: 'run', input });
        }
    }

    /** Stop the worker at once. A pending run rejects with ModelCancelled, and the next run loads the model again. */
    terminate() {
        this._worker?.terminate();
        this._worker = null;
        this._device = null;
        this._loading = null;
        this._rejectAll(new ModelCancelled());
    }

    _load() {
        this._loading ??= (async () => {
            const gpu = !readCpuModels().includes(this.model) && await hasWebGPU();
            this._device = gpu ? 'webgpu' : 'wasm';
            this._worker = new Worker(workerUrl(), { type: 'module' });
            this._worker.addEventListener('message', (event) => this._receive(event.data));
            this._worker.addEventListener('error', (event) => {
                event.preventDefault();
                this._rejectAll(new Error(event.message || 'The AI worker could not start.'));
            });
            await this._send({ type: 'load', kind: this.kind, model: this.model, dtype: this.dtype, device: this._device });
        })().catch((e) => {
            if (!(e instanceof ModelCancelled)) {
                this._worker?.terminate();
                this._worker = null;
                this._loading = null;
            }
            throw e;
        });
        return this._loading;
    }

    _send(message, timeout = 0) {
        const worker = this._worker;
        return new Promise((resolve, reject) => {
            const id = ++this._lastId;
            let timer = null;
            if (timeout) {
                timer = setTimeout(() => this._settle(id, null, new Error('The GPU did not finish the run in time.')), timeout);
            }
            this._calls.set(id, { resolve, reject, timer });
            worker.postMessage({ ...message, id });
        });
    }

    _receive(message) {
        if (message.type === 'progress') {
            this.onProgress?.(message.progress);
        } else if (message.type === 'lost') {
            this._rejectAll(new Error('The GPU stopped: ' + message.message));
        } else {
            this._settle(message.id, message.result, message.type === 'error' ? new Error(message.message) : null);
        }
    }

    _settle(id, result, error) {
        const call = this._calls.get(id);
        if (!call) return;
        this._calls.delete(id);
        clearTimeout(call.timer);
        if (error) {
            call.reject(error);
        } else {
            call.resolve(result);
        }
    }

    _rejectAll(error) {
        for (const id of [...this._calls.keys()]) {
            this._settle(id, null, error);
        }
    }
}
