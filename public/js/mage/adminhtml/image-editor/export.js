/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

export function buildFilterString(adjustments) {
    const parts = [];
    if (adjustments.brightness !== 100) parts.push(`brightness(${adjustments.brightness}%)`);
    if (adjustments.contrast !== 100) parts.push(`contrast(${adjustments.contrast}%)`);
    if (adjustments.hue !== 0) parts.push(`hue-rotate(${adjustments.hue}deg)`);
    if (adjustments.saturation !== 100) parts.push(`saturate(${adjustments.saturation}%)`);
    if (adjustments.warmth > 0) parts.push(`sepia(${adjustments.warmth}%)`);
    return parts.length ? parts.join(' ') : 'none';
}

export function renderToCanvas(baseCanvas, state, targetWidth, targetHeight) {
    const scale = targetWidth / baseCanvas.width;
    const pad = state.frame ? getFramePadding(state.frame) : { top: 0, right: 0, bottom: 0, left: 0 };
    const totalW = targetWidth + pad.left + pad.right;
    const totalH = targetHeight + pad.top + pad.bottom;

    const canvas = document.createElement('canvas');
    canvas.width = totalW;
    canvas.height = totalH;
    const ctx = canvas.getContext('2d');

    // Frame background
    if (state.frame) {
        drawFrameBackground(ctx, state.frame, totalW, totalH);
    }

    ctx.save();
    ctx.translate(pad.left, pad.top);

    // Base image with adjustments
    const filter = buildFilterString(state.adjustments);
    ctx.filter = filter;
    ctx.drawImage(baseCanvas, 0, 0, targetWidth, targetHeight);
    ctx.filter = 'none';

    // Redactions (destructive)
    for (const r of state.redactions) {
        const rx = r.x * scale;
        const ry = r.y * scale;
        const rw = r.w * scale;
        const rh = r.h * scale;

        if (r.style === 'pixelate') {
            drawPixelated(ctx, canvas, rx, ry, rw, rh);
        } else {
            ctx.fillStyle = '#000';
            ctx.fillRect(rx, ry, rw, rh);
        }
    }

    // Annotations
    for (const a of state.annotations) {
        drawAnnotation(ctx, a, scale);
    }

    // Watermark
    if (state.watermark) {
        drawWatermark(ctx, state.watermark, targetWidth, targetHeight);
    }

    ctx.restore();

    // Frame foreground decoration
    if (state.frame) {
        drawFrameForeground(ctx, state.frame, totalW, totalH, pad);
    }

    return canvas;
}

export function exportBlob(canvas, mimeType, quality) {
    return new Promise(resolve => {
        if (mimeType === 'image/png' || mimeType === 'image/gif') {
            canvas.toBlob(resolve, mimeType);
        } else {
            canvas.toBlob(resolve, mimeType, quality);
        }
    });
}

function drawPixelated(ctx, canvas, rx, ry, rw, rh) {
    const blockSize = Math.max(8, Math.floor(Math.min(rw, rh) / 10));

    // Read existing pixels from the canvas (including adjustments)
    let imgData;
    try {
        imgData = ctx.getImageData(rx, ry, rw, rh);
    } catch {
        ctx.fillStyle = '#000';
        ctx.fillRect(rx, ry, rw, rh);
        return;
    }

    // Pixelate by averaging blocks
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
                        r += data[i];
                        g += data[i + 1];
                        b += data[i + 2];
                        count++;
                    }
                }
            }
            if (count > 0) {
                r = Math.round(r / count);
                g = Math.round(g / count);
                b = Math.round(b / count);
                ctx.fillStyle = `rgb(${r},${g},${b})`;
                ctx.fillRect(rx + bx, ry + by, bw, bh);
            }
        }
    }
}

export function drawAnnotation(ctx, a, scale) {
    ctx.save();
    const lw = (a.lineWidth || 2) * scale;

    switch (a.type) {
        case 'rect': {
            const x = a.x * scale, y = a.y * scale, w = a.w * scale, h = a.h * scale;
            if (a.fillColor && a.fillColor !== 'transparent') {
                ctx.fillStyle = a.fillColor;
                ctx.fillRect(x, y, w, h);
            }
            ctx.strokeStyle = a.strokeColor || '#ff0000';
            ctx.lineWidth = lw;
            ctx.strokeRect(x, y, w, h);
            break;
        }
        case 'ellipse': {
            const cx = a.cx * scale, cy = a.cy * scale, rx = a.rx * scale, ry = a.ry * scale;
            ctx.beginPath();
            ctx.ellipse(cx, cy, Math.abs(rx), Math.abs(ry), 0, 0, Math.PI * 2);
            if (a.fillColor && a.fillColor !== 'transparent') {
                ctx.fillStyle = a.fillColor;
                ctx.fill();
            }
            ctx.strokeStyle = a.strokeColor || '#ff0000';
            ctx.lineWidth = lw;
            ctx.stroke();
            break;
        }
        case 'line': {
            ctx.beginPath();
            ctx.moveTo(a.x1 * scale, a.y1 * scale);
            ctx.lineTo(a.x2 * scale, a.y2 * scale);
            ctx.strokeStyle = a.color || '#ff0000';
            ctx.lineWidth = lw;
            ctx.stroke();
            break;
        }
        case 'arrow': {
            const x1 = a.x1 * scale, y1 = a.y1 * scale;
            const x2 = a.x2 * scale, y2 = a.y2 * scale;
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.strokeStyle = a.color || '#ff0000';
            ctx.lineWidth = lw;
            ctx.stroke();

            // Arrowhead
            const angle = Math.atan2(y2 - y1, x2 - x1);
            const headLen = lw * 5;
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headLen * Math.cos(angle - 0.4), y2 - headLen * Math.sin(angle - 0.4));
            ctx.lineTo(x2 - headLen * Math.cos(angle + 0.4), y2 - headLen * Math.sin(angle + 0.4));
            ctx.closePath();
            ctx.fillStyle = a.color || '#ff0000';
            ctx.fill();
            break;
        }
        case 'freehand': {
            if (a.points.length < 2) break;
            ctx.beginPath();
            ctx.moveTo(a.points[0].x * scale, a.points[0].y * scale);
            for (let i = 1; i < a.points.length; i++) {
                ctx.lineTo(a.points[i].x * scale, a.points[i].y * scale);
            }
            ctx.strokeStyle = a.color || '#ff0000';
            ctx.lineWidth = lw;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();
            break;
        }
        case 'text': {
            const fontSize = (a.fontSize || 24) * scale;
            const weight = a.bold ? 'bold' : 'normal';
            const style = a.italic ? 'italic' : 'normal';
            ctx.font = `${style} ${weight} ${fontSize}px ${a.fontFamily || 'sans-serif'}`;
            ctx.fillStyle = a.color || '#ff0000';
            ctx.textBaseline = 'top';
            const lines = (a.text || '').split('\n');
            let y = a.y * scale;
            for (const line of lines) {
                ctx.fillText(line, a.x * scale, y);
                y += fontSize * 1.2;
            }
            break;
        }
    }
    ctx.restore();
}

function drawWatermark(ctx, wm, w, h) {
    ctx.save();
    ctx.beginPath();
    ctx.rect(0, 0, w, h);
    ctx.clip();
    ctx.globalAlpha = wm.opacity ?? 0.5;

    if (wm.type === 'text') {
        const fontSize = wm.fontSize || 48;
        ctx.font = `bold ${fontSize}px sans-serif`;
        ctx.fillStyle = wm.color || '#ffffff';
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'center';
        const x = (wm.x ?? 0.5) * w;
        const y = (wm.y ?? 0.5) * h;
        ctx.fillText(wm.content || 'Watermark', x, y);
    } else if (wm.type === 'image' && wm.image) {
        const imgScale = wm.scale || 0.2;
        const iw = w * imgScale;
        const ih = iw * (wm.image.height / wm.image.width);
        const x = (wm.x ?? 0.5) * w - iw / 2;
        const y = (wm.y ?? 0.5) * h - ih / 2;
        ctx.drawImage(wm.image, x, y, iw, ih);
    }

    ctx.restore();
}

function getFramePadding(frame) {
    if (!frame || frame.type === 'none') return { top: 0, right: 0, bottom: 0, left: 0 };
    if (frame.type === 'stroke') {
        if (frame.position === 'outside') {
            const total = (frame.padding || 0) + (frame.strokeWidth || 3);
            return { top: total, right: total, bottom: total, left: total };
        }
        return { top: 0, right: 0, bottom: 0, left: 0 };
    }
    const base = frame.width || 20;
    if (frame.type === 'polaroid') {
        return { top: base, right: base, bottom: base * 3, left: base };
    }
    return { top: base, right: base, bottom: base, left: base };
}

function drawFrameBackground(ctx, frame, totalW, totalH) {
    if (frame.type === 'solid' || frame.type === 'polaroid') {
        ctx.fillStyle = frame.color || '#ffffff';
        ctx.fillRect(0, 0, totalW, totalH);
    } else if (frame.type === 'shadow') {
        ctx.fillStyle = '#1b1b1f';
        ctx.fillRect(0, 0, totalW, totalH);
    }
}

function drawFrameForeground(ctx, frame, totalW, totalH, pad) {
    if (frame.type === 'stroke') {
        const sw = frame.strokeWidth || 3;
        const padding = frame.padding || 0;
        ctx.strokeStyle = frame.color || '#ffffff';
        ctx.lineWidth = sw;
        if (frame.position === 'outside') {
            // Image is at (pad.left, pad.top), stroke around it with gap
            const imgW = totalW - pad.left - pad.right;
            const imgH = totalH - pad.top - pad.bottom;
            const offset = padding + sw / 2;
            ctx.strokeRect(pad.left - offset, pad.top - offset,
                imgW + offset * 2, imgH + offset * 2);
        } else {
            // Inside: stroke inset from image edges
            const inset = padding + sw / 2;
            ctx.strokeRect(pad.left + inset, pad.top + inset,
                totalW - pad.left - pad.right - inset * 2,
                totalH - pad.top - pad.bottom - inset * 2);
        }
    } else if (frame.type === 'shadow') {
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.6)';
        ctx.shadowBlur = pad.top;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = pad.top / 4;
        ctx.fillStyle = 'rgba(0,0,0,0)';
        ctx.fillRect(pad.left, pad.top, totalW - pad.left - pad.right, totalH - pad.top - pad.bottom);
        ctx.restore();
    }
}
