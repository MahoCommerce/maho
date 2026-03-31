/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

export class RedactTool {
    constructor(editor) {
        this.editor = editor;
        this.style = 'solid';
        this._drawing = null;
        this._startPos = null;
    }

    get name() { return 'redact'; }
    get label() { return 'Redact'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>';
    }

    activate() {}
    deactivate() {
        this._drawing = null;
    }

    onPointerDown(ix, iy) {
        this._startPos = { x: ix, y: iy };
        this._drawing = { x: ix, y: iy, w: 0, h: 0, style: this.style };
    }

    onPointerMove(ix, iy) {
        this.editor.setCursor('crosshair');
        if (!this._drawing) return;
        this._drawing.w = ix - this._startPos.x;
        this._drawing.h = iy - this._startPos.y;
        this.editor.requestRender();
    }

    onPointerUp() {
        if (!this._drawing) return;

        // Normalize negative dimensions
        if (this._drawing.w < 0) {
            this._drawing.x += this._drawing.w;
            this._drawing.w = -this._drawing.w;
        }
        if (this._drawing.h < 0) {
            this._drawing.y += this._drawing.h;
            this._drawing.h = -this._drawing.h;
        }

        if (this._drawing.w > 3 && this._drawing.h > 3) {
            this.editor.pushUndo();
            this.editor.redactions.push({ ...this._drawing });
        }

        this._drawing = null;
        this._startPos = null;
        this.editor.requestRender();
    }

    onKeyDown() {}

    renderOverlay(ctx, scale, offsetX, offsetY) {
        if (!this._drawing) return;
        const d = this._drawing;
        const rx = d.x * scale + offsetX;
        const ry = d.y * scale + offsetY;
        const rw = d.w * scale;
        const rh = d.h * scale;

        ctx.strokeStyle = '#ff0000';
        ctx.lineWidth = 2;
        ctx.setLineDash([6, 3]);
        ctx.strokeRect(rx, ry, rw, rh);
        ctx.setLineDash([]);

        ctx.fillStyle = d.style === 'solid' ? 'rgba(0,0,0,0.7)' : 'rgba(128,128,128,0.4)';
        ctx.fillRect(rx, ry, rw, rh);
    }

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-options-group';

        const label = document.createElement('span');
        label.style.cssText = 'font-size:11px;color:#a1a1aa';
        label.textContent = 'Style:';
        el.appendChild(label);

        const styles = [
            { value: 'solid', label: 'Solid Black' },
            { value: 'pixelate', label: 'Pixelate' },
        ];

        for (const s of styles) {
            const btn = document.createElement('button');
            btn.className = 'maho-ie-opt-btn' + (this.style === s.value ? ' active' : '');
            btn.textContent = s.label;
            btn.addEventListener('click', () => {
                this.style = s.value;
                el.querySelectorAll('.maho-ie-opt-btn:not(.maho-ie-opt-btn-danger)').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
            el.appendChild(btn);
        }

        // Divider
        const div = document.createElement('div');
        div.className = 'maho-ie-options-divider';
        el.appendChild(div);

        // Clear all
        const clearBtn = document.createElement('button');
        clearBtn.className = 'maho-ie-opt-btn maho-ie-opt-btn-danger';
        clearBtn.textContent = 'Clear All';
        clearBtn.addEventListener('click', () => {
            if (this.editor.redactions.length) {
                this.editor.pushUndo();
                this.editor.redactions = [];
                this.editor.requestRender();
            }
        });
        el.appendChild(clearBtn);

        // Info text
        const info = document.createElement('span');
        info.style.cssText = 'font-size:10px;color:#71717a;margin-left:8px';
        info.textContent = 'Redacted pixels are permanently destroyed on save';
        el.appendChild(info);

        return el;
    }

    destroy() {}
}
