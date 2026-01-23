/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Shared Promo Code handler for cart and checkout pages
 *
 * Usage:
 *   new MahoPromoCode({
 *       applyUrl: '/checkout/cart/couponPost',
 *       formKey: 'abc123',
 *       elements: {
 *           input: 'promo_code',
 *           applyBtn: 'btn-apply-promo',
 *           message: 'promo-code-message',
 *           codesContainer: 'applied-promo-codes'
 *       },
 *       messages: {
 *           emptyCode: 'Please enter a promo code.',
 *           applying: 'Applying...',
 *           error: 'An error occurred. Please try again.'
 *       },
 *       onSuccess: () => window.location.reload()
 *   });
 */
class MahoPromoCode {
    constructor(config) {
        this.config = {
            applyUrl: '',
            formKey: '',
            elements: {
                input: '',
                applyBtn: '',
                message: '',
                codesContainer: ''
            },
            messages: {
                emptyCode: 'Please enter a promo code.',
                applying: 'Applying...',
                error: 'An error occurred. Please try again.'
            },
            onSuccess: null,
            onRemoveSuccess: null,
            ...config
        };

        this.init();
    }

    init() {
        this.bindEvents();
    }

    getElement(key) {
        const id = this.config.elements[key];
        return id ? document.getElementById(id) : null;
    }

    bindEvents() {
        const applyBtn = this.getElement('applyBtn');
        const codeInput = this.getElement('input');

        if (applyBtn) {
            applyBtn.addEventListener('click', () => this.applyCode());
        }

        if (codeInput) {
            codeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyCode();
                }
            });
        }

        this.bindRemoveButtons();
    }

    bindRemoveButtons() {
        const container = this.getElement('codesContainer');
        const selector = container
            ? `#${this.config.elements.codesContainer} .btn-remove-code`
            : '.btn-remove-code';

        document.querySelectorAll(selector).forEach(btn => {
            // Avoid binding multiple times
            if (btn.dataset.promoBound) return;
            btn.dataset.promoBound = 'true';

            btn.addEventListener('click', (e) => {
                const codeEl = e.target.closest('.applied-code');
                if (codeEl) {
                    const type = codeEl.dataset.type;
                    const code = codeEl.dataset.code;
                    this.removeCode(type, code);
                }
            });
        });
    }

    async applyCode() {
        const codeInput = this.getElement('input');
        const code = codeInput?.value.trim();

        if (!code) {
            this.showMessage(this.config.messages.emptyCode, 'error');
            return;
        }

        const applyBtn = this.getElement('applyBtn');
        const originalText = applyBtn?.textContent;

        if (applyBtn) {
            applyBtn.disabled = true;
            applyBtn.textContent = this.config.messages.applying;
        }

        try {
            const response = await mahoFetch(this.config.applyUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    form_key: this.config.formKey,
                    promo_code: code,
                    isAjax: '1'
                })
            });

            if (response.success) {
                // Update promo block HTML if provided (do this first to recreate elements)
                if (response.promo_block_html) {
                    this.updatePromoBlock(response.promo_block_html);
                }

                // Show message after update so it appears in the new element
                this.showMessage(response.message, 'success');

                if (typeof this.config.onSuccess === 'function') {
                    this.config.onSuccess(response);
                } else if (this.shouldReloadAfterPromoChange()) {
                    // Default behavior: reload page (but not in old accordion checkout)
                    setTimeout(() => window.location.reload(), 500);
                }
            } else {
                this.showMessage(response.message, 'error');
            }
        } catch (error) {
            this.showMessage(this.config.messages.error, 'error');
        } finally {
            if (applyBtn) {
                applyBtn.disabled = false;
                applyBtn.textContent = originalText;
            }
        }
    }

    async removeCode(type, code) {
        try {
            const response = await mahoFetch(this.config.applyUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    form_key: this.config.formKey,
                    remove_type: type,
                    remove_code: code,
                    isAjax: '1'
                })
            });

            if (response.success) {
                // Update promo block HTML if provided
                if (response.promo_block_html) {
                    this.updatePromoBlock(response.promo_block_html);
                }

                // Show message after update
                this.showMessage(response.message, 'success');

                if (typeof this.config.onRemoveSuccess === 'function') {
                    this.config.onRemoveSuccess(response);
                } else if (this.shouldReloadAfterPromoChange()) {
                    // Default behavior: reload page (but not in old accordion checkout)
                    window.location.reload();
                }
            } else {
                this.showMessage(response.message, 'error');
            }
        } catch (error) {
            this.showMessage(this.config.messages.error, 'error');
        }
    }

    /**
     * Returns true for cart page where page reload updates totals.
     * Returns false for onestep/multistep checkout
     */
    shouldReloadAfterPromoChange() {
        return typeof checkout === 'undefined' || !checkout.accordion;
    }

    showMessage(message, type) {
        const messageContainer = this.getElement('message');
        if (!messageContainer) return;

        messageContainer.textContent = message;
        messageContainer.className = 'promo-message ' + type;

        // Auto-clear success messages if configured
        if (type === 'success' && this.config.successMessageTimeout) {
            setTimeout(() => this.clearMessage(), this.config.successMessageTimeout);
        }
    }

    clearMessage() {
        const messageContainer = this.getElement('message');
        if (messageContainer) {
            messageContainer.textContent = '';
            messageContainer.className = 'promo-message';
        }
    }

    updatePromoBlock(html) {
        const container = this.getElement('codesContainer');
        if (!container) return;

        // Find the content area to update
        const contentArea = container.querySelector('.onestep-section-content')
            || container.querySelector('.discount-form')
            || container;

        contentArea.innerHTML = html;
        this.bindEvents();
    }
}

// Make globally available
window.MahoPromoCode = MahoPromoCode;
