/**
 * Maho
 *
 * @category    design
 * @package     rwd_default
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
function Minicart(options) {
    this.formKey = options.formKey;
    this.previousVal = null;
    this.defaultErrorMessage = 'Error occurred. Try to refresh page.';
    this.selectors = {
        itemRemove:           '#cart-sidebar .remove',
        container:            '#header-cart',
        inputQty:             '.cart-item-quantity',
        qty:                  'div.header-minicart span.count',
        overlay:              '.minicart-wrapper',
        error:                '#minicart-error-message',
        success:              '#minicart-success-message',
        quantityButtonPrefix: '#qbutton-',
        quantityInputPrefix:  '#qinput-',
        quantityButtonClass:  '.quantity-button'
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

Minicart.prototype = {
    initAfterEvents: {},
    removeItemAfterEvents: {},
    init: function() {
        var cart = this;

        document.querySelectorAll(this.selectors.itemRemove).forEach(function(el) {
            el.removeEventListener('click', cart.removeItemHandler);
            el.addEventListener('click', cart.removeItemHandler);
        });

        document.querySelectorAll(this.selectors.inputQty).forEach(function(el) {
            el.removeEventListener('focus', cart.focusHandler);
            el.removeEventListener('blur', cart.blurHandler);
            el.addEventListener('focus', function() {
                cart.focusHandler(el);
            });
            el.addEventListener('blur', function() {
                cart.blurHandler(el);
            });
        });

        document.querySelectorAll(this.selectors.quantityButtonClass).forEach(function(el) {
            el.removeEventListener('click', cart.quantityButtonHandler);
            el.addEventListener('click', function() {
                cart.processUpdateQuantity(el); // Pass the correct element to the method
            });
        });

        for (var i in this.initAfterEvents) {
            if (this.initAfterEvents.hasOwnProperty(i) && typeof this.initAfterEvents[i] === "function") {
                this.initAfterEvents[i]();
            }
        }
    },

    removeItemHandler: function(e) {
        e.preventDefault();
        this.removeItem(e.currentTarget);
    },

    focusHandler: function(el) {
        this.previousVal = el.value;
        this.displayQuantityButton(el);
    },

    blurHandler: function(el) {
        this.revertInvalidValue(el);
    },

    removeItem: function(el) {
        var cart = this;
        if (confirm(el.dataset.confirm)) {
            cart.hideMessage();
            cart.showOverlay();

            // Create a URL-encoded string
            const formData = new URLSearchParams();
            formData.append('form_key', cart.formKey);

            fetch(el.getAttribute('href'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
                .then(response => response.json())
                .then(function(result) {
                    cart.hideOverlay();
                    if (result.success) {
                        cart.refreshIfOnCartPage();
                        cart.updateCartQty(result.qty);
                        cart.updateContentOnRemove(result, el.closest('li'));
                    } else {
                        cart.showMessage(result);
                    }
                    cart.init();
                    truncateOptions();
                })
                .catch(function() {
                    cart.hideOverlay();
                    cart.showError(cart.defaultErrorMessage);
                });
        }
        for (var i in this.removeItemAfterEvents) {
            if (this.removeItemAfterEvents.hasOwnProperty(i) && typeof this.removeItemAfterEvents[i] === "function") {
                this.removeItemAfterEvents[i]();
            }
        }
    },

    revertInvalidValue: function(el) {
        if (!this.isValidQty(el.value) || el.value == this.previousVal) {
            el.value = this.previousVal;
            this.hideQuantityButton(el);
        }
    },

    displayQuantityButton: function(el) {
        var buttonId = this.selectors.quantityButtonPrefix + el.dataset.itemId;
        var button = document.querySelector(buttonId);
        button.classList.add('visible');
        button.removeAttribute('disabled');
    },

    hideQuantityButton: function(el) {
        var buttonId = this.selectors.quantityButtonPrefix + el.dataset.itemId;
        var button = document.querySelector(buttonId);
        button.classList.remove('visible');
        button.setAttribute('disabled', 'disabled');
    },

    processUpdateQuantity: function(el) {
        var input = document.querySelector(this.selectors.quantityInputPrefix + el.dataset.itemId);
        if (this.isValidQty(input.value) && input.value != this.previousVal) {
            this.updateItem(el);
        } else {
            this.revertInvalidValue(input);
        }
    },

    updateItem: function(el) {
        var cart = this;
        var input = document.querySelector(this.selectors.quantityInputPrefix + el.dataset.itemId);

        if (isNaN(input.value)) {
            cart.hideOverlay();
            cart.showError(cart.defaultErrorMessage);
            return false;
        }

        var quantity = input.value;
        cart.hideMessage();
        cart.showOverlay();

        // Create a URL-encoded string
        const formData = new URLSearchParams();
        formData.append('qty', quantity);
        formData.append('form_key', cart.formKey);

        fetch(input.dataset.link, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
            .then(response => response.json())
            .then(function(result) {
                cart.hideOverlay();
                if (result.success) {
                    cart.refreshIfOnCartPage();
                    cart.updateCartQty(result.qty);
                    if (quantity !== 0) {
                        cart.updateContentOnUpdate(result);
                    } else {
                        cart.updateContentOnRemove(result, input.closest('li'));
                    }
                } else {
                    cart.showMessage(result);
                }
                cart.init();
                truncateOptions();
            })
            .catch(function() {
                cart.hideOverlay();
                cart.showError(cart.defaultErrorMessage);
            });
        return false;
    },

    updateContentOnRemove: function(result, el) {
        var cart = this;
        el.style.display = 'none';
        document.querySelector(this.selectors.container).innerHTML = result.content;
        cart.showMessage(result);
    },

    updateContentOnUpdate: function(result) {
        document.querySelector(this.selectors.container).innerHTML = result.content;
        this.showMessage(result);
    },

    updateCartQty: function(qty) {
        if (typeof qty !== 'undefined') {
            document.querySelector(this.selectors.qty).textContent = qty;
        }
    },

    isValidQty: function(val) {
        return (val.length > 0) && (val - 0 == val) && (val - 0 > 0);
    },

    showOverlay: function() {
        document.querySelector(this.selectors.overlay).classList.add('loading');
    },

    hideOverlay: function() {
        document.querySelector(this.selectors.overlay).classList.remove('loading');
    },

    showMessage: function(result) {
        if (typeof result.notice !== 'undefined') {
            this.showError(result.notice);
        } else if (typeof result.error !== 'undefined') {
            this.showError(result.error);
        } else if (typeof result.message !== 'undefined') {
            this.showSuccess(result.message);
        }
    },

    hideMessage: function() {
        document.querySelector(this.selectors.error).style.display = 'none';
        document.querySelector(this.selectors.success).style.display = 'none';
    },

    showError: function(message) {
        var errorElement = document.querySelector(this.selectors.error);
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    },

    showSuccess: function(message) {
        var successElement = document.querySelector(this.selectors.success);
        successElement.textContent = message;
        successElement.style.display = 'block';
    },

    refreshIfOnCartPage: function() {
        if (document.body.classList.contains("checkout-cart-index")) {
            window.location.reload(true);
        }
    }
};
