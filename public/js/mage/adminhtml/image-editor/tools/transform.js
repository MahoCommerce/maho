/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

export class TransformTool {
    constructor(editor) {
        this.editor = editor;
    }

    get name() { return 'transform'; }
    get label() { return 'Transform'; }
    get icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9"/><polyline points="21 3 21 9 15 9"/></svg>';
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

        const actions = [
            { label: 'Rotate Left', icon: this._rotateCCWIcon(), fn: () => this._rotate(-90) },
            { label: 'Rotate Right', icon: this._rotateCWIcon(), fn: () => this._rotate(90) },
            { label: 'Flip Horizontal', icon: this._flipHIcon(), fn: () => this._flip('h') },
            { label: 'Flip Vertical', icon: this._flipVIcon(), fn: () => this._flip('v') },
        ];

        for (const action of actions) {
            const btn = document.createElement('button');
            btn.className = 'maho-ie-opt-btn';
            btn.innerHTML = action.icon + ' ' + action.label;
            btn.addEventListener('click', action.fn);
            el.appendChild(btn);
        }

        return el;
    }

    _rotate(degrees) {
        this.editor.pushUndo();
        const src = this.editor.baseCanvas;
        const w = src.width;
        const h = src.height;
        const isRightAngle = Math.abs(degrees) === 90 || Math.abs(degrees) === 270;

        const canvas = document.createElement('canvas');
        canvas.width = isRightAngle ? h : w;
        canvas.height = isRightAngle ? w : h;
        const ctx = canvas.getContext('2d');

        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate((degrees * Math.PI) / 180);
        ctx.drawImage(src, -w / 2, -h / 2);

        this.editor.replaceBase(canvas);
    }

    _flip(axis) {
        this.editor.pushUndo();
        const src = this.editor.baseCanvas;
        const canvas = document.createElement('canvas');
        canvas.width = src.width;
        canvas.height = src.height;
        const ctx = canvas.getContext('2d');

        if (axis === 'h') {
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
        } else {
            ctx.translate(0, canvas.height);
            ctx.scale(1, -1);
        }
        ctx.drawImage(src, 0, 0);

        this.editor.replaceBase(canvas);
    }

    _rotateCCWIcon() {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9"/><polyline points="3 3 3 9 9 9"/></svg>';
    }

    _rotateCWIcon() {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9"/><polyline points="21 3 21 9 15 9"/></svg>';
    }

    _flipHIcon() {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M16 7l4 5-4 5M8 7L4 12l4 5"/></svg>';
    }

    _flipVIcon() {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M7 8L12 4l5 4M7 16l5 4 5-4"/></svg>';
    }

    destroy() {}
}
