// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { setRenderStore } from './extensions/widget-render.js';

const DEVICES = [['device-mobile', 'Mobile', 390], ['device-tablet', 'Tablet', 768], ['device-desktop', 'Desktop', 1280]];
const MIN_DELAY = 500;
const MESSAGE_SOURCE = 'maho-cms-preview';

/**
 * The Preview pane of the workspace. It posts the edit form to the admin, which
 * keeps a sanitized draft, then shows the storefront page of that draft.
 *
 * The next page loads in a hidden frame and replaces the visible one when it is
 * ready, so the pane never shows a blank page. One request runs at a time, and only
 * the latest content goes to the server.
 */
export class MahoPreviewPane {
    constructor(workspace) {
        this.workspace = workspace;
        this.setup = workspace.setup;
        this.editor = workspace.editor;
        this.config = this.setup.config.preview;
        this.form = this.setup.textarea.form;

        this.draftId = null;
        this.url = null;
        this.storeId = this.config.storeId;
        this.contextId = '';
        this.device = workspace.state.device ?? 'device-desktop';
        this.sync = workspace.state.sync ?? true;
        this.visible = false;
        this.inFlight = false;
        this.pending = false;
        this.timer = null;
        this.lastDuration = 0;
        this.scrollRatio = 0;
        this.syncLock = 0;
        this.popout = null;
        this.loads = 0;
        this.keepAlive = null;

        this.build();
        this.onChange = (event) => (event?.target && this.setup.wrapper.contains(event.target) ? null : this.schedule());
        this.onMessage = this.handleMessage.bind(this);
        this.onVisibility = () => document.hidden || !this.pending || this.schedule();
        this.onEditorScroll = this.handleEditorScroll.bind(this);
    }

    build() {
        const t = (text) => this.setup.translate(text);

        this.splitter = document.createElement('div');
        this.splitter.className = 'tiptap-preview-splitter';
        this.splitter.tabIndex = 0;
        this.splitter.setAttribute('role', 'separator');
        this.splitter.setAttribute('aria-orientation', 'vertical');
        this.splitter.setAttribute('aria-label', t('Resize the preview'));

        this.pane = document.createElement('aside');
        this.pane.className = 'tiptap-workspace-pane tiptap-preview-pane';
        this.pane.setAttribute('aria-label', t('Preview'));

        const bar = document.createElement('div');
        bar.className = 'tiptap-preview-bar';

        this.storeSelect = this.select(t('Store View'), this.storeOptions(), String(this.storeId));
        this.storeSelect.addEventListener('change', () => {
            this.storeId = Number(this.storeSelect.value);
            setRenderStore(this.setup, this.storeId);
            this.fillContextSelect();
            this.refresh();
        });
        bar.append(this.storeSelect);

        if (this.config.entity === 'block') {
            this.contextSelect = this.select(t('Show on'), [], '');
            this.contextSelect.addEventListener('change', () => {
                this.contextId = this.contextSelect.value;
                this.refresh();
            });
            this.fillContextSelect();
            bar.append(this.contextSelect);
        }

        const devices = document.createElement('div');
        devices.className = 'tiptap-preview-devices';
        this.deviceButtons = new Map();
        for (const [icon, label, width] of DEVICES) {
            const button = this.button(icon, `${t(label)} (${width}px)`, () => {
                this.device = icon;
                this.workspace.saveState({ device: icon });
                this.layout();
            });
            this.deviceButtons.set(icon, button);
            devices.append(button);
        }
        bar.append(devices);

        this.syncButton = this.button('scroll-sync', t('Sync scroll'), () => {
            this.sync = !this.sync;
            this.workspace.saveState({ sync: this.sync });
            this.paintButtons();
        });
        this.reloadButton = this.button('reload', t('Reload'), () => this.refresh());
        this.popoutButton = this.button('external-link', t('Open in a new window'), () => this.openPopout());
        bar.append(this.syncButton, this.reloadButton, this.popoutButton);

        this.status = document.createElement('div');
        this.status.className = 'tiptap-preview-status';
        this.status.setAttribute('role', 'status');
        this.status.hidden = true;

        this.stage = document.createElement('div');
        this.stage.className = 'tiptap-preview-stage';
        this.frames = [this.frame(), this.frame()];
        this.frames[0].classList.add('is-current');
        this.stage.append(...this.frames);

        this.pane.append(bar, this.status, this.stage);
        this.bindSplitter();
        this.paintButtons();
    }

    /** The pages of the current store view that show the block. Another page falls back to the block only. */
    fillContextSelect() {
        if (!this.contextSelect) {
            return;
        }
        const pages = (this.config.usages ?? []).filter((page) => page.stores.includes(this.storeId));
        if (!pages.some((page) => String(page.id) === this.contextId)) {
            this.contextId = '';
        }
        const options = [{ value: '', label: this.setup.translate('Block only') },
            ...pages.map((page) => ({ value: String(page.id), label: page.title }))];
        this.contextSelect.replaceChildren(...this.select('', options, this.contextId).children);
        this.contextSelect.value = this.contextId;
    }

    storeOptions() {
        const groups = new Map();
        for (const store of this.config.stores ?? []) {
            if (!groups.has(store.website)) {
                groups.set(store.website, []);
            }
            groups.get(store.website).push({ value: String(store.id), label: store.name });
        }
        return [...groups].map(([label, options]) => ({ label, options }));
    }

    select(label, options, current) {
        const select = document.createElement('select');
        select.title = label;
        select.setAttribute('aria-label', label);
        const add = (parent, option) => {
            const element = new Option(option.label, option.value, false, option.value === current);
            parent.append(element);
        };
        for (const option of options) {
            if (option.options) {
                const group = document.createElement('optgroup');
                group.label = option.label;
                option.options.forEach((item) => add(group, item));
                select.append(group);
            } else {
                add(select, option);
            }
        }
        return select;
    }

    button(icon, label, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'tiptap-preview-action';
        button.title = label;
        button.setAttribute('aria-label', label);
        button.innerHTML = this.setup.getIcon(icon);
        button.addEventListener('click', onClick);
        return button;
    }

    frame() {
        const frame = document.createElement('iframe');
        frame.className = 'tiptap-preview-frame';
        frame.title = this.setup.translate('Preview');
        frame.addEventListener('load', () => this.handleLoad(frame));
        return frame;
    }

    paintButtons() {
        for (const [icon, button] of this.deviceButtons) {
            this.workspace.setPressed(button, icon === this.device);
        }
        this.workspace.setPressed(this.syncButton, this.sync);
    }

    show() {
        if (this.visible) {
            return;
        }
        this.visible = true;
        this.setup.wrapper.append(this.splitter, this.pane);
        this.setup.wrapper.style.setProperty('--tt-preview-size', `${this.workspace.state.previewWidth ?? 45}%`);
        this.editor.on('update', this.onChange);
        this.form?.addEventListener('input', this.onChange);
        this.form?.addEventListener('change', this.onChange);
        window.addEventListener('message', this.onMessage);
        document.addEventListener('visibilitychange', this.onVisibility);
        this.scroller()?.addEventListener('scroll', this.onEditorScroll, { passive: true });
        // The server forgets a draft after its lifetime, and the frame or the window of the
        // preview would then show a 404 on a reload. A third of the lifetime leaves time for a retry
        this.keepAlive = window.setInterval(() => this.send({ reload: false }), (this.config.lifetime ?? 1800) * 1000 / 3);
        this.layout();
        this.refresh();
    }

    hide() {
        if (!this.visible) {
            return;
        }
        this.visible = false;
        window.clearTimeout(this.timer);
        window.clearInterval(this.keepAlive);
        this.keepAlive = null;
        this.editor.off('update', this.onChange);
        this.form?.removeEventListener('input', this.onChange);
        this.form?.removeEventListener('change', this.onChange);
        window.removeEventListener('message', this.onMessage);
        document.removeEventListener('visibilitychange', this.onVisibility);
        this.scroller()?.removeEventListener('scroll', this.onEditorScroll);
        this.splitter.remove();
        this.pane.remove();
        this.setup.wrapper.style.removeProperty('--tt-preview-size');
    }

    destroy() {
        this.hide();
        this.popout = null;
    }

    /** Scale the frame to the device width and fit it into the pane. */
    layout() {
        if (!this.visible) {
            return;
        }
        const width = DEVICES.find(([icon]) => icon === this.device)?.[2] ?? 1280;
        const available = Math.max(160, this.stage.clientWidth - 16);
        const scale = Math.min(1, available / width);
        const height = Math.max(240, this.stage.clientHeight - 16) / scale;
        for (const frame of this.frames) {
            frame.style.width = `${width}px`;
            frame.style.height = `${Math.round(height)}px`;
            frame.style.transform = `translateX(-50%) scale(${scale.toFixed(3)})`;
        }
        this.paintButtons();
    }

    /** Send the form now: a store or a page change, or the reload button. */
    refresh() {
        window.clearTimeout(this.timer);
        this.timer = null;
        this.send();
    }

    schedule() {
        if (!this.visible) {
            return;
        }
        if (this.inFlight || document.hidden) {
            this.pending = true;
            return;
        }
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => this.send(), Math.max(MIN_DELAY, this.lastDuration));
    }

    /** With `reload` false, the frames reload only when the server gives a new draft. */
    async send({ reload = true } = {}) {
        if (!this.visible || this.inFlight) {
            this.pending ||= reload;
            return;
        }
        this.inFlight = true;
        this.pending = false;
        this.started = performance.now();
        this.reloadButton.classList.add('is-busy');

        try {
            const result = await mahoFetch(this.config.url, {
                method: 'POST',
                body: this.formData(),
                loaderArea: false,
            });
            const sameDraft = result.url === this.url;
            this.draftId = result.draft;
            this.url = result.url;
            this.showStatus(result.warnings ?? []);
            if (!reload && sameDraft) {
                this.finish();
                return;
            }
            this.loads += 1;
            const next = this.frames.find((frame) => !frame.classList.contains('is-current'));
            next.src = `${this.url}${this.url.includes('?') ? '&' : '?'}_=${this.loads}`;
            if (this.popout && !this.popout.closed) {
                this.popout.location.replace(this.url);
            }
        } catch (error) {
            this.showStatus([error?.message || this.setup.translate('The preview could not be loaded.')], true);
            this.finish();
        }
    }

    /** End the request, and send the change that came while it ran. */
    finish() {
        this.inFlight = false;
        this.reloadButton.classList.remove('is-busy');
        if (this.pending) {
            this.pending = false;
            this.schedule();
        }
    }

    formData() {
        const data = this.form ? new FormData(this.form) : new FormData();
        for (const [name, value] of [...data]) {
            if (value instanceof File) {
                data.delete(name);
            }
        }
        data.set(this.setup.textarea.name, this.setup.convertToPlain(this.editor.getHTML()));
        data.set('preview_entity', this.config.entity);
        data.set('preview_store', String(this.storeId));
        data.set('preview_context', this.contextId);
        if (this.draftId) {
            data.set('preview_draft', this.draftId);
        }
        return data;
    }

    handleLoad(frame) {
        if (!frame.src || frame.classList.contains('is-current')) {
            return;
        }
        for (const other of this.frames) {
            other.classList.toggle('is-current', other === frame);
        }
        this.post(frame, { type: 'scrollTo', ratio: this.scrollRatio });
        this.lastDuration = performance.now() - this.started;
        this.finish();
    }

    showStatus(messages, isError = false) {
        this.status.textContent = messages.join(' ');
        this.status.hidden = messages.length === 0;
        this.status.classList.toggle('is-error', isError);
    }

    current() {
        return this.frames.find((frame) => frame.classList.contains('is-current'));
    }

    targetOrigin() {
        try {
            return new URL(this.url).origin;
        } catch {
            return null;
        }
    }

    post(frame, message) {
        const origin = this.targetOrigin();
        if (origin) {
            frame.contentWindow?.postMessage({ source: MESSAGE_SOURCE, ...message }, origin);
        }
    }

    /**
     * The storefront page reports its scroll position through postMessage, so the sync
     * also works when the admin and the store have different origins.
     */
    handleMessage(event) {
        const data = event.data;
        if (event.origin !== this.targetOrigin() || data?.source !== MESSAGE_SOURCE
            || event.source !== this.current()?.contentWindow) {
            return;
        }
        if (data.type === 'scroll' && typeof data.ratio === 'number') {
            this.scrollRatio = data.ratio;
            const scroller = this.scroller();
            if (this.sync && scroller && performance.now() > this.syncLock) {
                this.syncLock = performance.now() + 150;
                scroller.scrollTop = data.ratio * Math.max(0, scroller.scrollHeight - scroller.clientHeight);
            }
        }
    }

    handleEditorScroll() {
        if (!this.sync || performance.now() < this.syncLock) {
            return;
        }
        const scroller = this.scroller();
        const max = scroller.scrollHeight - scroller.clientHeight;
        this.scrollRatio = max > 0 ? scroller.scrollTop / max : 0;
        this.syncLock = performance.now() + 150;
        this.post(this.current(), { type: 'scrollTo', ratio: this.scrollRatio });
    }

    /** The element that scrolls the content of the editor. */
    scroller() {
        let element = this.editor.view.dom;
        while (element && element !== this.setup.wrapper) {
            const overflow = getComputedStyle(element).overflowY;
            if (overflow === 'auto' || overflow === 'scroll') {
                return element;
            }
            element = element.parentElement;
        }
        return null;
    }

    openPopout() {
        if (!this.url) {
            return;
        }
        this.popout = window.open(this.url, `tiptap-preview-${this.draftId}`);
    }

    bindSplitter() {
        const wrapper = this.setup.wrapper;
        const setWidth = (percent) => {
            const width = Math.min(70, Math.max(20, percent));
            wrapper.style.setProperty('--tt-preview-size', `${width.toFixed(1)}%`);
            this.workspace.saveState({ previewWidth: Number(width.toFixed(1)) });
            this.layout();
        };
        let dragging = false;
        this.splitter.addEventListener('pointerdown', (event) => {
            dragging = true;
            this.splitter.setPointerCapture(event.pointerId);
            // The frame would take the pointer events while the splitter moves over it
            this.stage.classList.add('is-resizing');
            event.preventDefault();
        });
        this.splitter.addEventListener('pointermove', (event) => {
            if (dragging) {
                const rect = wrapper.getBoundingClientRect();
                setWidth(((rect.right - event.clientX) / rect.width) * 100);
            }
        });
        const stop = (event) => {
            dragging = false;
            this.stage.classList.remove('is-resizing');
            if (this.splitter.hasPointerCapture(event.pointerId)) {
                this.splitter.releasePointerCapture(event.pointerId);
            }
        };
        this.splitter.addEventListener('pointerup', stop);
        this.splitter.addEventListener('pointercancel', stop);
        this.splitter.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                const current = this.workspace.state.previewWidth ?? 45;
                setWidth(current + (event.key === 'ArrowLeft' ? 2 : -2));
            }
        });
    }
}
