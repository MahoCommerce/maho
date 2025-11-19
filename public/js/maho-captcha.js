/**
 * Maho
 *
 * @package     Maho_Captcha
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const MahoCaptcha = {
    loadingImageUrl: null,
    altchaWidget: null,
    altchaState: null,
    frontendSelectors: '',
    onVerifiedCallback: null,
    loaderEl: null,
    loaderTimeoutId: null,

    async setup(config) {
        this.altchaWidget = document.querySelector('altcha-widget');
        this.frontendSelectors = config.frontendSelectors ?? '';
        this.loadingImageUrl = config.loadingImageUrl ?? '';

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

    initForms() {
        for (const formEl of document.querySelectorAll(this.frontendSelectors)) {
            formEl.addEventListener('focusin', this.loadAltchaScripts.bind(this), { capture: true, once: true });
            formEl.addEventListener('submit', this.onFormSubmit.bind(this), true);
            for (const buttonEl of formEl.querySelectorAll('button[type=submit]')) {
                buttonEl.addEventListener('click', this.onFormButtonClick.bind(this), true);
            }
        }
    },

    async loadAltchaScripts() {
        if (typeof customElements.get('altcha-widget') === 'function') return;

        await Promise.all([
            new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = '/js/altcha.min.js';
                script.type = 'module';
                script.onload = resolve;
                script.onerror = () => reject(`${script.src} Not Found`);
                document.head.appendChild(script);
            }),
        ]);

        const styleEl = document.createElement('style');
        styleEl.textContent = `
        altcha-widget {display: flex;position: fixed;bottom: 0;right: 0}
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

    onFormSubmit(event) {
        const formEl = event.target;
        if (this.altchaState !== 'verified') {
            event.preventDefault();
            event.stopPropagation();

            this.showLoader();
            this.onVerifiedCallback = () => {
                this.hideLoader();
                formEl.requestSubmit(event.submitter)
            }
            this.startVerification();
        }
    },

    onFormButtonClick(event) {
        const buttonEl = event.target;
        if (this.altchaState !== 'verified') {
            event.preventDefault();
            event.stopPropagation();

            this.showLoader();
            this.onVerifiedCallback = () => {
                this.hideLoader();
                buttonEl.dispatchEvent(new PointerEvent('click'));
            }
            this.startVerification();
        }
    },

    startVerification() {
        if (this.altchaState === 'unverified' || this.altchaState === 'error') {
            this.altchaWidget.verify();
        }
    },

    onStateChange(event) {
        const { state, payload } = event.detail;
        this.altchaState = state;

        // Fix for error `An invalid form control with name='' is not focusable.`
        document.querySelector('#maho_captcha input[type=checkbox]').disabled = state === 'verifying';

        // Replicate maho_captcha input into all forms
        if (state === 'verified' && typeof payload === 'string') {
            for (const formEl of document.querySelectorAll(this.frontendSelectors)) {
                this.setHiddenInputValue(formEl, payload);
            }
        }

        // Call stored form submit event on the next event loop
        if (state === 'verified' && typeof this.onVerifiedCallback === 'function') {
            setTimeout(this.onVerifiedCallback.bind(this), 0);
        }
        this.onVerifiedCallback = null;
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
            this.loaderEl.innerHTML = (this.loadingImageUrl ? '<img src="' + this.loadingImageUrl + '">' : '') + ' Verifying...';
            this.loaderEl.addEventListener('close', () => {
                this.onVerifiedCallback = null;
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
