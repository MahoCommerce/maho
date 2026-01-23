/**
 * Maho
 *
 * @package     base_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class Minicart {
    constructor(options) {
        this.formKey = options.formKey;
        this.previousVal = null;
        this.defaultErrorMessage = 'Error occurred. Try to refresh page.';
        this.selectors = {
            itemRemove: '#cart-sidebar .remove',
            container: '#header-cart',
            inputQty: '.cart-item-quantity',
            qty: 'div.header-minicart span.count',
            overlay: '.minicart-wrapper',
            error: '#minicart-error-message',
            success: '#minicart-success-message',
            quantityButtonPrefix: '#qbutton-',
            quantityInputPrefix: '#qinput-',
            quantityButtonClass: '.quantity-button'
        };

        if (options.selectors) {
            this.selectors = { ...this.selectors, ...options.selectors };
        }

        // Bind the methods to the current instance
        this.removeItemHandler = this.removeItemHandler.bind(this);
        this.focusHandler = this.focusHandler.bind(this);
        this.blurHandler = this.blurHandler.bind(this);
        this.quantityButtonHandler = this.processUpdateQuantity.bind(this);
    }

    initAfterEvents = {};
    removeItemAfterEvents = {};

    init() {
        document.querySelectorAll(this.selectors.itemRemove).forEach(el => {
            el.removeEventListener('click', this.removeItemHandler);
            el.addEventListener('click', this.removeItemHandler);
        });

        document.querySelectorAll(this.selectors.inputQty).forEach(el => {
            el.removeEventListener('focus', this.focusHandler);
            el.removeEventListener('blur', this.blurHandler);
            el.addEventListener('focus', () => this.focusHandler(el));
            el.addEventListener('blur', () => this.blurHandler(el));
        });

        document.querySelectorAll(this.selectors.quantityButtonClass).forEach(el => {
            el.removeEventListener('click', this.quantityButtonHandler);
            el.addEventListener('click', () => this.processUpdateQuantity(el));
        });

        for (const [, event] of Object.entries(this.initAfterEvents)) {
            if (typeof event === "function") {
                event();
            }
        }
    }

    removeItemHandler(e) {
        e.preventDefault();
        this.removeItem(e.currentTarget);
    }

    focusHandler(el) {
        this.previousVal = el.value;
        this.displayQuantityButton(el);
    }

    blurHandler(el) {
        this.revertInvalidValue(el);
    }

    removeItem(el) {
        if (confirm(el.dataset.confirm)) {
            this.hideMessage();
            this.showOverlay();

            const formData = new URLSearchParams();
            formData.append('form_key', this.formKey);

            fetch(el.getAttribute('href'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
                .then(response => response.json())
                .then(result => {
                    this.hideOverlay();
                    if (result.success) {
                        this.refreshIfOnCartPage();
                        this.updateCartQty(result.qty);
                        this.updateContentOnRemove(result);
                    } else {
                        this.showMessage(result);
                    }
                    this.init();
                    truncateOptions();
                })
                .catch(() => {
                    this.hideOverlay();
                    this.showError(this.defaultErrorMessage);
                });
        }
        for (const [, event] of Object.entries(this.removeItemAfterEvents)) {
            if (typeof event === "function") {
                event();
            }
        }
    }

    revertInvalidValue(el) {
        if (!this.isValidQty(el.value) || el.value == this.previousVal) {
            el.value = this.previousVal;
            this.hideQuantityButton(el);
        }
    }

    displayQuantityButton(el) {
        const buttonId = this.selectors.quantityButtonPrefix + el.dataset.itemId;
        const button = document.querySelector(buttonId);
        button.classList.add('visible');
        button.removeAttribute('disabled');
    }

    hideQuantityButton(el) {
        const buttonId = this.selectors.quantityButtonPrefix + el.dataset.itemId;
        const button = document.querySelector(buttonId);
        button.classList.remove('visible');
        button.setAttribute('disabled', 'disabled');
    }

    processUpdateQuantity(el) {
        const input = document.querySelector(this.selectors.quantityInputPrefix + el.dataset.itemId);
        if (this.isValidQty(input.value) && input.value != this.previousVal) {
            this.updateItem(el);
        } else {
            this.revertInvalidValue(input);
        }
    }

    updateItem(el) {
        const input = document.querySelector(this.selectors.quantityInputPrefix + el.dataset.itemId);

        if (isNaN(input.value)) {
            this.hideOverlay();
            this.showError(this.defaultErrorMessage);
            return false;
        }

        const quantity = input.value;
        this.hideMessage();
        this.showOverlay();

        const formData = new URLSearchParams();
        formData.append('qty', quantity);
        formData.append('form_key', this.formKey);

        fetch(input.dataset.link, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
            .then(response => response.json())
            .then(result => {
                this.hideOverlay();
                if (result.success) {
                    this.refreshIfOnCartPage();
                    this.updateCartQty(result.qty);
                    if (quantity !== 0) {
                        this.updateContentOnUpdate(result);
                    } else {
                        this.updateContentOnRemove(result);
                    }
                } else {
                    this.showMessage(result);
                }
                this.init();
                truncateOptions();
            })
            .catch(() => {
                this.hideOverlay();
                this.showError(this.defaultErrorMessage);
            });
        return false;
    }

    updateContentOnRemove(result) {
        this.updateContent(result);
    }

    updateContentOnUpdate(result) {
        this.updateContent(result);
    }

    updateContent(result) {
        const container = this.getMessageContainer();
        container.innerHTML = result.content;
        this.showMessage(result);
    }

    updateCartQty(qty) {
        if (typeof qty !== 'undefined') {
            const el = document.querySelector(this.selectors.qty);
            el.textContent = qty;
            el.className = el.className.replace(/count-\d+/, 'count-' + qty);
        }
    }

    isValidQty(val) {
        return (val.length > 0) && (val - 0 == val) && (val - 0 > 0);
    }

    showOverlay() {
        document.querySelector(this.selectors.overlay).classList.add('loading');
    }

    hideOverlay() {
        document.querySelector(this.selectors.overlay).classList.remove('loading');
    }

    showMessage(result) {
        if (typeof result.notice !== 'undefined') {
            this.showError(result.notice);
        } else if (typeof result.error !== 'undefined') {
            this.showError(result.error);
        } else if (typeof result.message !== 'undefined') {
            this.showSuccess(result.message);
        }
    }

    getMessageContainer() {
        return document.querySelector(this.selectors.overlay)?.parentNode || document;
    }

    hideMessage() {
        const container = this.getMessageContainer();
        container.querySelector(this.selectors.error)?.style.setProperty('display', 'none');
        container.querySelector(this.selectors.success)?.style.setProperty('display', 'none');
    }

    showError(message) {
        const el = document.querySelector(this.selectors.error);
        if (!el) return;
        el.textContent = message;
        el.style.display = 'block';
    }

    showSuccess(message) {
        const el = document.querySelector(this.selectors.success);
        if (!el) return;
        el.textContent = message;
        el.style.display = 'block';
    }

    refreshIfOnCartPage() {
        if (document.body.classList.contains("checkout-cart-index")) {
            window.location.reload(true);
        }
    }

    openOffcanvas() {
        const trigger = document.querySelector('.skip-cart.offcanvas-trigger');
        if (trigger) {
            trigger.click();
        }
    }
};
