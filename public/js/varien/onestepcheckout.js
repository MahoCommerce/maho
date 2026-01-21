/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Mock Accordion class for one-step checkout
 * The existing checkout templates expect an accordion, but we don't use one
 */
class OneStepAccordion {
    constructor() {
        this.sections = [];
    }

    openSection(sectionId) {
        // In one-step, all sections are always visible - no-op
    }

    closeSection(sectionId) {
        // In one-step, all sections are always visible - no-op
    }
}

/**
 * One-Step Checkout Controller
 * Works alongside the existing Billing, Shipping, ShippingMethod, Payment, Review classes
 */
class OneStepCheckout {
    constructor(config) {
        this.config = config;
        this.urls = {
            progress: config.progress,
            review: config.review,
            saveBilling: config.saveBilling,
            saveShipping: config.saveShipping,
            saveShippingMethod: config.saveShippingMethod,
            savePayment: config.savePayment,
            saveOrder: config.saveOrder,
            successUrl: config.successUrl,
            failure: config.failure,
            estimateBilling: config.estimateBilling,
            applyCoupon: config.applyCoupon
        };
        this.formKey = config.formKey;
        this.isVirtual = config.isVirtual;
        this.isLoggedIn = config.isLoggedIn;
        this.currentStep = 'billing';
        this.loadWaiting = false;

        // Create mock accordion for compatibility
        this.accordion = new OneStepAccordion();

        // Make this instance available globally as 'checkout' for compatibility
        window.checkout = this;
        window.accordion = this.accordion;

        this.billingForm = null;
        this.shippingForm = null;
        this.shippingMethodForm = null;
        this.paymentForm = null;

        // Queue for address-related requests to prevent race conditions
        // that could create duplicate addresses on the server
        this.addressRequestQueue = Promise.resolve();

        this.init();
    }

    init() {
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', () => {
            this.initForms();
            this.initBillingShippingToggle();
            this.initAutoSave();
            this.initPlaceholders();
            this.initCoupon();
            this.loadReview();
            // Check if billing form is pre-filled and load shipping methods
            // Use setTimeout to allow dynamic region dropdowns to populate
            setTimeout(() => this.checkPrefilledBilling(), 100);
        });
    }

    /**
     * Check if billing form has pre-filled data and trigger shipping method load
     */
    checkPrefilledBilling() {
        if (!this.billingForm || this.isVirtual) return;

        // Only require the 4 shipping-related fields
        const country = document.getElementById('billing:country_id')?.value;
        const postcode = document.getElementById('billing:postcode')?.value;
        const city = document.getElementById('billing:city')?.value;

        // Check if minimum required fields for shipping estimation are pre-filled
        if (country && postcode && city) {
            // Require region for countries that have regions (like US)
            const regionSelect = document.getElementById('billing:region_id');
            const regionId = document.getElementById('billing:region_id')?.value;
            if (regionSelect && regionSelect.options.length > 1 && !regionId) {
                return;
            }
            // Form has shipping address data, trigger billing save to load shipping methods
            this.saveBilling();
        }
    }

    initPlaceholders() {
        // Hide shipping method placeholder if methods already loaded
        const shippingMethodLoad = document.getElementById('checkout-shipping-method-load');
        const shippingPlaceholder = document.getElementById('shipping-method-placeholder');
        if (shippingMethodLoad && shippingPlaceholder && shippingMethodLoad.children.length > 0) {
            shippingPlaceholder.style.display = 'none';
        }

        // Hide payment method placeholder if methods already loaded (not showing "No Payment Methods")
        const paymentForm = document.getElementById('co-payment-form');
        const paymentPlaceholder = document.getElementById('payment-method-placeholder');
        if (paymentForm && paymentPlaceholder) {
            const hasPaymentMethods = paymentForm.querySelector('input[name="payment[method]"]');
            if (hasPaymentMethods) {
                paymentPlaceholder.style.display = 'none';
            }
        }
    }

    initCoupon() {
        const applyBtn = document.getElementById('onestep-coupon-apply');
        const cancelBtn = document.getElementById('onestep-coupon-cancel');
        const input = document.getElementById('onestep-coupon-code');

        if (applyBtn) {
            applyBtn.addEventListener('click', () => this.applyCoupon());
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.removeCoupon());
        }

        // Allow Enter key to apply coupon
        if (input) {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyCoupon();
                }
            });
        }
    }

    async applyCoupon() {
        const input = document.getElementById('onestep-coupon-code');
        const messageEl = document.getElementById('onestep-coupon-message');
        const cancelBtn = document.getElementById('onestep-coupon-cancel');

        if (!input) return;

        const couponCode = input.value.trim();
        if (!couponCode) {
            this.showCouponMessage('Please enter a coupon code.', false);
            return;
        }

        try {
            this.setLoading('onestep-coupon', true);

            const formData = new FormData();
            formData.append('coupon_code', couponCode);
            formData.append('form_key', this.formKey);
            formData.append('isAjax', '1');

            const result = await mahoFetch(this.urls.applyCoupon, {
                method: 'POST',
                body: formData,
                loaderArea: false
            });

            this.showCouponMessage(result.message, result.success);

            if (result.success && result.coupon_code) {
                input.value = result.coupon_code;
                if (cancelBtn) cancelBtn.style.display = '';
                // Refresh the order summary to show discount
                this.loadReview();
            }
        } catch (error) {
            this.showCouponMessage(error.message || 'Failed to apply coupon.', false);
        } finally {
            this.setLoading('onestep-coupon', false);
        }
    }

    async removeCoupon() {
        const input = document.getElementById('onestep-coupon-code');
        const cancelBtn = document.getElementById('onestep-coupon-cancel');

        try {
            this.setLoading('onestep-coupon', true);

            const formData = new FormData();
            formData.append('remove', '1');
            formData.append('form_key', this.formKey);
            formData.append('isAjax', '1');

            const result = await mahoFetch(this.urls.applyCoupon, {
                method: 'POST',
                body: formData,
                loaderArea: false
            });

            this.showCouponMessage(result.message, result.success);

            if (result.success) {
                if (input) input.value = '';
                if (cancelBtn) cancelBtn.style.display = 'none';
                // Refresh the order summary to remove discount
                this.loadReview();
            }
        } catch (error) {
            this.showCouponMessage(error.message || 'Failed to remove coupon.', false);
        } finally {
            this.setLoading('onestep-coupon', false);
        }
    }

    showCouponMessage(message, success) {
        const messageEl = document.getElementById('onestep-coupon-message');
        if (!messageEl) return;

        messageEl.textContent = message;
        messageEl.className = 'coupon-message ' + (success ? 'success' : 'error');

        // Auto-hide success messages after 5 seconds
        if (success && message) {
            setTimeout(() => {
                messageEl.textContent = '';
                messageEl.className = 'coupon-message';
            }, 5000);
        }
    }

    initForms() {
        this.billingForm = document.getElementById('co-billing-form');
        this.shippingForm = document.getElementById('co-shipping-form');
        this.shippingMethodForm = document.getElementById('co-shipping-method-form');
        this.paymentForm = document.getElementById('co-payment-form');
    }

    initBillingShippingToggle() {
        // Handle "Ship to this address" / "Ship to different address" toggle
        const useForShippingYes = document.getElementById('billing:use_for_shipping_yes');
        const useForShippingNo = document.getElementById('billing:use_for_shipping_no');
        const shippingSection = document.getElementById('onestep-shipping');

        if (!shippingSection) return; // Virtual product, no shipping

        const toggleShipping = () => {
            if (useForShippingNo && useForShippingNo.checked) {
                shippingSection.style.display = 'block';
            } else {
                shippingSection.style.display = 'none';
            }
        };

        if (useForShippingYes) {
            useForShippingYes.addEventListener('change', toggleShipping);
        }
        if (useForShippingNo) {
            useForShippingNo.addEventListener('change', toggleShipping);
        }

        // Initial state
        toggleShipping();
    }

    initAutoSave() {
        // Auto-save billing address on field change (change event only fires when value changes)
        if (this.billingForm) {
            this.billingForm.addEventListener('change', (e) => {
                if (e.target.matches('input, select, textarea')) {
                    this.debouncedSaveBilling();
                }
            });
        }

        // Auto-save shipping address on field change (when using different shipping address)
        if (this.shippingForm) {
            this.shippingForm.addEventListener('change', (e) => {
                if (e.target.matches('input, select, textarea')) {
                    this.debouncedSaveShipping();
                }
            });
        }

        // Auto-save shipping method on change
        this.initShippingMethodAutoSave();

        // Auto-save payment method on change
        this.initPaymentMethodAutoSave();
    }


    debouncedSaveBilling = this.debounce(() => {
        this.saveBilling();
    }, 500);

    debouncedSaveShipping = this.debounce(() => {
        this.saveShippingEstimate();
    }, 500);

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Queue an address-related request to prevent concurrent requests
     * that could create duplicate addresses on the server.
     * If a request is already pending, the new request waits for it to complete.
     * @param {Function} requestFn - Async function that performs the request
     * @returns {Promise}
     */
    queueAddressRequest(requestFn) {
        // Chain this request after any pending ones
        this.addressRequestQueue = this.addressRequestQueue
            .then(() => requestFn())
            .catch(() => {}); // Ignore errors to keep queue alive
        return this.addressRequestQueue;
    }

    saveBilling() {
        if (!this.billingForm || this.isVirtual) return;

        // Check if using billing address for shipping or separate shipping address
        const useForShippingYes = document.getElementById('billing:use_for_shipping_yes');
        const useForShipping = useForShippingYes ? useForShippingYes.checked : true;

        // Determine which form to check for shipping fields
        let fieldPrefix;
        if (useForShipping) {
            fieldPrefix = 'billing';
        } else {
            // Using separate shipping address - don't save billing for shipping estimate
            fieldPrefix = 'shipping';
            if (!this.shippingForm) return;
        }

        // Only check the 4 shipping-related fields for auto-save
        const country = document.getElementById(`${fieldPrefix}:country_id`)?.value;
        const postcode = document.getElementById(`${fieldPrefix}:postcode`)?.value;
        const city = document.getElementById(`${fieldPrefix}:city`)?.value;

        if (!country || !postcode || !city) {
            return;
        }

        // Require region for countries that have regions (like US)
        const regionSelect = document.getElementById(`${fieldPrefix}:region_id`);
        const regionId = document.getElementById(`${fieldPrefix}:region_id`)?.value;
        if (regionSelect && regionSelect.options.length > 1 && !regionId) {
            return;
        }

        // Queue this request to prevent concurrent address operations
        this.queueAddressRequest(async () => {
            try {
                this.setLoading('onestep-shipping-method', true);

                // Use estimateBilling endpoint - saves billing to quote and returns shipping methods
                const formData = new FormData(this.billingForm);

                const result = await mahoFetch(this.urls.estimateBilling, {
                    method: 'POST',
                    body: formData,
                    loaderArea: false
                });

                if (result.success && result.update_section) {
                    this.updateShippingMethods(result.update_section.html);
                }
            } catch (error) {
                // Silently handle errors during auto-save
            } finally {
                this.setLoading('onestep-shipping-method', false);
            }
        });
    }

    saveShipping() {
        if (!this.shippingForm || this.isVirtual) return;

        const formData = new FormData(this.shippingForm);

        this.queueAddressRequest(async () => {
            try {
                this.setLoading('onestep-shipping', true);

                await mahoFetch(this.urls.saveShipping, {
                    method: 'POST',
                    body: formData,
                    loaderArea: false
                });
            } catch (error) {
                // Silently handle errors
            } finally {
                this.setLoading('onestep-shipping', false);
            }
        });
        // Refresh review (will be queued after the above)
        this.loadReview();
    }

    /**
     * Save shipping address estimate when using different shipping address
     */
    saveShippingEstimate() {
        if (!this.shippingForm || this.isVirtual) return;

        // Only run if using different shipping address
        const useForShippingNo = document.getElementById('billing:use_for_shipping_no');
        if (!useForShippingNo || !useForShippingNo.checked) {
            return;
        }

        // Check the 4 shipping-related fields
        const country = document.getElementById('shipping:country_id')?.value;
        const postcode = document.getElementById('shipping:postcode')?.value;
        const city = document.getElementById('shipping:city')?.value;

        if (!country || !postcode || !city) {
            return;
        }

        // Require region for countries that have regions
        const regionSelect = document.getElementById('shipping:region_id');
        const regionId = document.getElementById('shipping:region_id')?.value;
        if (regionSelect && regionSelect.options.length > 1 && !regionId) {
            return;
        }

        // Queue this request to prevent concurrent address operations
        this.queueAddressRequest(async () => {
            try {
                this.setLoading('onestep-shipping-method', true);

                // Use the shipping form which includes form_key
                const formData = new FormData(this.shippingForm);

                const result = await mahoFetch(this.urls.saveShipping, {
                    method: 'POST',
                    body: formData,
                    loaderArea: false
                });

                // Update shipping methods if provided in response
                if (result.update_section && result.update_section.name === 'shipping-method') {
                    this.updateShippingMethods(result.update_section.html);
                }
            } catch (error) {
                // Silently handle errors during auto-save
            } finally {
                this.setLoading('onestep-shipping-method', false);
            }
        });
    }

    initShippingMethodAutoSave() {
        // Listen for shipping method selection changes
        const container = document.getElementById('checkout-shipping-method-load');
        if (!container) return;

        container.addEventListener('change', (e) => {
            if (e.target.name === 'shipping_method') {
                this.saveShippingMethod();
            }
        });
    }

    saveShippingMethod() {
        const selected = document.querySelector('input[name="shipping_method"]:checked');
        if (!selected) return;

        // Use the actual form to include form key
        const shippingMethodForm = document.getElementById('co-shipping-method-form');
        if (!shippingMethodForm) return;

        const formData = new FormData(shippingMethodForm);

        this.queueAddressRequest(async () => {
            try {
                this.setLoading('onestep-shipping-method', true);

                const result = await mahoFetch(this.urls.saveShippingMethod, {
                    method: 'POST',
                    body: formData,
                    loaderArea: false
                });

                // Update payment methods if provided
                if (result.update_section && result.update_section.name === 'payment-method') {
                    this.updatePaymentMethods(result.update_section.html);
                }
            } catch (error) {
                // Silently handle errors
            } finally {
                this.setLoading('onestep-shipping-method', false);
            }
        });
        // Refresh review (will be queued after the above)
        this.loadReview();
    }

    initPaymentMethodAutoSave() {
        // Listen for payment method selection changes
        const container = document.getElementById('checkout-payment-method-load');
        if (!container) return;

        container.addEventListener('change', (e) => {
            if (e.target.name === 'payment[method]') {
                this.savePayment();
            }
        });
    }

    savePayment() {
        const paymentForm = document.getElementById('co-payment-form');
        if (!paymentForm) return;

        const formData = new FormData(paymentForm);

        this.queueAddressRequest(async () => {
            try {
                this.setLoading('onestep-payment', true);

                await mahoFetch(this.urls.savePayment, {
                    method: 'POST',
                    body: formData,
                    loaderArea: false
                });
            } catch (error) {
                // Silently handle errors
            } finally {
                this.setLoading('onestep-payment', false);
            }
        });
        // Refresh review (will be queued after the above)
        this.loadReview();
    }

    async loadShippingMethods() {
        // Shipping methods are typically loaded as part of the billing save response
        // or we can trigger a billing save to refresh them
        if (this.isVirtual) return;

        // For now, shipping methods are updated through saveBilling response
        // No separate loading needed
    }

    updateShippingMethods(html) {
        const container = document.getElementById('checkout-shipping-method-load');
        const placeholder = document.getElementById('shipping-method-placeholder');

        if (container && html) {
            container.innerHTML = html;
            // Hide placeholder when content is loaded
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            // Re-init any scripts
            this.initShippingMethodAutoSave();
            // Auto-select if only one shipping method
            this.autoSelectSingleShippingMethod();
        }
    }

    /**
     * Auto-select shipping method if only one is available and trigger payment load
     */
    autoSelectSingleShippingMethod() {
        const shippingMethods = document.querySelectorAll('input[name="shipping_method"]');
        if (shippingMethods.length === 1) {
            // Ensure it's checked (may already be checked by server)
            shippingMethods[0].checked = true;
            // Trigger save to load payment methods
            setTimeout(() => this.saveShippingMethod(), 50);
        }
    }

    updatePaymentMethods(html) {
        const container = document.getElementById('checkout-payment-method-load');
        const placeholder = document.getElementById('payment-method-placeholder');

        if (container && html) {
            container.innerHTML = html;
            // Hide placeholder when content is loaded
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            // Re-init any scripts
            this.initPaymentMethodAutoSave();
            // Auto-select if only one payment method
            this.autoSelectSinglePaymentMethod();
        }
    }

    /**
     * Auto-select payment method if only one is available
     */
    autoSelectSinglePaymentMethod() {
        const paymentMethods = document.querySelectorAll('input[name="payment[method]"]');
        if (paymentMethods.length === 1) {
            // Ensure it's checked (may already be checked by server)
            paymentMethods[0].checked = true;
            // Trigger save to update review
            setTimeout(() => this.savePayment(), 50);
        }
    }

    loadReview() {
        const container = document.getElementById('checkout-review-load');
        if (!container) return;

        this.queueAddressRequest(async () => {
            try {
                this.setLoading('onestep-review', true);

                // mahoFetch returns text/html directly for non-JSON responses
                const html = await mahoFetch(this.urls.review, {
                    method: 'GET',
                    loaderArea: false
                });

                // Insert HTML and execute any inline scripts
                // This allows the Review object from opcheckout.js to be created
                if (typeof updateElementHtmlAndExecuteScripts === 'function') {
                    updateElementHtmlAndExecuteScripts(container, html);
                } else {
                    container.innerHTML = html;
                    this.executeScripts(container);
                }

                // Wrap review.save() to add billing form validation
                this.wrapReviewSave();
            } catch (error) {
                // Silently handle errors - review will load when checkout state is valid
            } finally {
                this.setLoading('onestep-review', false);
            }
        });
    }

    /**
     * Wrap review.save() to add billing/shipping form validation
     */
    wrapReviewSave() {
        // If review object doesn't exist yet, retry after a short delay
        if (typeof review === 'undefined' || !review.save) {
            setTimeout(() => this.wrapReviewSave(), 100);
            return;
        }

        // Don't wrap twice
        if (review._onestepWrapped) return;
        review._onestepWrapped = true;

        const originalSave = review.save.bind(review);
        const self = this;

        review.save = function() {
            // Clear previous error messages
            self.clearErrors();

            let isValid = true;

            // Validate billing form
            if (self.billingForm) {
                const billingValidator = new Validation(self.billingForm);
                if (!billingValidator.validate()) {
                    isValid = false;
                }
            }

            // Validate shipping form if visible and using different address
            const useForShippingNo = document.getElementById('billing:use_for_shipping_no');
            if (useForShippingNo && useForShippingNo.checked && self.shippingForm) {
                const shippingValidator = new Validation(self.shippingForm);
                if (!shippingValidator.validate()) {
                    isValid = false;
                }
            }

            // Validate shipping method is selected (for non-virtual orders)
            if (!self.isVirtual) {
                const shippingMethodSelected = document.querySelector('input[name="shipping_method"]:checked');
                if (!shippingMethodSelected) {
                    self.showError(self.config.messages.selectShippingMethodError);
                    isValid = false;
                }
            }

            // Validate payment method is selected
            const paymentMethodSelected = document.querySelector('input[name="payment[method]"]:checked');
            if (!paymentMethodSelected) {
                self.showError(self.config.messages.selectPaymentMethodError);
                isValid = false;
            }

            // Validate payment form (for credit card fields, etc.)
            if (self.paymentForm) {
                const paymentValidator = new Validation(self.paymentForm);
                if (!paymentValidator.validate()) {
                    isValid = false;
                }
            }

            // Validate agreements (terms and conditions)
            const agreements = document.querySelectorAll('.checkout-agreements input[type="checkbox"]');
            for (const agreement of agreements) {
                if (!agreement.checked) {
                    self.showError(self.config.messages.agreementsError, agreement);
                    isValid = false;
                }
            }

            // Only proceed if all validations passed
            if (!isValid) {
                return false;
            }

            // Call original save
            return originalSave();
        };
    }

    /**
     * Execute script tags within a container (since innerHTML doesn't run them)
     */
    executeScripts(container) {
        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            if (oldScript.src) {
                newScript.src = oldScript.src;
            } else {
                newScript.textContent = oldScript.textContent;
            }
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }
    setLoading(sectionId, loading) {
        const section = document.getElementById(sectionId);
        if (section) {
            if (loading) {
                section.classList.add('loading');
            } else {
                section.classList.remove('loading');
            }
        }
    }

    /**
     * Compatibility method: disable/enable all form elements in a container
     */
    _disableEnableAll(element, isDisabled) {
        if (!element) return;
        element.querySelectorAll('button, input, select, textarea').forEach(el => {
            el.disabled = isDisabled;
        });
    }

    /**
     * Clear all custom error messages
     */
    clearErrors() {
        document.querySelectorAll('.onestep-checkout .validation-advice.custom-error').forEach(el => el.remove());
        document.querySelectorAll('.onestep-checkout .validation-failed.custom-error').forEach(el => el.classList.remove('validation-failed', 'custom-error'));
    }

    showError(message, element = null) {
        if (Array.isArray(message)) {
            message = message.join('<br>');
        }

        // Create error message element using standard validation-advice class
        const errorDiv = document.createElement('div');
        errorDiv.className = 'validation-advice custom-error';
        errorDiv.innerHTML = message;

        // If element provided, show error near it
        if (element) {
            element.classList.add('validation-failed', 'custom-error');
            // Find the closest container for this specific element (not the whole section)
            const parent = element.closest('li') || element.closest('.checkbox') || element.closest('label')?.parentElement || element.parentElement;
            if (parent) {
                // Insert after the parent element
                parent.insertAdjacentElement('afterend', errorDiv);
            }
        } else {
            // Show at top of checkout
            const checkout = document.getElementById('onestep-checkout');
            if (checkout) {
                checkout.insertBefore(errorDiv, checkout.firstChild);
            }
        }
    }

    // =============================================
    // Compatibility methods for existing checkout scripts
    // These methods are called by Billing, Shipping, etc. classes
    // =============================================

    /**
     * Called by step classes to navigate to next section
     * In one-step, we don't navigate - all sections visible
     */
    gotoSection(section, reloadProgressBlock) {
        this.currentStep = section;
        // In one-step, don't hide/show sections - just refresh data
        if (section === 'review' || section === 'payment') {
            this.loadReview();
        }
    }

    /**
     * Handle AJAX response from step save methods
     */
    setStepResponse(response) {
        if (response.error) {
            this.showError(response.message || response.error);
            return false;
        }

        // Update section content if provided
        if (response.update_section) {
            const sectionName = response.update_section.name;
            const html = response.update_section.html;

            if (sectionName === 'shipping-method') {
                this.updateShippingMethods(html);
            } else if (sectionName === 'payment-method') {
                this.updatePaymentMethods(html);
            } else if (sectionName === 'review') {
                const container = document.getElementById('checkout-review-load');
                if (container) container.innerHTML = html;
            }
        }

        // Handle section navigation
        if (response.goto_section) {
            this.gotoSection(response.goto_section, true);
        }

        // Refresh review on any successful update
        this.loadReview();

        return true;
    }

    /**
     * Show/hide loading indicator for a step
     * Compatible with original Checkout.setLoadWaiting
     */
    setLoadWaiting(step, keepDisabled) {
        // Map step names to our section IDs
        const sectionMap = {
            'billing': 'onestep-billing',
            'shipping': 'onestep-shipping',
            'shipping_method': 'onestep-shipping-method',
            'payment': 'onestep-payment',
            'review': 'onestep-review'
        };

        if (step) {
            // If already loading something, clear it first
            if (this.loadWaiting) {
                this.setLoadWaiting(false);
            }

            const sectionId = sectionMap[step];
            if (sectionId) {
                this.setLoading(sectionId, true);
            }

            // Handle original checkout's buttons-container and please-wait elements
            const container = document.getElementById(`${step}-buttons-container`);
            if (container) {
                container.classList.add('disabled');
                container.style.opacity = '0.5';
                this._disableEnableAll(container, true);
            }
            const pleaseWait = document.getElementById(`${step}-please-wait`);
            if (pleaseWait) {
                pleaseWait.style.display = 'block';
            }
        } else {
            // Clear loading state
            if (this.loadWaiting) {
                const sectionId = sectionMap[this.loadWaiting];
                if (sectionId) {
                    this.setLoading(sectionId, false);
                }

                // Handle original checkout's buttons-container and please-wait elements
                const container = document.getElementById(`${this.loadWaiting}-buttons-container`);
                if (container) {
                    if (!keepDisabled) {
                        container.classList.remove('disabled');
                        container.style.opacity = '1';
                    }
                    this._disableEnableAll(container, !!keepDisabled);
                }
                const pleaseWait = document.getElementById(`${this.loadWaiting}-please-wait`);
                if (pleaseWait) {
                    pleaseWait.style.display = 'none';
                }
            }
        }

        this.loadWaiting = step;
    }

    /**
     * Reload the progress block (sidebar in regular checkout)
     * In one-step, we just refresh the review
     */
    reloadProgressBlock(toStep) {
        this.loadReview();
    }

    /**
     * Reload specific step content
     */
    reloadStep(step) {
        // In one-step, we typically don't need to reload individual steps
        // But we can refresh the review
        this.loadReview();
    }

    /**
     * Reload the review block
     */
    reloadReviewBlock() {
        this.loadReview();
    }

    /**
     * Get URLs object (for compatibility)
     */
    get progressUrl() { return this.urls.progress; }
    get reviewUrl() { return this.urls.review; }
    get failureUrl() { return this.urls.failure; }
}

// Make it globally available
window.OneStepCheckout = OneStepCheckout;
