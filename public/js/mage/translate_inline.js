/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class TranslateInline {

    constructor() {
        this.initialize(...arguments);
    }

    initialize(trigId, ajaxUrl, area) {
        this.trigEl = document.getElementById(trigId);
        this.ajaxUrl = ajaxUrl;
        this.area = area;

        this.trigTimer = null;
        this.trigContentEl = null;

        this.bindEventListeners();

        this.helperDiv = document.createElement('div');
    }

    bindEventListeners() {
        this.trigEl.addEventListener('click', this.formShow.bind(this));

        document.addEventListener('mousemove', (event) => {
            const target = event.target.closest('[data-translate]');
            if (target) {
                this.trigShow(target, event);
            } else if (event.target === this.trigEl) {
                this.trigHideClear();
            } else {
                this.trigHideDelayed();
            }
        });
    }

    initializeElement(el) {
        if (!el.initializedTranslate) {
            el.classList.add('translate-inline');
            el.initializedTranslate = true;
        }
    }

    reinitElements(el) {
        document.querySelectorAll('[data-translate]').forEach((el) => this.initializeElement(el));
    }

    trigShow(el, event) {
        if (this.trigContentEl === el) {
            return;
        }

        this.trigHideClear();
        this.trigContentEl = el;
        const pos = el.getBoundingClientRect();

        this.trigEl.style.left = pos.left + 'px';
        this.trigEl.style.top = pos.top + 'px';
        this.trigEl.style.display = 'block';

        event.preventDefault();
    }

    trigHide() {
        this.trigEl.style.display = 'none';
        this.trigContentEl = null;
    }

    trigHideDelayed() {
        this.trigTimer ??= window.setTimeout(this.trigHide.bind(this), 2000);
    }

    trigHideClear() {
        clearInterval(this.trigTimer);
        this.trigTimer = null;
    }

    formShow() {
        if (this.formIsShown) {
            return;
        }
        this.formIsShown = true;

        const el = this.trigContentEl;
        if (!el) {
            return;
        }

        this.trigHideClear();

        const t = new Template(`
            <div class="magento_table_container"><table cellspacing="0">
                <tr><th class="label">Location:</th><td class="value">#{location}</td></tr>
                <tr><th class="label">Scope:</th><td class="value">#{scope}</td></tr>
                <tr><th class="label">Shown:</th><td class="value">#{shown_escape}</td></tr>
                <tr><th class="label">Original:</th><td class="value">#{original_escape}</td></tr>
                <tr><th class="label">Translated:</th><td class="value">#{translated_escape}</td></tr>
                <tr><th class="label"><label for="perstore_#{i}">Store View Specific:</label></th><td class="value">
                    <input id="perstore_#{i}" name="translate[#{i}][perstore]" type="checkbox" value="1"/>
                </td></tr>
                <tr><th class="label"><label for="custom_#{i}">Custom:</label></th><td class="value">
                    <input name="translate[#{i}][original]" type="hidden" value="#{scope}::#{original_escape}"/>
                    <input id="custom_#{i}" name="translate[#{i}][custom]" class="input-text" value="#{translated_escape}" />
                </td></tr>
            </table></div>
        `);

        const fragments = JSON.parse(this.trigContentEl.dataset.translate).map((data, i) => {
            data['i'] = i;
            data['shown_escape'] = escapeHtml(data['shown'], true);
            data['translated_escape'] = escapeHtml(data['translated'], true);
            data['original_escape'] = escapeHtml(data['original'], true);
            return t.evaluate(data);
        });

        const content = `
            <form id="translate-inline-form">
                ${fragments.join('')}
            </form>
            <p class="a-center accent">Please refresh the page to see your changes after submitting this form.</p>
        `
        Dialog.confirm(content, {
            id: 'translate-inline',
            title: 'Translation',
            className: 'magento',
            width: 650,
            height: 470,
            okLabel: 'Submit',
            onOk: this.formOk.bind(this),
            onCancel: this.formClose.bind(this),
            onClose: this.formClose.bind(this)
        });

        this.trigHide();
    }

    async formOk(win) {
        if (this.formIsSubmitted) {
            return;
        }
        this.formIsSubmitted = true;
        try {
            const params = new FormData(document.getElementById('translate-inline-form'));
            params.set('area', this.area);

            await mahoFetch(this.ajaxUrl, {
                method: 'POST',
                body: params,
            });

            win.close();
            this.formClose(win);
        } catch (error) {

        }
        this.formIsSubmitted = false;
    }

    formClose() {
        this.formIsShown = false;
    }
};
