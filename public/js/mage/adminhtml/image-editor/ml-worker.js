// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

import { TRANSFORMERS_URL } from './ml-runtime.js';

// A lost GPU returns a black image. A correct result keeps the mean brightness of the input.
const MAX_MEAN_DIFFERENCE = 0.1;

let run = null;

function mean(values) {
    let sum = 0;
    for (const value of values) {
        if (!Number.isFinite(value)) throw new Error('The model returned an invalid value.');
        sum += value;
    }
    return sum / values.length;
}

const loaders = {
    /** Input: a planar RGB tile with values from 0 to 1. Output: the upscaled tile in the same format. */
    async 'image-to-image'({ AutoModelForImageToImage, Tensor }, model, options) {
        const net = await AutoModelForImageToImage.from_pretrained(model, options);
        return async ({ data, dims }) => {
            const input = new Tensor('float32', data, dims);
            const { reconstruction } = await net({ pixel_values: input });
            input.dispose?.();
            const output = reconstruction.data.slice();
            reconstruction.dispose?.();
            if (Math.abs(mean(output) - mean(data)) > MAX_MEAN_DIFFERENCE) {
                throw new Error('The model returned an image that does not match the input.');
            }
            return output;
        };
    },

    /** Input: an image Blob. Output: the RGBA pixels of the image, with the mask of the product in the alpha channel. */
    async 'background-removal'({ pipeline }, model, options) {
        const segmenter = await pipeline('background-removal', model, options);
        return async (blob) => {
            const [image] = await segmenter([blob]);
            return new ImageData(image.data, image.width, image.height);
        };
    },
};

async function watchDevice(env) {
    try {
        const device = await env.backends.onnx.webgpu?.device;
        device?.lost.then((info) => self.postMessage({ type: 'lost', message: info.message || info.reason }));
    } catch {}
}

self.addEventListener('message', async ({ data: message }) => {
    try {
        if (message.type === 'load') {
            const transformers = await import(TRANSFORMERS_URL);
            run = await loaders[message.kind](transformers, message.model, {
                dtype: message.dtype,
                device: message.device,
                progress_callback: (progress) => self.postMessage({ type: 'progress', progress }),
            });
            if (message.device === 'webgpu') {
                watchDevice(transformers.env);
            }
            // from_pretrained() sends no 'ready' event, only pipeline() does.
            self.postMessage({ type: 'progress', progress: { status: 'ready' } });
            self.postMessage({ type: 'done', id: message.id });
        } else {
            self.postMessage({ type: 'done', id: message.id, result: await run(message.input) });
        }
    } catch (e) {
        self.postMessage({ type: 'error', id: message.id, message: e?.message ?? String(e) });
    }
});
