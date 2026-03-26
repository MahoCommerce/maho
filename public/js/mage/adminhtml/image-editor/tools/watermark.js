/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

export class WatermarkTool {
    constructor(editor) {
        this.editor = editor;
        this._dragging = false;
        this._dragStart = null;
        this._origPos = null;
    }

    get name() { return 'watermark'; }
    get label() { return 'Watermark'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>';
    }

    activate() {
        if (!this.editor.watermark) {
            this.editor.watermark = {
                type: 'text',
                content: 'Watermark',
                x: 0.5,
                y: 0.5,
                opacity: 0.3,
                fontSize: 48,
                color: '#ffffff',
                image: null,
                scale: 0.2,
            };
            this.editor.requestRender();
        }
    }

    deactivate() {}

    _hitTest(wm, ix, iy) {
        const imgW = this.editor.baseCanvas.width;
        const imgH = this.editor.baseCanvas.height;
        const cx = wm.x * imgW;
        const cy = wm.y * imgH;

        if (wm.type === 'image' && wm.image) {
            const iw = imgW * (wm.scale || 0.2);
            const ih = iw * (wm.image.height / wm.image.width);
            return ix >= cx - iw / 2 && ix <= cx + iw / 2
                && iy >= cy - ih / 2 && iy <= cy + ih / 2;
        }

        // Text watermark: estimate bounds from font size
        const fontSize = wm.fontSize || 48;
        const text = wm.content || 'Watermark';
        const estW = text.length * fontSize * 0.6;
        const estH = fontSize;
        return ix >= cx - estW / 2 && ix <= cx + estW / 2
            && iy >= cy - estH / 2 && iy <= cy + estH / 2;
    }

    onPointerDown(ix, iy) {
        const wm = this.editor.watermark;
        if (!wm) return;

        if (this._hitTest(wm, ix, iy)) {
            this._dragging = true;
            this._dragStart = { x: ix, y: iy };
            this._origPos = { x: wm.x, y: wm.y };
        }
    }

    onPointerMove(ix, iy) {
        // Cursor hint when hovering the watermark
        if (!this._dragging && this.editor.watermark) {
            if (this._hitTest(this.editor.watermark, ix, iy)) {
                this.editor.setCursor('move');
            }
        }

        if (!this._dragging || !this.editor.watermark) return;

        const imgW = this.editor.baseCanvas.width;
        const imgH = this.editor.baseCanvas.height;
        const dx = (ix - this._dragStart.x) / imgW;
        const dy = (iy - this._dragStart.y) / imgH;

        this.editor.watermark.x = Math.max(0, Math.min(1, this._origPos.x + dx));
        this.editor.watermark.y = Math.max(0, Math.min(1, this._origPos.y + dy));
        this.editor.requestRender();
    }

    onPointerUp() {
        this._dragging = false;
        this._dragStart = null;
        this._origPos = null;
    }

    onKeyDown() {}
    renderOverlay() {}

    renderOptions() {
        const el = document.createElement('div');
        el.className = 'maho-ie-options-group';
        const wm = this.editor.watermark;
        if (!wm) return el;

        // Type toggle
        const typeLabel = document.createElement('span');
        typeLabel.style.cssText = 'font-size:11px;color:#a1a1aa';
        typeLabel.textContent = 'Type:';
        el.appendChild(typeLabel);

        const textBtn = document.createElement('button');
        textBtn.className = 'maho-ie-opt-btn' + (wm.type === 'text' ? ' active' : '');
        textBtn.textContent = 'Text';
        textBtn.addEventListener('click', () => {
            this.editor.pushUndo();
            wm.type = 'text';
            this.editor.setOptions(this.renderOptions());
            this.editor.requestRender();
        });
        el.appendChild(textBtn);

        const imgBtn = document.createElement('button');
        imgBtn.className = 'maho-ie-opt-btn' + (wm.type === 'image' ? ' active' : '');
        imgBtn.textContent = 'Image';
        imgBtn.addEventListener('click', () => {
            this.editor.pushUndo();
            wm.type = 'image';
            this.editor.setOptions(this.renderOptions());
            this.editor.requestRender();
        });
        el.appendChild(imgBtn);

        // Divider
        el.appendChild(this._divider());

        if (wm.type === 'text') {
            // Text content
            const textGroup = document.createElement('div');
            textGroup.className = 'maho-ie-input';
            const textLabel = document.createElement('label');
            textLabel.textContent = 'Text';
            const textInput = document.createElement('input');
            textInput.type = 'text';
            textInput.value = wm.content || '';
            textInput.style.width = '140px';
            textInput.addEventListener('input', () => {
                wm.content = textInput.value;
                this.editor.requestRender();
            });
            textGroup.append(textLabel, textInput);
            el.appendChild(textGroup);

            // Font size
            const sizeGroup = document.createElement('div');
            sizeGroup.className = 'maho-ie-slider';
            const sizeLabel = document.createElement('label');
            sizeLabel.textContent = 'Size';
            const sizeVal = document.createElement('span');
            sizeVal.className = 'maho-ie-slider-value';
            sizeVal.textContent = wm.fontSize;
            const sizeInput = document.createElement('input');
            sizeInput.type = 'range';
            sizeInput.min = '12';
            sizeInput.max = '200';
            sizeInput.value = wm.fontSize;
            sizeInput.addEventListener('input', () => {
                wm.fontSize = parseInt(sizeInput.value);
                sizeVal.textContent = wm.fontSize;
                this.editor.requestRender();
            });
            sizeGroup.append(sizeLabel, sizeInput, sizeVal);
            el.appendChild(sizeGroup);

            // Color
            const colorGroup = document.createElement('div');
            colorGroup.className = 'maho-ie-color';
            const colorLabel = document.createElement('label');
            colorLabel.textContent = 'Color';
            const colorInput = document.createElement('input');
            colorInput.type = 'color';
            colorInput.value = wm.color || '#ffffff';
            colorInput.addEventListener('input', () => {
                wm.color = colorInput.value;
                this.editor.requestRender();
            });
            colorGroup.append(colorLabel, colorInput);
            el.appendChild(colorGroup);
        } else {
            // Image upload
            const uploadBtn = document.createElement('button');
            uploadBtn.className = 'maho-ie-opt-btn';
            uploadBtn.textContent = wm.image ? 'Change Image' : 'Upload Image';
            uploadBtn.addEventListener('click', () => {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/*';
                fileInput.addEventListener('change', () => {
                    const file = fileInput.files[0];
                    if (!file) return;
                    const img = new Image();
                    img.onload = () => {
                        URL.revokeObjectURL(img.src);
                        wm.image = img;
                        this.editor.requestRender();
                    };
                    img.src = URL.createObjectURL(file);
                });
                fileInput.click();
            });
            el.appendChild(uploadBtn);

            // Scale
            const scaleGroup = document.createElement('div');
            scaleGroup.className = 'maho-ie-slider';
            const scaleLabel = document.createElement('label');
            scaleLabel.textContent = 'Scale';
            const scaleVal = document.createElement('span');
            scaleVal.className = 'maho-ie-slider-value';
            scaleVal.textContent = Math.round(wm.scale * 100) + '%';
            const scaleInput = document.createElement('input');
            scaleInput.type = 'range';
            scaleInput.min = '5';
            scaleInput.max = '100';
            scaleInput.value = Math.round(wm.scale * 100);
            scaleInput.addEventListener('input', () => {
                wm.scale = parseInt(scaleInput.value) / 100;
                scaleVal.textContent = scaleInput.value + '%';
                this.editor.requestRender();
            });
            scaleGroup.append(scaleLabel, scaleInput, scaleVal);
            el.appendChild(scaleGroup);
        }

        // Opacity (common)
        el.appendChild(this._divider());
        const opGroup = document.createElement('div');
        opGroup.className = 'maho-ie-slider';
        const opLabel = document.createElement('label');
        opLabel.textContent = 'Opacity';
        const opVal = document.createElement('span');
        opVal.className = 'maho-ie-slider-value';
        opVal.textContent = Math.round(wm.opacity * 100) + '%';
        const opInput = document.createElement('input');
        opInput.type = 'range';
        opInput.min = '5';
        opInput.max = '100';
        opInput.value = Math.round(wm.opacity * 100);
        opInput.addEventListener('input', () => {
            wm.opacity = parseInt(opInput.value) / 100;
            opVal.textContent = opInput.value + '%';
            this.editor.requestRender();
        });
        opGroup.append(opLabel, opInput, opVal);
        el.appendChild(opGroup);

        // Position info
        const posInfo = document.createElement('span');
        posInfo.style.cssText = 'font-size:10px;color:#71717a';
        posInfo.textContent = 'Drag watermark on canvas to reposition';
        el.appendChild(posInfo);

        // Remove
        el.appendChild(this._divider());
        const removeBtn = document.createElement('button');
        removeBtn.className = 'maho-ie-opt-btn maho-ie-opt-btn-danger';
        removeBtn.textContent = 'Remove';
        removeBtn.addEventListener('click', () => {
            this.editor.pushUndo();
            this.editor.watermark = null;
            this.editor.setOptions(this.renderOptions());
            this.editor.requestRender();
        });
        el.appendChild(removeBtn);

        return el;
    }

    _divider() {
        const d = document.createElement('div');
        d.className = 'maho-ie-options-divider';
        return d;
    }

    destroy() {}
}
