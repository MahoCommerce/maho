// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

export const TRANSFORMERS_URL = 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.8.1';

export async function hasWebGPU() {
    if (!navigator.gpu) return false;
    try {
        const adapter = await navigator.gpu.requestAdapter();
        return !!adapter;
    } catch {
        return false;
    }
}
