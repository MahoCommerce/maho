/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * VAT Number Validator with real-time AJAX validation
 */
class VatValidator {
    constructor(config) {
        this.config = {
            vatFieldId: '',
            countryFieldId: '',
            validateUrl: '/customer/vat/validate',
            debounceDelay: 400,
            minLength: 4,
            ...config
        };

        this.vatField = null;
        this.countryField = null;
        this.indicator = null;
        this.debounceTimer = null;
        this.requestId = 0;
        this.lastValidatedValue = null;
        this.lastValidatedCountry = null;

        this.init();
    }

    init() {
        this.vatField = document.getElementById(this.config.vatFieldId);
        this.countryField = document.getElementById(this.config.countryFieldId);

        if (!this.vatField) {
            return;
        }

        this.createIndicator();
        this.bindEvents();
    }

    createIndicator() {
        this.indicator = document.createElement('div');
        this.indicator.className = 'validation-advice';
        this.indicator.id = `advice-vat-validation-${this.vatField.id}`;
        this.indicator.setAttribute('aria-live', 'polite');
        this.indicator.style.display = 'none';
        this.vatField.parentNode.appendChild(this.indicator);
    }

    bindEvents() {
        this.vatField.addEventListener('input', () => this.handleInput());
        this.vatField.addEventListener('blur', () => this.handleBlur());

        if (this.countryField) {
            this.countryField.addEventListener('change', () => this.handleCountryChange());
        }
    }

    handleInput() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        const value = this.vatField.value.trim();

        if (!value || value.length < this.config.minLength) {
            this.clearValidation();
            return;
        }

        this.setStatus('validating');
        this.debounceTimer = setTimeout(() => this.validate(), this.config.debounceDelay);
    }

    handleBlur() {
        const value = this.vatField.value.trim();

        if (!value || value.length < this.config.minLength) {
            return;
        }

        const country = this.getCountryCode();
        if (value !== this.lastValidatedValue || country !== this.lastValidatedCountry) {
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }
            this.validate();
        }
    }

    handleCountryChange() {
        const value = this.vatField.value.trim();

        if (!value || value.length < this.config.minLength) {
            return;
        }

        this.clearValidation();
        this.setStatus('validating');
        this.validate();
    }

    getCountryCode() {
        return this.countryField?.value || '';
    }

    async validate() {
        const vatNumber = this.vatField.value.trim();
        const country = this.getCountryCode();

        if (!vatNumber || !country) {
            this.clearValidation();
            return;
        }

        const currentRequestId = ++this.requestId;

        try {
            const response = await mahoFetch(this.config.validateUrl, {
                method: 'POST',
                body: new URLSearchParams({ country, vat_number: vatNumber })
            });

            // Ignore stale responses
            if (currentRequestId !== this.requestId) {
                return;
            }

            this.lastValidatedValue = vatNumber;
            this.lastValidatedCountry = country;
            this.handleResponse(response);
        } catch (error) {
            // Ignore errors from stale requests
            if (currentRequestId !== this.requestId) {
                return;
            }
            this.setStatus('error');
        }
    }

    handleResponse(response) {
        // Don't show anything for unsupported countries
        if (response.country_supported === false) {
            this.clearValidation();
            return;
        }

        if (response.error) {
            this.setStatus('error', response.message);
            return;
        }

        if (response.valid) {
            this.setStatus('valid');
        } else {
            this.setStatus('invalid', response.message);
        }
    }

    setStatus(status, message = '') {
        this.vatField.classList.remove('validation-passed', 'validation-failed');

        switch (status) {
            case 'validating':
                this.indicator.className = 'validation-advice';
                this.indicator.textContent = '…';
                this.indicator.style.display = '';
                break;
            case 'valid':
                this.vatField.classList.add('validation-passed');
                this.indicator.style.display = 'none';
                break;
            case 'invalid':
            case 'error':
                this.vatField.classList.add('validation-failed');
                this.indicator.className = 'validation-advice';
                this.indicator.textContent = message;
                this.indicator.style.display = message ? '' : 'none';
                break;
            default:
                this.indicator.style.display = 'none';
                return;
        }
    }

    clearValidation() {
        this.vatField.classList.remove('validation-passed', 'validation-failed');
        this.indicator.className = 'validation-advice';
        this.indicator.textContent = '';
        this.indicator.style.display = 'none';
        this.lastValidatedValue = null;
        this.lastValidatedCountry = null;
    }
}

window.VatValidator = VatValidator;
