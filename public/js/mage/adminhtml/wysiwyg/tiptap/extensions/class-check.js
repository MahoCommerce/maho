// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

/**
 * Find the classes that the storefront does not style. A hidden frame loads the stylesheets
 * of the store view and the Tailwind browser build of its theme. The browser build writes a
 * rule for each utility class of the frame that it knows.
 */

// The browser build needs some time for a new class. A class with no rule after this time is unknown.
const WAIT = 600;
const POLL = 50;

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

const withoutQuery = (url) => url.split(/[?#]/)[0];

/**
 * The classes of the <link> stylesheets in `doc`, or null when `stylesUrl`, the stylesheet of the
 * theme, hides its rules. A stylesheet of another origin hides its rules, so a font stylesheet
 * on a CDN is left out. Without the stylesheet of the theme, each of its classes looks unknown.
 */
const linkedClasses = (doc, stylesUrl) => {
    const found = new Set();
    for (const sheet of doc.styleSheets) {
        if (sheet.ownerNode?.localName !== 'link') {
            continue;
        }
        try {
            collectClasses(sheet.cssRules, found);
        } catch {
            if (withoutQuery(sheet.href ?? '') === withoutQuery(stylesUrl)) {
                return null;
            }
        }
    }
    return found;
};

const checks = new WeakMap();

class ClassCheck {
    constructor(setup) {
        this.setup = setup;
        this.storeId = null;
        this.frame = null;
        this.ready = null;
        this.linked = null;
    }

    /** A promise of the document of the frame, or of null when the check cannot run. */
    load() {
        const storeId = this.setup.config.render_store_id;
        if (storeId !== this.storeId) {
            this.frame?.remove();
            this.frame = null;
            this.storeId = storeId;
            this.ready = this.open(storeId);
        }
        return this.ready;
    }

    async open(storeId) {
        let styles = null;
        try {
            const body = new FormData();
            body.set('store_id', storeId);
            styles = await mahoFetch(this.setup.config.class_check_url, { method: 'POST', body, loaderArea: false });
        } catch (error) {
            console.error('Cannot load the stylesheets of the store view:', error);
        }
        // Without the browser build, every utility that the content adds looks unknown
        if (!styles?.tailwind || storeId !== this.storeId) {
            return null;
        }
        const frame = document.createElement('iframe');
        frame.hidden = true;
        frame.srcdoc = `<!doctype html><html><head><meta charset="utf-8">${styles.head ?? ''}${styles.tailwind}</head><body><div id="probe"></div></body></html>`;
        const loaded = new Promise((resolve) => frame.addEventListener('load', resolve, { once: true }));
        document.body.append(frame);
        this.frame = frame;
        await loaded;
        this.linked = linkedClasses(frame.contentDocument, styles.styles ?? '');
        return this.linked ? frame.contentDocument : null;
    }

    /** The classes of the <link> stylesheets, and of the <style> elements of the browser build. */
    styledClasses(doc) {
        return [...doc.styleSheets]
            .filter((sheet) => sheet.ownerNode?.localName === 'style')
            .reduce((found, sheet) => collectClasses(sheet.cssRules, found), new Set(this.linked));
    }

    async findUnknown(classes) {
        const doc = await this.load();
        if (!doc) {
            return null;
        }
        doc.getElementById('probe').className = classes.join(' ');
        const deadline = performance.now() + WAIT;
        let unknown = classes;
        while (unknown.length > 0 && performance.now() < deadline) {
            await new Promise((resolve) => setTimeout(resolve, POLL));
            const styled = this.styledClasses(doc);
            unknown = classes.filter((name) => !styled.has(CSS.escape(name)));
        }
        return unknown;
    }
}

/**
 * A promise of the classes of `classes` that the storefront of the editor does not style, or of
 * null when the editor cannot check them.
 */
export const findUnknownClasses = async (setup, classes) => {
    if (classes.length === 0 || !setup?.config?.class_check_url || !setup.config.render_store_id) {
        return [];
    }
    if (!checks.has(setup)) {
        checks.set(setup, new ClassCheck(setup));
    }
    return checks.get(setup).findUnknown(classes);
};
