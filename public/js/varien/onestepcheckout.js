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
            estimateBilling: config.estimateBilling
        };
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

        this.init();
    }

    init() {
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', () => {
            this.initForms();
            this.initBillingShippingToggle();
            this.initAutoSave();
            this.initPlaceholders();
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
        // Auto-save billing address on any field change
        // The saveBilling() method will only actually save when City, State, Zip, Country are filled
        if (this.billingForm) {
            this.billingForm.addEventListener('change', (e) => {
                const target = e.target;
                // Trigger auto-save for input, select, and textarea elements
                if (target.matches('input, select, textarea')) {
                    this.debouncedSaveBilling();
                }
            });
            this.billingForm.addEventListener('blur', (e) => {
                const target = e.target;
                // Trigger auto-save on blur for text inputs
                if (target.matches('input[type="text"], input[type="email"], input[type="tel"], textarea')) {
                    this.debouncedSaveBilling();
                }
            }, true); // Use capture phase to catch blur events
        }

        // Auto-save shipping address on any field change (when using different shipping address)
        if (this.shippingForm) {
            this.shippingForm.addEventListener('change', (e) => {
                const target = e.target;
                if (target.matches('input, select, textarea')) {
                    this.debouncedSaveShipping();
                }
            });
            this.shippingForm.addEventListener('blur', (e) => {
                const target = e.target;
                if (target.matches('input[type="text"], input[type="email"], input[type="tel"], textarea')) {
                    this.debouncedSaveShipping();
                }
            }, true);
        }

        // Auto-save shipping method on change
        this.initShippingMethodAutoSave();

        // Auto-save payment method on change
        this.initPaymentMethodAutoSave();

        // Update Place Order button on form field changes
        this.initButtonStateListeners();
    }

    /**
     * Add listeners to form fields to update Place Order button state in real-time
     */
    initButtonStateListeners() {
        const updateButton = () => this.updatePlaceOrderButton();

        // Listen to all input/select changes in billing form
        if (this.billingForm) {
            this.billingForm.addEventListener('input', updateButton);
            this.billingForm.addEventListener('change', updateButton);
        }

        // Listen to all input/select changes in shipping form
        if (this.shippingForm) {
            this.shippingForm.addEventListener('input', updateButton);
            this.shippingForm.addEventListener('change', updateButton);
        }

        // Listen to all input/select changes in payment form
        if (this.paymentForm) {
            this.paymentForm.addEventListener('input', updateButton);
            this.paymentForm.addEventListener('change', updateButton);
        }
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

    async saveBilling() {
        if (!this.billingForm || this.isVirtual) return;

        // Check if using billing address for shipping or separate shipping address
        const useForShippingYes = document.getElementById('billing:use_for_shipping_yes');
        const useForShipping = useForShippingYes ? useForShippingYes.checked : true;

        // Determine which form to check for shipping fields
        let addressForm, fieldPrefix;
        if (useForShipping) {
            addressForm = this.billingForm;
            fieldPrefix = 'billing';
        } else {
            // Using separate shipping address - check shipping form instead
            addressForm = this.shippingForm;
            fieldPrefix = 'shipping';
            if (!addressForm) return;
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

        try {
            this.setLoading('onestep-shipping-method', true);

            // Use estimateBilling endpoint - saves billing to quote and returns shipping methods
            const formData = new FormData(this.billingForm);

            const response = await fetch(this.urls.estimateBilling, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success && result.update_section) {
                this.updateShippingMethods(result.update_section.html);
            }
        } catch (error) {
            // Silently ignore errors during auto-save
        } finally {
            this.setLoading('onestep-shipping-method', false);
        }
    }

    async saveShipping() {
        if (!this.shippingForm || this.isVirtual) return;

        const formData = new FormData(this.shippingForm);

        try {
            this.setLoading('onestep-shipping', true);

            const result = await mahoFetch(this.urls.saveShipping, {
                method: 'POST',
                body: formData,
                loaderArea: false
            });

            // Refresh review
            this.loadReview();
        } catch (error) {
            // Silently handle errors
        } finally {
            this.setLoading('onestep-shipping', false);
        }
    }

    /**
     * Save shipping address estimate when using different shipping address
     */
    async saveShippingEstimate() {
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

        try {
            this.setLoading('onestep-shipping-method', true);

            // Use cart's estimatePost for shipping address estimation
            const estimateData = new FormData();
            estimateData.append('country_id', country);
            estimateData.append('estimate_postcode', postcode);
            estimateData.append('estimate_city', city);
            estimateData.append('region_id', regionId || '');
            estimateData.append('region', document.getElementById('shipping:region')?.value || '');
            estimateData.append('isAjax', '1');

            const estimateResponse = await fetch(this.urls.estimateBilling.replace('estimateBilling', 'cart/estimatePost'), {
                method: 'POST',
                body: estimateData
            });

            const estimateResult = await estimateResponse.json();

            if (estimateResult.success) {
                // Load shipping methods from onepage checkout
                const shippingResponse = await fetch(this.urls.saveBilling.replace('saveBilling', 'shippingMethod'));
                const shippingHtml = await shippingResponse.text();

                if (shippingHtml) {
                    this.updateShippingMethods(shippingHtml);
                }
            }
        } catch (error) {
            // Silently ignore errors during auto-save
        } finally {
            this.setLoading('onestep-shipping-method', false);
        }
    }

    initShippingMethodAutoSave() {
        // Listen for shipping method selection changes
        const container = document.getElementById('checkout-shipping-method-load');
        if (!container) return;

        container.addEventListener('change', (e) => {
            if (e.target.name === 'shipping_method') {
                this.updatePlaceOrderButton();
                this.saveShippingMethod();
            }
        });
    }

    async saveShippingMethod() {
        const selected = document.querySelector('input[name="shipping_method"]:checked');
        if (!selected) return;

        // Use the actual form to include form key
        const shippingMethodForm = document.getElementById('co-shipping-method-form');
        if (!shippingMethodForm) return;

        const formData = new FormData(shippingMethodForm);

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
            // Refresh review
            this.loadReview();
        } catch (error) {
            // Silently handle errors
        } finally {
            this.setLoading('onestep-shipping-method', false);
        }
    }

    initPaymentMethodAutoSave() {
        // Listen for payment method selection changes
        const container = document.getElementById('checkout-payment-method-load');
        if (!container) return;

        container.addEventListener('change', (e) => {
            if (e.target.name === 'payment[method]') {
                this.updatePlaceOrderButton();
                this.savePayment();
            }
        });
    }

    async savePayment() {
        const paymentForm = document.getElementById('co-payment-form');
        if (!paymentForm) return;

        const formData = new FormData(paymentForm);

        try {
            this.setLoading('onestep-payment', true);

            const result = await mahoFetch(this.urls.savePayment, {
                method: 'POST',
                body: formData,
                loaderArea: false
            });

            // Refresh review
            this.loadReview();
        } catch (error) {
            // Silently handle errors
        } finally {
            this.setLoading('onestep-payment', false);
        }
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
            // Update button state
            this.updatePlaceOrderButton();
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
            // Update button state
            this.updatePlaceOrderButton();
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

    async loadReview() {
        const container = document.getElementById('checkout-review-load');
        const loader = document.getElementById('onestep-review-please-wait');

        if (!container) return;

        try {
            if (loader) loader.style.display = 'flex';

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

            // Update the Place Order button state based on checkout completion
            this.updatePlaceOrderButton();
        } catch (error) {
            // Silently handle errors - review will load when checkout state is valid
        } finally {
            if (loader) loader.style.display = 'none';
        }
    }

    /**
     * Wrap review.save() to add billing/shipping form validation
     */
    wrapReviewSave() {
        if (typeof review === 'undefined' || !review.save) return;

        const originalSave = review.save.bind(review);
        const self = this;

        review.save = function() {
            // Validate billing form
            if (self.billingForm) {
                const billingValidator = new Validation(self.billingForm);
                if (!billingValidator.validate()) {
                    return;
                }
            }

            // Validate shipping form if visible and using different address
            const useForShippingNo = document.getElementById('billing:use_for_shipping_no');
            if (useForShippingNo && useForShippingNo.checked && self.shippingForm) {
                const shippingValidator = new Validation(self.shippingForm);
                if (!shippingValidator.validate()) {
                    return;
                }
            }

            // Validate shipping method is selected (for non-virtual orders)
            if (!self.isVirtual) {
                const shippingMethodSelected = document.querySelector('input[name="shipping_method"]:checked');
                if (!shippingMethodSelected) {
                    alert(Translator.translate('Please select a shipping method.'));
                    return;
                }
            }

            // Validate payment method is selected
            const paymentMethodSelected = document.querySelector('input[name="payment[method]"]:checked');
            if (!paymentMethodSelected) {
                alert(Translator.translate('Please select a payment method.'));
                return;
            }

            // Validate payment form (for credit card fields, etc.)
            if (self.paymentForm) {
                const paymentValidator = new Validation(self.paymentForm);
                if (!paymentValidator.validate()) {
                    return;
                }
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

    /**
     * Check if checkout is ready to place order
     */
    isCheckoutComplete() {
        // Check billing form required fields have values
        if (this.billingForm && !this.areRequiredFieldsFilled(this.billingForm)) {
            return false;
        }

        // Check shipping form required fields if using different address
        const useForShippingNo = document.getElementById('billing:use_for_shipping_no');
        if (useForShippingNo && useForShippingNo.checked && this.shippingForm) {
            if (!this.areRequiredFieldsFilled(this.shippingForm)) {
                return false;
            }
        }

        // For non-virtual products, check shipping method is selected
        if (!this.isVirtual) {
            const shippingMethodSelected = document.querySelector('input[name="shipping_method"]:checked');
            if (!shippingMethodSelected) {
                return false;
            }
        }

        // Check payment method is selected
        const paymentMethodSelected = document.querySelector('input[name="payment[method]"]:checked');
        if (!paymentMethodSelected) {
            return false;
        }

        // Check payment form required fields have values
        if (this.paymentForm && !this.areRequiredFieldsFilled(this.paymentForm)) {
            return false;
        }

        return true;
    }

    /**
     * Check if all required fields in a form have values (without showing validation errors)
     */
    areRequiredFieldsFilled(form) {
        const requiredFields = form.querySelectorAll('.required-entry, [required]');
        for (const field of requiredFields) {
            // Skip hidden fields
            if (field.offsetParent === null) continue;

            const value = field.value?.trim();
            if (!value) {
                return false;
            }
        }
        return true;
    }

    /**
     * Update the Place Order button enabled/disabled state
     */
    updatePlaceOrderButton() {
        const button = document.querySelector('.btn-checkout');
        if (!button) return;

        const isComplete = this.isCheckoutComplete();

        if (isComplete) {
            button.disabled = false;
            button.classList.remove('disabled');
        } else {
            button.disabled = true;
            button.classList.add('disabled');
        }
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

    showError(message) {
        if (Array.isArray(message)) {
            message = message.join('\n');
        }
        alert(message);
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

        const sectionId = sectionMap[step];
        if (sectionId) {
            this.setLoading(sectionId, !!step && !keepDisabled);
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
     * Place the order
     */
    async placeOrder() {
        // Validate required fields before placing order
        if (!this.isCheckoutComplete()) {
            this.showError('Please select shipping and payment methods before placing your order.');
            return;
        }

        // Validate billing form
        if (this.billingForm && typeof this.billingForm.checkValidity === 'function') {
            if (!this.billingForm.checkValidity()) {
                this.billingForm.reportValidity();
                return;
            }
        }

        // Check agreements (terms and conditions)
        const agreements = document.querySelectorAll('.checkout-agreements input[type="checkbox"]');
        for (const agreement of agreements) {
            if (!agreement.checked) {
                this.showError('Please agree to all the terms and conditions before placing your order.');
                return;
            }
        }

        // Get the payment form
        const paymentForm = document.getElementById('co-payment-form');
        if (!paymentForm) {
            this.showError('Payment form not found.');
            return;
        }

        // Collect all form data
        const formData = new FormData();

        // Add billing data
        if (this.billingForm) {
            for (const [key, value] of new FormData(this.billingForm)) {
                formData.append(key, value);
            }
        }

        // Add payment data
        for (const [key, value] of new FormData(paymentForm)) {
            formData.append(key, value);
        }

        // Add agreements
        agreements.forEach((agreement, index) => {
            if (agreement.checked) {
                formData.append(agreement.name, agreement.value);
            }
        });

        try {
            // Disable button and show loading
            const button = document.querySelector('.btn-checkout');
            if (button) {
                button.disabled = true;
                button.classList.add('disabled');
            }
            this.setLoading('onestep-review', true);

            const result = await mahoFetch(this.urls.saveOrder, {
                method: 'POST',
                body: formData,
                loaderArea: false
            });

            if (result.success || result.redirect) {
                // Order placed successfully - redirect to success page
                window.location.href = result.redirect || this.urls.successUrl;
            } else if (result.error) {
                this.showError(result.message || result.error_messages || 'An error occurred while placing your order.');
                if (button) {
                    button.disabled = false;
                    button.classList.remove('disabled');
                }
            }
        } catch (error) {
            this.showError(error.message || 'An error occurred while placing your order. Please try again.');
            const button = document.querySelector('.btn-checkout');
            if (button) {
                button.disabled = false;
                button.classList.remove('disabled');
            }
        } finally {
            this.setLoading('onestep-review', false);
        }
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
