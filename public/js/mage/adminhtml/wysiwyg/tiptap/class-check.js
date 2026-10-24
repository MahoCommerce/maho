// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

/**
 * The classes that the storefront of a store view styles, for the plain HTML editor. Two sources
 * answer: the stylesheets of the store view, for the component and daisyUI classes of the theme,
 * and the Tailwind design system of the theme, for every utility that a content build compiles.
 */

/** An escaped class name in a selector, as CSS.escape() writes it. */
const SELECTOR_CLASS = /\.((?:\\[0-9a-fA-F]{1,6} ?|\\[^0-9a-fA-F\n]|[\w-]|[^\x00-\x7F])+)/g;

const collectClasses = (rules, found) => {
    for (const rule of rules) {
        for (const match of rule.selectorText?.matchAll(SELECTOR_CLASS) ?? []) {
            found.add(match[1]);
        }
        if (rule.cssRules) {
            collectClasses(rule.cssRules, found);
        }
    }
    return found;
};

/** Read a class name that CSS.escape() wrote. */
const unescapeClass = (name) => name.replace(/\\([0-9a-fA-F]{1,6}) ?|\\(.)/g, (match, hex, char) => (hex ? String.fromCodePoint(parseInt(hex, 16)) : char));

const withoutQuery = (url) => url.split(/[?#]/)[0];

/**
 * The class names of the stylesheets in `doc`, or null when `stylesUrl`, the stylesheet of the
 * theme, hides its rules. A stylesheet of another origin hides its rules, so a font stylesheet
 * on a CDN is left out. Without the stylesheet of the theme, each of its classes looks unknown.
 */
const linkedClasses = (doc, stylesUrl) => {
    const found = new Set();
    for (const sheet of doc.styleSheets) {
        try {
            collectClasses(sheet.cssRules, found);
        } catch {
            if (withoutQuery(sheet.href ?? '') === withoutQuery(stylesUrl)) {
                return null;
            }
        }
    }
    return new Set([...found].map(unescapeClass));
};

/** A hidden frame parses the stylesheets of `head`, so they do not change the admin page. */
const readStylesheets = async (head, stylesUrl) => {
    const frame = document.createElement('iframe');
    frame.hidden = true;
    frame.srcdoc = `<!doctype html><html><head><meta charset="utf-8">${head}</head><body></body></html>`;
    const loaded = new Promise((resolve) => frame.addEventListener('load', resolve, { once: true }));
    document.body.append(frame);
    try {
        await loaded;
        return linkedClasses(frame.contentDocument, stylesUrl);
    } finally {
        frame.remove();
    }
};

const loadDesignSystem = async (css) => {
    if (!css) {
        return null;
    }
    const { loadDesignSystem: load } = await import('../../../../tailwindcss-design.js');
    return load(css);
};

/** The names that complete a variant, such as `md:` or `aria-checked:`. */
const variantNames = (designSystem) => designSystem.getVariants().flatMap((variant) => [
    ...(variant.isArbitrary && variant.values.length > 0 ? [] : [`${variant.name}:`]),
    ...variant.values.map((value) => `${variant.name}-${value}:`),
]);

class ClassCheck {
    constructor(setup) {
        this.setup = setup;
        this.storeId = null;
        this.ready = null;
    }

    /** A promise of { styled, designSystem } for the store view of the editor, or of null. */
    load() {
        const storeId = this.setup.config.render_store_id;
        if (storeId !== this.storeId) {
            this.storeId = storeId;
            this.ready = this.open(storeId);
        }
        return this.ready;
    }

    async open(storeId) {
        try {
            const body = new FormData();
            body.set('store_id', storeId);
            const styles = await mahoFetch(this.setup.config.class_check_url, { method: 'POST', body, loaderArea: false });
            const [styled, designSystem] = await Promise.all([
                readStylesheets(styles.head ?? '', styles.styles ?? ''),
                loadDesignSystem(styles.css ?? ''),
            ]);
            return styled ? { styled, designSystem } : null;
        } catch (error) {
            console.error('Cannot read the classes of the store view:', error);
            return null;
        }
    }

    async findUnknown(classes) {
        const sources = await this.load();
        if (!sources) {
            return null;
        }
        const rest = classes.filter((name) => !sources.styled.has(name));
        const css = sources.designSystem?.candidatesToCss(rest) ?? rest.map(() => null);
        return rest.filter((name, index) => css[index] === null);
    }

    async listClasses() {
        const sources = await this.load();
        if (!sources) {
            return null;
        }
        sources.names ??= {
            classes: [...new Set([...sources.styled, ...(sources.designSystem?.getClassList().map(([name]) => name) ?? [])])].sort(),
            variants: sources.designSystem ? variantNames(sources.designSystem) : [],
        };
        return sources.names;
    }
}

const checks = new WeakMap();

/** The check of the editor of `setup`, or null when the editor has no store view to check against. */
const checkOf = (setup) => {
    if (!setup?.config?.class_check_url || !setup.config.render_store_id) {
        return null;
    }
    if (!checks.has(setup)) {
        checks.set(setup, new ClassCheck(setup));
    }
    return checks.get(setup);
};

/**
 * A promise of the classes of `classes` that the storefront of the editor does not style, or of
 * null when the editor cannot check them.
 */
export const findUnknownClasses = async (setup, classes) => {
    const check = checkOf(setup);
    if (classes.length === 0 || !check) {
        return check ? [] : null;
    }
    return check.findUnknown(classes);
};

/**
 * A promise of { classes, variants }: the class names that the storefront of the editor styles,
 * and the Tailwind variants such as `md:`, or of null.
 */
export const listClasses = async (setup) => checkOf(setup)?.listClasses() ?? null;
