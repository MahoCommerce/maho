// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

/**
 * Show a widget or block directive in the editor as the storefront shows it. The server
 * renders the directive in the theme of the store view, and a sandboxed frame shows the
 * result with the stylesheets of that theme. The frame runs no script.
 */

// A widget or a block shows in a frame. An icon is inline SVG, so it goes into the node itself
export const FRAMED_TYPES = ['widget', 'block'];
export const INLINE_TYPES = ['icon'];

// The server takes this many directives in one request
const BATCH_SIZE = 50;

const renderers = new WeakMap();

class DirectiveRenderer {
    constructor(setup) {
        this.url = setup.config.render_directives_url;
        this.storeId = setup.config.render_store_id;
        this.results = new Map();
        this.queue = new Map();
        this.timer = null;
    }

    /** A promise of { head, html }, or of null when the server does not render the directive. */
    render(directive) {
        if (!this.results.has(directive)) {
            this.results.set(directive, new Promise((resolve) => {
                this.queue.set(directive, resolve);
                this.timer ??= setTimeout(() => this.flush(), 30);
            }));
        }
        return this.results.get(directive);
    }

    async flush() {
        this.timer = null;
        const batch = [...this.queue];
        this.queue.clear();
        for (let i = 0; i < batch.length; i += BATCH_SIZE) {
            await this.send(batch.slice(i, i + BATCH_SIZE));
        }
    }

    async send(batch) {
        const body = new FormData();
        body.set('store_id', this.storeId);
        for (const [directive] of batch) {
            body.append('directives[]', directive);
        }
        let result = null;
        try {
            result = await mahoFetch(this.url, { method: 'POST', body, loaderArea: false });
        } catch (error) {
            console.error('Cannot render the widgets:', error);
        }
        for (const [directive, resolve] of batch) {
            const html = result?.html?.[directive];
            if (typeof html !== 'string') {
                // Ask again the next time that the node shows
                this.results.delete(directive);
            }
            resolve(typeof html === 'string' ? { head: result.head ?? '', html } : null);
        }
    }
}

const rendererOf = (setup) => {
    if (!setup?.config?.render_directives_url || !setup.config.render_store_id) {
        return null;
    }
    if (!renderers.has(setup)) {
        renderers.set(setup, new DirectiveRenderer(setup));
    }
    return renderers.get(setup);
};

// The sandbox blocks the scripts and the inline handlers anyway, but each one that it
// blocks logs an error in the console
const withoutScripts = (html) => {
    const template = document.createElement('template');
    template.innerHTML = html;
    for (const script of template.content.querySelectorAll('script')) {
        script.remove();
    }
    for (const element of template.content.querySelectorAll('*')) {
        for (const { name, value } of [...element.attributes]) {
            if (name.startsWith('on') || value.trim().toLowerCase().startsWith('javascript:')) {
                element.removeAttribute(name);
            }
        }
    }
    return template.innerHTML;
};

const frameDocument = (head, html) => `<!doctype html><html lang="${document.documentElement.lang || 'en'}"><head>`
    + `<meta charset="utf-8"><base target="_blank">${head}`
    + '<style>html,body{margin:0;overflow:hidden;min-height:0}</style>'
    + `</head><body><div class="std">${withoutScripts(html)}</div></body></html>`;

/**
 * Render the directive into the node view element `dom` when it comes into view. With
 * `inline`, the render replaces the label of the node, and it must be one SVG element.
 * Returns a function that stops the observers.
 */
export const mountDirectiveRender = (setup, dom, directive, { inline = false } = {}) => {
    const renderer = rendererOf(setup);
    if (!renderer) {
        return () => {};
    }

    let resizeObserver = null;
    let stopped = false;

    const showInline = (html) => {
        const template = document.createElement('template');
        template.innerHTML = withoutScripts(html.trim());
        const svg = template.content.firstElementChild;
        const label = dom.querySelector('.maho-widget-label');
        if (template.content.childElementCount !== 1 || svg?.localName !== 'svg' || !label) {
            return;
        }
        label.replaceChildren(svg);
        dom.classList.add('is-rendered-inline');
    };

    const show = (result) => {
        if (stopped || !result) {
            return;
        }
        if (inline) {
            showInline(result.html);
            return;
        }
        if (result.html.trim() === '') {
            dom.classList.add('is-render-empty');
            const note = document.createElement('span');
            note.className = 'maho-widget-note';
            note.textContent = setup.translate('Nothing to show in this store view.');
            dom.append(note);
            return;
        }

        const frame = document.createElement('iframe');
        frame.className = 'maho-widget-frame';
        frame.setAttribute('sandbox', 'allow-same-origin');
        frame.setAttribute('tabindex', '-1');
        frame.setAttribute('aria-hidden', 'true');
        frame.addEventListener('load', () => {
            const root = frame.contentDocument?.documentElement;
            if (!root) {
                return;
            }
            const fit = () => {
                frame.style.height = `${root.scrollHeight}px`;
            };
            fit();
            resizeObserver = new ResizeObserver(fit);
            resizeObserver.observe(frame.contentDocument.body);
            dom.classList.add('is-rendered');
        }, { once: true });
        frame.srcdoc = frameDocument(result.head, result.html);
        dom.append(frame);
    };

    const intersectionObserver = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            intersectionObserver.disconnect();
            renderer.render(directive).then(show);
        }
    }, { rootMargin: '200px' });
    intersectionObserver.observe(dom);

    return () => {
        stopped = true;
        intersectionObserver.disconnect();
        resizeObserver?.disconnect();
    };
};
