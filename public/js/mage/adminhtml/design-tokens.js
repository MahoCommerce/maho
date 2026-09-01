// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

window.MahoDesignTokens = (function () {
    const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

    function expandHex(hex) {
        const m = HEX.exec(hex.trim());
        if (!m) {
            return null;
        }
        const v = m[1];
        return '#' + (v.length === 3 ? v.split('').map((c) => c + c).join('') : v).toLowerCase();
    }

    function luminance(color) {
        const hex = expandHex(color);
        if (!hex) {
            return null;
        }
        const [r, g, b] = hex.slice(1).match(/../g).map((pair) => {
            const c = parseInt(pair, 16) / 255;
            return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }

    function contrast(a, b) {
        return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    }

    const INK_DARK = '#101418', INK_LIGHT = '#ffffff';

    function contentInk(color) {
        const l = luminance(color);
        if (l === null) {
            return null;
        }
        return contrast(l, luminance(INK_DARK)) >= contrast(l, luminance(INK_LIGHT)) ? INK_DARK : INK_LIGHT;
    }

    function linearRgb(L, C, h) {
        const a = C * Math.cos(h), b = C * Math.sin(h);
        const l = Math.pow(L + 0.3963377774 * a + 0.2158037573 * b, 3);
        const m = Math.pow(L - 0.1055613458 * a - 0.0638541728 * b, 3);
        const s = Math.pow(L - 0.0894841775 * a - 1.2914855480 * b, 3);
        return [
            4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
            -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
            -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
        ];
    }

    // daisyUI palettes sit outside sRGB. Lower the chroma until the color fits:
    // clamping each channel would swing the hue.
    function oklchToHex(value) {
        const m = /^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)/i.exec(value.trim());
        if (!m) {
            return null;
        }
        const L = m[2] === '%' ? parseFloat(m[1]) / 100 : parseFloat(m[1]);
        const h = parseFloat(m[4]) * Math.PI / 180;
        const fits = (rgb) => rgb.every((c) => c >= -0.0001 && c <= 1.0001);

        let hi = parseFloat(m[3]), lo = 0;
        if (!fits(linearRgb(L, hi, h))) {
            for (let i = 0; i < 20; i++) {
                const mid = (lo + hi) / 2;
                if (fits(linearRgb(L, mid, h))) {
                    lo = mid;
                } else {
                    hi = mid;
                }
            }
            hi = lo;
        }

        return '#' + linearRgb(L, hi, h).map((c) => {
            const v = c <= 0.0031308 ? 12.92 * c : 1.055 * Math.pow(Math.max(c, 0), 1 / 2.4) - 0.055;
            return Math.round(Math.min(1, Math.max(0, v)) * 255).toString(16).padStart(2, '0');
        }).join('');
    }

    function toHex(value) {
        return expandHex(value) || oklchToHex(value);
    }

    /** Every "--name: value" pair in a pasted block, whatever wraps it. */
    function parseDeclarations(css) {
        const found = {};
        for (const m of css.matchAll(/(--[a-z0-9-]+)\s*:\s*([^;}\n]+)/gi)) {
            found[m[1].toLowerCase()] = m[2].trim();
        }
        return found;
    }

    function inputFor(name) {
        return document.querySelector('[name=' + JSON.stringify(name) + ']');
    }

    /** Runs the callback once the document is parsed, or at once if it already is. */
    function whenParsed(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    /** Fire the events the admin form and our own listeners expect. */
    function setValue(input, value) {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function initContrast(opts) {
        const field = document.getElementById(opts.fieldId);
        const other = inputFor(opts.partner);
        const box = document.querySelector('.contrast-check[data-for=' + JSON.stringify(opts.fieldId) + ']');
        const badge = box && box.querySelector('.contrast-ratio');
        if (!field || !other || !badge) {
            return;
        }

        function update() {
            const a = luminance(field.value), b = luminance(other.value);
            // The theme's own value is not knowable here
            if (a === null || b === null) {
                box.hidden = true;
                return;
            }
            const ratio = contrast(a, b);
            const [state, note] = ratio >= 7 ? ['pass', opts.labels.aaa]
                : ratio >= 4.5 ? ['pass', opts.labels.aa]
                    : ratio >= 3 ? ['warn', opts.labels.aaLarge]
                        : ['fail', opts.labels.fail];
            badge.textContent = ratio.toFixed(1) + ':1 ' + note;
            badge.className = 'contrast-ratio contrast-' + state;
            box.hidden = false;
        }

        [field, other].forEach((input) => {
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        update();
    }

    function initImport(opts) {
        const box = document.getElementById(opts.id);
        if (!box) {
            return;
        }
        const status = box.querySelector('.token-import-status');
        const template = box.querySelector('template');

        // The banner is not a setting, so it takes the row instead of the value column.
        // This script runs mid-row, so the later cells exist only once parsing ends.
        whenParsed(function () {
            const cell = box.parentElement;
            const row = cell.parentElement;
            if (row.tagName !== 'TR') {
                return;
            }
            const span = row.cells.length;
            for (const other of [...row.cells]) {
                if (other !== cell) {
                    other.remove();
                }
            }
            cell.colSpan = span;
        });

        /** Writes every recognized variable into its field. */
        function fill(css) {
            const applied = [], skipped = [];
            for (const [variable, raw] of Object.entries(parseDeclarations(css))) {
                const input = opts.map[variable] ? inputFor(opts.map[variable]) : null;
                const value = opts.colors.includes(variable) ? toHex(raw) : raw;
                if (!input || value === null) {
                    skipped.push(variable);
                    continue;
                }
                setValue(input, value);
                applied.push(variable);
            }
            return { applied, skipped };
        }

        box.querySelector('button').addEventListener('click', () => {
            Dialog.confirm(template.innerHTML, {
                title: opts.labels.title,
                okLabel: opts.labels.apply,
                className: 'maho-dialog token-import-modal',
                width: 640,
                onOpen: (dialog) => dialog.querySelector('textarea').focus(),
                onOk: (dialog) => {
                    const { applied, skipped } = fill(dialog.querySelector('textarea').value);
                    const error = dialog.querySelector('.token-import-error');

                    // Returning false keeps the dialog open, so the text stays available
                    if (!applied.length) {
                        error.textContent = opts.labels.nothing;
                        error.hidden = false;
                        return false;
                    }

                    status.textContent = opts.labels.applied.replace('%1', applied.length)
                        + (skipped.length ? ' ' + opts.labels.skipped.replace('%1', skipped.length) : '');
                    status.hidden = false;
                },
            });
        });
    }

    /**
     * Put the palette inside the real options, so the closed control and the picker
     * share one source. Without browser support the plain select is untouched.
     */
    function initThemeSelect(opts) {
        const select = document.getElementById(opts.selectId);
        const packageSelect = document.getElementById(opts.packageId);
        if (!select || !CSS.supports('appearance', 'base-select')) {
            return;
        }
        select.classList.add('theme-select');

        // The package field empties and refills this select, so put the parts back
        const watcher = new MutationObserver(() => decorate());

        function decorate() {
            watcher.disconnect();

            if (!select.querySelector(':scope > button')) {
                const button = document.createElement('button');
                button.appendChild(document.createElement('selectedcontent'));
                select.prepend(button);
            }

            const themes = opts.palettes[packageSelect ? packageSelect.value : ''] || {};
            for (const option of select.options) {
                option.querySelector('.theme-swatch')?.remove();
                const colors = themes[option.value];
                if (!colors || !colors.length) {
                    continue;
                }
                const swatch = document.createElement('span');
                swatch.className = 'theme-swatch';
                colors.forEach((color) => {
                    const dot = document.createElement('i');
                    dot.style.background = color;
                    swatch.appendChild(dot);
                });
                option.prepend(swatch);
            }

            watcher.observe(select, { childList: true });
        }

        decorate();
    }

    /**
     * Pin the panel to the top right of its fieldset. Fixed, not sticky: the table
     * cell is only as tall as the panel, so sticky has nothing to slide against.
     */
    function floatPanel(panel) {
        const section = panel && panel.closest('fieldset');
        if (!section) {
            return;
        }
        panel.classList.add('is-floating');
        // Collapse the row in place. Moving the panel out would reload the iframe, and
        // hiding the row would hide the panel with it
        panel.closest('tr')?.classList.add('has-floating-preview');
        // The admin pins .content-header above this panel, so it sets the floor
        const header = document.querySelector('.content-header');

        // visibility, not the hidden attribute: display:none measures as zero high,
        // so the panel would look like it fits and flicker
        function place() {
            const box = section.getBoundingClientRect();
            const gap = 12;
            const height = panel.offsetHeight;
            const floor = header ? Math.max(gap, header.getBoundingClientRect().bottom + gap) : gap;

            const highest = Math.max(box.top, floor);
            const lowest = box.bottom - height - gap;

            panel.classList.toggle('is-offscreen', lowest < box.top || highest > lowest);
            if (panel.classList.contains('is-offscreen')) {
                return;
            }
            panel.style.left = Math.max(gap, box.right - panel.offsetWidth) + 'px';
            panel.style.top = Math.min(highest, lowest) + 'px';
        }

        let pending = false;
        function schedule() {
            if (!pending) {
                pending = true;
                requestAnimationFrame(() => {
                    pending = false;
                    place();
                });
            }
        }

        addEventListener('scroll', schedule, { passive: true });
        addEventListener('resize', schedule, { passive: true });
        // The section moves while the rest of the page lays out
        addEventListener('load', schedule);
        new ResizeObserver(schedule).observe(section);
        new ResizeObserver(schedule).observe(panel);
        schedule();
    }

    /**
     * Render at a real device width and scale to fit. At 480px the storefront would
     * always pick its phone layout.
     */
    function devicePicker(panel) {
        const bar = panel && panel.querySelector('.token-preview-devices');
        if (!bar) {
            return;
        }
        const store = 'maho-preview-width';

        function apply(width) {
            const available = panel.clientWidth;
            const scale = Math.min(1, available / width);
            panel.style.setProperty('--preview-render-width', width + 'px');
            panel.style.setProperty('--preview-scale', scale);
            panel.style.setProperty('--preview-offset', Math.max(0, (available - width * scale) / 2) + 'px');
            bar.querySelectorAll('button').forEach((b) => {
                b.classList.toggle('is-current', b.dataset.width === String(width));
            });
        }

        bar.addEventListener('click', function (event) {
            const button = event.target.closest('button');
            if (!button) {
                return;
            }
            apply(Number(button.dataset.width));
            try {
                localStorage.setItem(store, button.dataset.width);
            } catch (e) {
                // a browser that blocks storage still previews, it just forgets
            }
        });

        let chosen;
        try {
            chosen = localStorage.getItem(store);
        } catch (e) {
            chosen = null;
        }
        const current = () => Number(panel.style.getPropertyValue('--preview-render-width').replace('px', '')) || 1280;
        apply(Number(chosen) || 1280);
        // Measure again after the browser lays the panel out
        requestAnimationFrame(() => apply(current()));
        addEventListener('resize', () => apply(current()), { passive: true });
    }

    function initPreview(opts) {
        const frame = document.getElementById(opts.id);
        if (!frame) {
            return;
        }

        function css() {
            const vars = {};
            for (const entry of opts.tokens) {
                const input = inputFor(entry.name);
                const value = input && input.value.trim();
                if (!value) {
                    continue;
                }
                entry.vars.forEach((v) => {
                    vars[v] = value;
                });
                if (entry.derive === 'content') {
                    const ink = contentInk(value);
                    if (ink) {
                        vars[entry.vars[0] + '-content'] = ink;
                    }
                }
            }
            const surface = vars['--color-base-100'];
            const ink = vars['--color-base-content'] || (surface && contentInk(surface));
            if (surface && ink) {
                vars['--color-base-200'] = 'color-mix(in oklab, ' + surface + ', ' + ink + ' 4%)';
                vars['--color-base-300'] = 'color-mix(in oklab, ' + surface + ', ' + ink + ' 12%)';
            }
            const body = Object.entries(vars).map(([k, v]) => k + ':' + v + ';').join('');
            return body === '' ? '' : ':root{' + body + '}';
        }

        function styleElement(doc, id) {
            let style = doc.getElementById(id);
            if (!style) {
                style = doc.createElement('style');
                style.id = id;
                doc.head.appendChild(style);
            }
            return style;
        }

        // A web font needs a link element. Injecting it removes the need for a reload
        function paintFont(doc) {
            const input = inputFor(opts.fontUrl);
            let url = null;
            try {
                const parsed = new URL(input ? input.value.trim() : '');
                url = ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : null;
            } catch {
                url = null;
            }

            let link = doc.getElementById('preview-font');
            if (url === null) {
                link?.remove();
                return;
            }
            if (!link) {
                link = doc.createElement('link');
                link.id = 'preview-font';
                link.rel = 'stylesheet';
                doc.head.appendChild(link);
            }
            if (link.href !== url) {
                link.href = url;
            }
        }

        function paint() {
            let doc;
            try {
                doc = frame.contentDocument;
            } catch (e) {
                return; // a separate admin domain: the preview only updates on reload
            }
            if (!doc || !doc.documentElement) {
                return;
            }
            // Development chrome opts out with the attribute, so the admin needs no
            // knowledge of its markup
            styleElement(doc, 'preview-chrome').textContent = '[data-preview-hide]{display:none !important}';
            styleElement(doc, 'design-tokens').textContent = css();
            paintFont(doc);
        }

        const panel = frame.closest('.token-preview');
        floatPanel(panel);
        devicePicker(panel);
        frame.addEventListener('load', paint);
        document.addEventListener('input', function (event) {
            if (event.target.name && event.target.name.includes('[fields]')) {
                paint();
            }
        });
    }

    return { luminance, contrast, contentInk, oklchToHex, toHex, parseDeclarations, initContrast, initImport, initThemeSelect, initPreview };
})();
