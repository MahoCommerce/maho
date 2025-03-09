/**
 * Maho
 *
 * @package     Maho_Captcha
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const MahoCaptcha = {
    altchaWidget: null,
    altchaState: null,
    frontendSelectors: '',
    onVerifiedCallback: null,

    async setup(config) {
        this.altchaWidget = document.querySelector('altcha-widget');
        this.frontendSelectors = config.frontendSelectors ?? '';

        // Load Altcha JS
        if (typeof customElements.get('altcha-widget') !== 'function') {
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
        }

        // Inject Altcha Widget
        if (!this.altchaWidget) {
            if (typeof config.widgetAttributes !== 'object' || config.widgetAttributes === null) {
                throw new Error('widgetAttributes must be specified');
            }
            this.altchaWidget = document.createElement('altcha-widget');
            for (const [ attr, val ] of Object.entries(config.widgetAttributes)) {
                this.altchaWidget.setAttribute(attr, val);
            }
            document.body.appendChild(this.altchaWidget);
        }

        // Inject Stylesheet
        if (!document.querySelector('style[maho-captcha-style]')) {
            const styleEl = document.createElement('style');
            styleEl.dataset.mahoCaptchaStyle = '';
            styleEl.textContent = `
                altcha-widget[name=maho_captcha] {
                    display: flex;
                    position: fixed;
                    bottom: 0;
                    right: 0;
                }
                dialog.maho-captcha-verifying {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 8px;
                    padding: 8px;
                    border: none;
                    border-radius: 6px;
                    box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
                    body:has(&) {
                        overflow: hidden;
                    }
                    &::backdrop {
                        background: rgba(0, 0, 0, 0.7);
                    }
                }
            `;
            document.head.appendChild(styleEl);
        }

        this.altchaWidget.addEventListener('load', () => {
            const state = this.altchaWidget.getState();
            this.onStateChange({ detail: { state } });
            this.altchaWidget.addEventListener('statechange', this.onStateChange.bind(this));
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', this.initForms.bind(this));
        } else {
            this.initForms();
        }
    },

    initForms() {
        for (const formEl of document.querySelectorAll(this.frontendSelectors)) {
            formEl.addEventListener('focusin', this.startVerification.bind(this), true);
            formEl.addEventListener('submit', this.onFormSubmit.bind(this), true);
        }
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

    startVerification() {
        if (this.altchaState === 'unverified' || this.altchaState === 'error') {
            this.altchaWidget.verify();
        }
    },

    onStateChange(event) {
        const { state, payload } = event.detail;
        this.altchaState = state;

        // Fix for error `An invalid form control with name='' is not focusable.`
        document.getElementById('maho_captcha_checkbox').disabled = state === 'verifying';

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
        if (this.loaderEl) {
            return;
        }
        this.loaderEl = document.createElement('dialog');
        this.loaderEl.className = 'maho-captcha-verifying';
        this.loaderEl.textContent = 'Verifying...';
        this.loaderEl.addEventListener('close', () => {
            this.onVerifiedCallback = null;
            this.hideLoader();
        });
        document.body.appendChild(this.loaderEl);
        this.loaderEl.showModal();
    },

    hideLoader() {
        if (this.loaderEl) {
            this.loaderEl.remove();
            this.loaderEl = null;
        }
    },
}
