// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

export const SLIDERS = [
    { key: 'exposure', group: 'Light', label: 'Exposure', min: -100, max: 100 },
    { key: 'contrast', group: 'Light', label: 'Contrast', min: -100, max: 100 },
    { key: 'highlights', group: 'Light', label: 'Highlights', min: -100, max: 100 },
    { key: 'shadows', group: 'Light', label: 'Shadows', min: -100, max: 100 },
    { key: 'temperature', group: 'Color', label: 'Temperature', min: -100, max: 100 },
    { key: 'tint', group: 'Color', label: 'Tint', min: -100, max: 100 },
    { key: 'saturation', group: 'Color', label: 'Saturation', min: -100, max: 100 },
    { key: 'vibrance', group: 'Color', label: 'Vibrance', min: -100, max: 100 },
    { key: 'sharpness', group: 'Effects', label: 'Sharpness', min: 0, max: 100 },
    { key: 'vignette', group: 'Effects', label: 'Vignette', min: -100, max: 100 },
];

// A filter adds its values to the sliders. The tone adds an RGB offset to the shadows and to the highlights.
export const FILTERS = [
    { id: 'none', label: 'Original', values: {} },
    { id: 'vivid', label: 'Vivid', values: { contrast: 35, saturation: 28, vibrance: 25, sharpness: 22 } },
    { id: 'warm', label: 'Warm', values: { temperature: 30, tint: 5, saturation: 5 } },
    { id: 'cool', label: 'Cool', values: { temperature: -30, tint: -5, contrast: 5 } },
    {
        id: 'golden', label: 'Golden hour',
        values: { temperature: 50, tint: 8, exposure: 5, contrast: 15, highlights: -15, saturation: 15, vibrance: 10, vignette: 20 },
        tone: { shadows: [0.03, 0.01, -0.03], highlights: [0.06, 0.03, -0.05] },
    },
    { id: 'faded', label: 'Faded', values: { contrast: -20, saturation: -15 }, tone: { shadows: [0.08, 0.08, 0.08] } },
    {
        id: 'matte', label: 'Matte', values: { contrast: -10, saturation: -20, shadows: 15 },
        tone: { shadows: [0.04, 0.05, 0.07], highlights: [0.01, 0, -0.01] },
    },
    {
        id: 'vintage', label: 'Vintage', values: { contrast: -10, saturation: -30, temperature: 15, vignette: 30 },
        tone: { shadows: [0.07, 0.05, 0.08], highlights: [0.06, 0.03, -0.05] },
    },
    {
        id: 'cinematic', label: 'Cinematic', values: { exposure: -8, contrast: 35, saturation: -10, vignette: 30 },
        tone: { shadows: [-0.05, 0.01, 0.07], highlights: [0.07, 0.02, -0.05] },
    },
    {
        id: 'dramatic', label: 'Dramatic',
        values: { exposure: -10, contrast: 60, highlights: -35, shadows: -25, saturation: -20, sharpness: 40, vignette: 45 },
    },
    { id: 'mono', label: 'Mono', values: { saturation: -100, contrast: 15 } },
    { id: 'noir', label: 'Noir', values: { saturation: -100, contrast: 70, shadows: -30, highlights: 10, vignette: 45 } },
    {
        id: 'sepia', label: 'Sepia', values: { saturation: -100, contrast: 10 },
        tone: { shadows: [0.04, 0.01, -0.05], highlights: [0.11, 0.05, -0.08] },
    },
];

const LUMA_R = 0.2126;
const LUMA_G = 0.7152;
const LUMA_B = 0.0722;
const ENCODE_STEPS = 4096;
const SHARPEN_REFERENCE_SIDE = 1600;

const clamp = (v, min, max) => (v < min ? min : v > max ? max : v);
const smoothstep = (edge0, edge1, v) => {
    const t = clamp((v - edge0) / (edge1 - edge0), 0, 1);
    return t * t * (3 - 2 * t);
};
const toLinear = (v) => (v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
const toSrgb = (v) => (v <= 0.0031308 ? v * 12.92 : 1.055 * v ** (1 / 2.4) - 0.055);

const LINEAR = Float32Array.from({ length: 256 }, (_, i) => toLinear(i / 255));

export function neutralAdjustments() {
    const adjustments = { filter: 'none', filterStrength: 100 };
    for (const s of SLIDERS) adjustments[s.key] = 0;
    return adjustments;
}

export function isNeutral(adjustments) {
    const filterOn = adjustments.filter !== 'none' && adjustments.filterStrength > 0;
    return !filterOn && SLIDERS.every((s) => !adjustments[s.key]);
}

/** Add the filter at its strength to the slider values, and scale each value to -1..1. */
function resolve(adjustments) {
    const filter = FILTERS.find((f) => f.id === adjustments.filter) ?? FILTERS[0];
    const strength = filter.id === 'none' ? 0 : adjustments.filterStrength / 100;
    const values = {};
    for (const s of SLIDERS) {
        const value = (adjustments[s.key] || 0) + (filter.values[s.key] || 0) * strength;
        values[s.key] = clamp(value, s.min, s.max) / 100;
    }
    const scale = (rgb) => (rgb ?? [0, 0, 0]).map((c) => c * strength);
    values.tone = filter.tone && strength
        ? { shadows: scale(filter.tone.shadows), highlights: scale(filter.tone.highlights) }
        : null;
    return values;
}

/** A positive value applies an S-curve, which keeps black and white in place. A negative value pulls the values toward mid gray. */
function contrastCurve(v, contrast) {
    if (contrast < 0) return 0.5 + (v - 0.5) * (1 + 0.6 * contrast);
    const s = v * v * (3 - 2 * v);
    return v + (s * s * (3 - 2 * s) - v) * contrast;
}

/** White balance gains in linear light, normalized so that the brightness of a gray stays the same. */
function whiteBalanceGains(temperature, tint) {
    const r = 2 ** (0.4 * temperature);
    const g = 2 ** (-0.3 * tint);
    const b = 2 ** (-0.4 * temperature);
    const luma = LUMA_R * r + LUMA_G * g + LUMA_B * b;
    return [r / luma, g / luma, b / luma];
}

/**
 * Apply the adjustments to the image in place and return it.
 * Exposure and white balance work in linear light. The other steps work on the sRGB values.
 */
export function applyAdjustments(image, adjustments) {
    if (isNeutral(adjustments)) return image;
    const v = resolve(adjustments);
    const { width: w, height: h, data: d } = image;

    const gain = 2 ** (2 * v.exposure);
    const [gainR, gainG, gainB] = whiteBalanceGains(v.temperature, v.tint);
    const decodeR = LINEAR.map((x) => x * gain * gainR);
    const decodeG = LINEAR.map((x) => x * gain * gainG);
    const decodeB = LINEAR.map((x) => x * gain * gainB);
    const encode = new Float32Array(ENCODE_STEPS + 1);
    for (let i = 0; i <= ENCODE_STEPS; i++) encode[i] = contrastCurve(toSrgb(i / ENCODE_STEPS), v.contrast);
    const encodeIndex = (x) => (x >= 1 ? ENCODE_STEPS : (x * ENCODE_STEPS) | 0);

    const { shadows, highlights, saturation, vibrance, vignette, tone } = v;
    const halfW = w / 2, halfH = h / 2;

    for (let y = 0, i = 0; y < h; y++) {
        const dy = (y + 0.5 - halfH) / halfH;
        for (let x = 0; x < w; x++, i += 4) {
            let r = encode[encodeIndex(decodeR[d[i]])];
            let g = encode[encodeIndex(decodeG[d[i + 1]])];
            let b = encode[encodeIndex(decodeB[d[i + 2]])];
            let l = LUMA_R * r + LUMA_G * g + LUMA_B * b;

            if (shadows || highlights) {
                // Both curves are 0 at black and at white, so the end points of the range stay where they are.
                const t = clamp(l, 0, 1);
                const delta = 1.7 * (shadows * t * (1 - t) * (1 - t) + highlights * t * t * (1 - t));
                r += delta; g += delta; b += delta; l += delta;
            }

            if (saturation || vibrance) {
                const chroma = Math.min(1, Math.max(r, g, b) - Math.min(r, g, b));
                const amount = Math.max(0, (1 + saturation) * (1 + vibrance * (1 - chroma) * (1 - chroma)));
                r = l + (r - l) * amount;
                g = l + (g - l) * amount;
                b = l + (b - l) * amount;
            }

            if (tone) {
                const t = clamp(l, 0, 1);
                const ws = 1 - t, wh = t;
                r += tone.shadows[0] * ws + tone.highlights[0] * wh;
                g += tone.shadows[1] * ws + tone.highlights[1] * wh;
                b += tone.shadows[2] * ws + tone.highlights[2] * wh;
            }

            if (vignette) {
                const dx = (x + 0.5 - halfW) / halfW;
                const falloff = smoothstep(0.35, 1, Math.sqrt((dx * dx + dy * dy) / 2));
                if (vignette > 0) {
                    const m = 1 - vignette * 0.8 * falloff;
                    r *= m; g *= m; b *= m;
                } else {
                    const m = -vignette * 0.7 * falloff;
                    r += (1 - r) * m; g += (1 - g) * m; b += (1 - b) * m;
                }
            }

            d[i] = r * 255;
            d[i + 1] = g * 255;
            d[i + 2] = b * 255;
        }
    }

    if (v.sharpness > 0) sharpen(image, v.sharpness);
    return image;
}

/** Unsharp mask on the luminance only, so the edges get no color fringes. */
function sharpen(image, amount) {
    const { width: w, height: h, data: d } = image;
    const step = Math.max(1, Math.round(Math.max(w, h) / SHARPEN_REFERENCE_SIDE));
    const luma = new Float32Array(w * h);
    for (let p = 0, i = 0; p < luma.length; p++, i += 4) {
        luma[p] = LUMA_R * d[i] + LUMA_G * d[i + 1] + LUMA_B * d[i + 2];
    }
    const strength = amount * 1.6;
    for (let y = 0; y < h; y++) {
        const up = Math.max(0, y - step) * w, row = y * w, down = Math.min(h - 1, y + step) * w;
        for (let x = 0; x < w; x++) {
            const left = Math.max(0, x - step), right = Math.min(w - 1, x + step);
            const blur = (4 * luma[row + x]
                + 2 * (luma[up + x] + luma[down + x] + luma[row + left] + luma[row + right])
                + luma[up + left] + luma[up + right] + luma[down + left] + luma[down + right]) / 16;
            const delta = (luma[row + x] - blur) * strength;
            const i = (row + x) * 4;
            d[i] += delta; d[i + 1] += delta; d[i + 2] += delta;
        }
    }
}

/**
 * Suggest slider values for the image. A product photo on a white background keeps its exposure, and
 * the white balance reads only the pixels that are nearly gray, so a strong product color does not shift it.
 */
export function autoAdjustments(image) {
    const d = image.data;
    const histogram = new Uint32Array(256);
    let count = 0, grayCount = 0, sumR = 0, sumG = 0, sumB = 0;
    for (let i = 0; i < d.length; i += 4) {
        if (d[i + 3] < 128) continue;
        const r = d[i], g = d[i + 1], b = d[i + 2];
        const y = Math.round(LUMA_R * r + LUMA_G * g + LUMA_B * b);
        histogram[y]++;
        count++;
        if (y > 40 && y < 235 && Math.max(r, g, b) - Math.min(r, g, b) < 40) {
            sumR += LINEAR[r]; sumG += LINEAR[g]; sumB += LINEAR[b];
            grayCount++;
        }
    }
    if (!count) return {};

    const percentile = (p) => {
        let seen = 0;
        for (let i = 0; i < 256; i++) {
            seen += histogram[i];
            if (seen >= p * count) return i / 255;
        }
        return 1;
    };
    const fraction = (from, to) => {
        let n = 0;
        for (let i = from; i <= to; i++) n += histogram[i];
        return n / count;
    };
    const low = percentile(0.005), high = percentile(0.995), median = Math.max(0.02, percentile(0.5));

    const values = { vibrance: 15, sharpness: 10 };
    if (median < 0.4 || (median > 0.6 && high < 0.95)) {
        const stops = Math.log2(toLinear(0.46) / toLinear(median));
        values.exposure = Math.round(clamp(stops * 30, -50, 50));
    }
    values.contrast = Math.round(clamp((0.85 - (high - low)) * 100, 0, 35));
    values.shadows = Math.round(clamp(fraction(0, 50) * 60, 0, 30));
    // A white background is not a highlight to recover, so pure white stays out of the count.
    values.highlights = Math.round(clamp(-fraction(200, 249) * 60, -30, 0)) + 0;

    if (grayCount > count * 0.02) {
        // Half of the full correction, because a gray-world guess can be wrong.
        const redBlue = Math.log2(sumR / sumB);
        const green = Math.log2(sumG / Math.sqrt(sumR * sumB));
        values.temperature = Math.round(clamp((-redBlue / 0.8) * 50, -25, 25));
        values.tint = Math.round(clamp((green / 0.3) * 50, -20, 20));
    }
    return values;
}
