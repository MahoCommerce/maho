// SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

// Keeps every protected form armed with a currently valid payload, so forms can be posted
// however and as often as their own module likes without knowing this module exists.
const MahoCaptcha = {
    loadingImageUrl: null,
    verifyingText: 'Verifying...',
    altchaWidget: null,
    altchaState: null,
    frontendSelectors: '',
    scriptsPromise: null,
    verificationPromise: null,
    resolveVerification: null,
    lastRearmAt: 0,
    loaderEl: null,
    loaderTimeoutId: null,

    async setup(config) {
        this.altchaWidget = document.querySelector('altcha-widget');
        this.frontendSelectors = config.frontendSelectors ?? '';
        this.loadingImageUrl = config.loadingImageUrl ?? '';
        this.verifyingText = config.verifyingText ?? 'Verifying...';

        if (document.readyState === 'loadingImageUrl') {
            document.addEventListener('DOMContentLoaded', this.initForms.bind(this));
        } else {
            this.initForms();
        }

        this.altchaWidget.addEventListener('load', () => {
            const state = this.altchaWidget.getState();
            this.onStateChange({detail: {state}});
            this.altchaWidget.addEventListener('statechange', this.onStateChange.bind(this));
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
        const buttonEl = event.target;

        event.preventDefault();
        event.stopPropagation();

        if (await this.verifyWithLoader()) {
            buttonEl.dispatchEvent(new PointerEvent('click'));
        }
    },

    async verifyWithLoader() {
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
        if (this.altchaState === 'verifying') {
            return;
        }
        // The widget only solves a new challenge from the unverified state.
        if (this.altchaState !== null && this.altchaState !== 'unverified') {
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
            // Replicate maho_captcha input into all forms
            if (typeof payload === 'string') {
                for (const formEl of this.getForms()) {
                    this.setHiddenInputValue(formEl, payload);
                }
            }
            this.settleVerification(typeof payload === 'string');
        } else if (state === 'expired') {
            // Re-arm rather than leave forms holding a payload the server will reject: an AJAX
            // post fires no submit event for us to intercept.
            this.clearHiddenInputs();
            if (this.canRearm()) {
                this.startVerification();
            } else {
                this.settleVerification(false);
            }
        } else if (state === 'error') {
            // No re-arm: retrying a failing challenge endpoint would spin.
            this.clearHiddenInputs();
            this.settleVerification(false);
        }
    },

    // Stops a challenge that arrives already expired (clock skew) from re-arming in a loop.
    canRearm() {
        const now = performance.now();
        if (now - this.lastRearmAt < 5000) {
            return false;
        }
        this.lastRearmAt = now;
        return true;
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

    clearHiddenInputs() {
        for (const formEl of this.getForms()) {
            formEl.querySelector('input[name="maho_captcha"]')?.remove();
        }
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
