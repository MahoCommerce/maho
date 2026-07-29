// SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

// Keeps every protected form armed with a valid payload, so a form can be posted however and as
// often as its own module likes without knowing this one exists.
const MahoCaptcha = {
    loadingImageUrl: null,
    verifyingText: 'Verifying...',
    altchaWidget: null,
    altchaState: null,
    frontendSelectors: '',
    scriptsPromise: null,
    verificationPromise: null,
    resolveVerification: null,
    idleRearms: 0,
    loaderEl: null,
    loaderTimeoutId: null,

    async setup(config) {
        this.altchaWidget = document.querySelector('altcha-widget');
        this.frontendSelectors = config.frontendSelectors ?? '';
        this.loadingImageUrl = config.loadingImageUrl ?? '';
        this.verifyingText = config.verifyingText ?? 'Verifying...';

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', this.initForms.bind(this));
        } else {
            this.initForms();
        }

        this.altchaWidget.addEventListener('statechange', this.onStateChange.bind(this));
        this.altchaWidget.addEventListener('load', () => {
            this.onStateChange({ detail: { state: this.altchaWidget.getState() } });
            // Never let the widget's own auto mode do this: two solves in flight abort each other.
            this.startVerification();
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.altchaState === 'expired') {
                this.idleRearms = 0;
                this.startVerification();
            }
        });
    },

    getForms() {
        return this.frontendSelectors ? document.querySelectorAll(this.frontendSelectors) : [];
    },

    initForms() {
        for (const formEl of this.getForms()) {
            formEl.addEventListener('focusin', this.loadAltchaScripts.bind(this), { capture: true, once: true });
            formEl.addEventListener('submit', this.onFormSubmit.bind(this), true);
            for (const buttonEl of formEl.querySelectorAll('button[type=submit]')) {
                buttonEl.addEventListener('click', this.onFormButtonClick.bind(this), true);
            }
        }
    },

    // Memoized: several forms (and a submit) can race to load the script.
    loadAltchaScripts() {
        return this.scriptsPromise ??= this.fetchAltchaScripts();
    },

    async fetchAltchaScripts() {
        if (typeof customElements.get('altcha-widget') === 'function') return;

        await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '/js/altcha-i18n.min.js';
            script.type = 'module';
            script.onload = resolve;
            script.onerror = () => reject(`${script.src} Not Found`);
            document.head.appendChild(script);
        });

        const styleEl = document.createElement('style');
        styleEl.textContent = `
        altcha-widget {display: flex;position: fixed;bottom: 0;right: 0;z-index: 9999}
        dialog.maho-captcha-verifying {
            margin: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: none;
            border-radius: 0.5rem;
            body:has(&) {overflow: hidden}
            &::backdrop {background: rgba(0, 0, 0, 0.5)}
        }`;
        document.head.appendChild(styleEl);
    },

    async onFormSubmit(event) {
        if (this.altchaState === 'verified') {
            return;
        }
        const formEl = event.target;
        const submitter = event.submitter;

        event.preventDefault();
        event.stopPropagation();

        if (await this.verifyWithLoader()) {
            formEl.requestSubmit(submitter);
        }
    },

    async onFormButtonClick(event) {
        if (this.altchaState === 'verified') {
            return;
        }
        // Not target: a click landing on markup inside the button replays into nothing.
        const buttonEl = event.currentTarget;

        event.preventDefault();
        event.stopPropagation();

        if (await this.verifyWithLoader()) {
            buttonEl.dispatchEvent(new PointerEvent('click'));
        }
    },

    async verifyWithLoader() {
        this.idleRearms = 0;
        this.showLoader();
        try {
            await this.loadAltchaScripts();
            return await this.waitForVerification();
        } catch (error) {
            console.error('MahoCaptcha error:', error);
            return false;
        } finally {
            this.hideLoader();
        }
    },

    waitForVerification() {
        if (this.altchaState === 'verified') {
            return Promise.resolve(true);
        }
        // Held in a local: startVerification() can settle synchronously, clearing the field.
        const promise = this.verificationPromise ??= new Promise((resolve) => {
            this.resolveVerification = resolve;
        });
        this.startVerification();
        return promise;
    },

    settleVerification(verified) {
        const resolve = this.resolveVerification;
        this.verificationPromise = null;
        this.resolveVerification = null;
        if (resolve) {
            setTimeout(() => resolve(verified), 0);
        }
    },

    startVerification() {
        // Nothing to drive before the widget has loaded: its load handler starts the first one.
        if (this.altchaState === null || this.altchaState === 'verifying') {
            return;
        }
        // The widget only solves a new challenge from the unverified state.
        if (this.altchaState !== 'unverified') {
            this.altchaWidget.reset();
        }
        this.altchaWidget.verify();
    },

    onStateChange(event) {
        const { state, payload } = event.detail;
        this.altchaState = state;

        // Fix for error `An invalid form control with name='' is not focusable.`
        const checkbox = document.querySelector('#maho_captcha input[type=checkbox]');
        if (checkbox) {
            checkbox.disabled = state === 'verifying';
        }

        if (state === 'verified') {
            // The load event carries no payload of its own, so fall back to the widget's input.
            const value = typeof payload === 'string' ? payload : this.getWidgetPayload();
            for (const formEl of this.getForms()) {
                this.setHiddenInputValue(formEl, value);
            }
            this.settleVerification(value !== '');
        } else if (state === 'expired') {
            // Re-arm: an AJAX post fires no submit event for us to intercept. The spent payload
            // stays in the forms meanwhile, since removing it only breaks a post landing now.
            if (this.canRearm()) {
                this.startVerification();
            } else {
                this.settleVerification(false);
            }
        } else if (state === 'error') {
            // No re-arm: retrying a failing challenge endpoint would spin.
            this.settleVerification(false);
        }
    },

    getWidgetPayload() {
        return this.altchaWidget.querySelector('input[name="altcha"]')?.value ?? '';
    },

    // A challenge lives a minute: without a budget an idle tab would solve one forever.
    canRearm() {
        return !document.hidden && this.idleRearms++ < 5;
    },

    setHiddenInputValue(formEl, payload) {
        let hiddenInput = formEl.querySelector('input[name="maho_captcha"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.setAttribute('type', 'hidden');
            hiddenInput.setAttribute('name', 'maho_captcha');
            formEl.appendChild(hiddenInput);
        }
        hiddenInput.value = payload;
    },

    showLoader() {
        if (this.loaderEl || this.loaderTimeoutId) {
            return;
        }
        this.loaderTimeoutId = setTimeout(() => {
            this.loaderEl = document.createElement('dialog');
            this.loaderEl.className = 'maho-captcha-verifying';
            this.loaderEl.innerHTML = (this.loadingImageUrl ? '<img src="' + this.loadingImageUrl + '">' : '') + ' ' + this.verifyingText;
            this.loaderEl.addEventListener('close', () => {
                this.settleVerification(false);
                this.hideLoader();
            });
            document.body.appendChild(this.loaderEl);
            this.loaderEl.showModal();
        }, window.LOADING_TIMEOUT ?? 200);
    },

    hideLoader() {
        if (this.loaderEl) {
            this.loaderEl.remove();
            this.loaderEl = null;
        }
        if (this.loaderTimeoutId) {
            clearTimeout(this.loaderTimeoutId);
            this.loaderTimeoutId = null;
        }
    },
}
