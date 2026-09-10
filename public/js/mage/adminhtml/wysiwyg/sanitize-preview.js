// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

/**
 * Warn about the HTML that a save removes from this field. The author can still change it.
 *
 * PHP decides what to remove, so the browser must ask the server. This class attaches to a
 * plain textarea. It needs no rich text editor, so it also covers the product description.
 */
class mahoSanitizePreview {

    constructor(htmlId, url) {
        this.textarea = document.getElementById(htmlId);
        this.url = url;
        this.removed = null;
        this.notice = null;

        if (!this.textarea || !this.url) {
            return;
        }

        // The rich text editor writes the textarea directly, which fires `change`
        this.checkDebounced = debounce(this.check.bind(this), 800);
        this.textarea.addEventListener('input', this.checkDebounced);
        this.textarea.addEventListener('change', this.checkDebounced);

        this.check();
    }

    destroy() {
        this.textarea?.removeEventListener('input', this.checkDebounced);
        this.textarea?.removeEventListener('change', this.checkDebounced);
        this.notice?.remove();
        this.notice = null;
    }

    /** A failed request blocks nothing. It only hides the warning. */
    async check() {
        if (!this.textarea || !this.url) {
            return;
        }

        const html = this.textarea.value;
        if (html.trim() === '') {
            this.removed = null;
            this.render();
            return;
        }

        try {
            const result = await mahoFetch(this.url, {
                method: 'POST',
                body: new URLSearchParams({ html }),
                loaderArea: false,
            });
            // A later edit replaced the content of this answer
            if (this.textarea.value !== html) {
                return;
            }
            this.removed = result.removed?.length > 0 ? result.removed : null;
        } catch (error) {
            this.removed = null;
        }

        this.render();
    }

    render() {
        if (this.removed === null) {
            this.notice?.remove();
            this.notice = null;
            return;
        }

        this.notice ??= document.createElement('div');
        this.notice.className = 'notice-msg sanitize-preview-notice';
        this.notice.textContent = this.translate(
            'Saving removes this HTML: %s. A content field allows only standard HTML elements.',
            this.describe(this.removed),
        );
        this.textarea.before(this.notice);
    }

    describe(removed, limit = 6) {
        if (removed.length <= limit) {
            return removed.join(', ');
        }
        return removed.slice(0, limit).join(', ')
            + ' ' + this.translate('and %s more', String(removed.length - limit));
    }

    translate(string, ...args) {
        return typeof Translator !== 'undefined' ? Translator.translate(string, ...args) : string;
    }
}

window.mahoSanitizePreview = mahoSanitizePreview;
