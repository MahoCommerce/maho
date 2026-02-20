/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2015-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class Checkout
{
    constructor(accordion, urls) {
        this.accordion = accordion;
        this.progressUrl = urls.progress;
        this.reviewUrl = urls.review;
        this.failureUrl = urls.failure;
        this.billingForm = false;
        this.shippingForm = false;
        this.syncBillingShipping = false;
        this.payment = '';
        this.loadWaiting = false;
        this.steps = ['billing', 'shipping', 'shipping_method', 'payment', 'review'];
        this.currentStep = 'billing';

        // Add click listeners to all section titles
        this.accordion.sections.forEach(section => {
            const stepTitle = section.querySelector('.step-title');
            stepTitle.addEventListener('click', (event) => this._onSectionClick(event));
        });

        this.accordion.disallowAccessToNextSections = true;

        this.initialize(...arguments);
    }

    initialize() {
        // Placeholder for 3rd party modules
    }

    /**
     * Section header click handler
     *
     * @param {Event} event
     */
    _onSectionClick(event) {
        const section = event.target.closest('.section');
        if (section.classList.contains('allow')) {
            event.preventDefault();
            const sectionId = section.id.replace('opc-', '');
            this.gotoSection(sectionId, false);
            return false;
        }
    }

    ajaxFailure(error) {
        alert(error);
        location.href = encodeURI(this.failureUrl);
    }

    reloadProgressBlock(toStep) {
        this.reloadStep(toStep);
        if (this.syncBillingShipping) {
            this.syncBillingShipping = false;
            this.reloadStep('shipping');
        }
    }

    async reloadStep(prevStep) {
        try {
            const response = await fetch(this.progressUrl + (prevStep ? `?prevStep=${prevStep}` : ''));
            if (!response.ok) {
                throw new Error(`Server returned status ${response.status}`);
            }

            const html = await response.text();
            const prevStepDiv = document.getElementById(`${prevStep}-progress-opcheckout`);
            if (prevStepDiv) {
                prevStepDiv.innerHTML = html;
            }

            this.resetPreviousSteps();
        } catch (error) {
            this.ajaxFailure(error);
        }
    }

    async reloadReviewBlock() {
        try {
            const response = await fetch(this.reviewUrl);
            if (!response.ok) {
                throw new Error(`Server returned status ${response.status}`);
            }

            const html = await response.text();
            document.getElementById('checkout-review-load').innerHTML = html;
        } catch (error) {
            this.ajaxFailure(error);
        }
    }

    _disableEnableAll(element, isDisabled) {
        const descendants = element.querySelectorAll('*');
        descendants.forEach(descendant => {
            descendant.disabled = isDisabled;
        });
        element.disabled = isDisabled;
    }

    setLoadWaiting(step, keepDisabled) {
        let container;
        if (step) {
            if (this.loadWaiting) {
                this.setLoadWaiting(false);
            }
            container = document.getElementById(`${step}-buttons-container`);
            container.classList.add('disabled');
            container.style.opacity = 0.5;
            this._disableEnableAll(container, true);
            document.getElementById(`${step}-please-wait`).style.display = 'block';
        } else {
            if (this.loadWaiting) {
                container = document.getElementById(`${this.loadWaiting}-buttons-container`);
                const isDisabled = keepDisabled ? true : false;
                if (!isDisabled) {
                    container.classList.remove('disabled');
                    container.style.opacity = 1;
                }
                this._disableEnableAll(container, isDisabled);
                document.getElementById(`${this.loadWaiting}-please-wait`).style.display = 'none';
            }
        }
        this.loadWaiting = step;
    }

    gotoSection(section, reloadProgressBlock) {
        if (reloadProgressBlock) {
            this.reloadProgressBlock(this.currentStep);
        }
        this.currentStep = section;
        const sectionElement = document.getElementById(`opc-${section}`);
        sectionElement.classList.add('allow');
        this.accordion.openSection(`opc-${section}`);
        if (!reloadProgressBlock) {
            this.resetPreviousSteps();
        }

        const checkoutSteps = document.getElementById('checkoutSteps');
        if (checkoutSteps) checkoutSteps.scrollIntoView();
    }

    resetPreviousSteps() {
        const stepIndex = this.steps.indexOf(this.currentStep);
        for (let i = stepIndex; i < this.steps.length; i++) {
            const nextStep = this.steps[i];
            const progressDiv = document.getElementById(`${nextStep}-progress-opcheckout`);
            if (progressDiv) {
                progressDiv.querySelectorAll('dt').forEach(el => el.classList.remove('complete'));
                progressDiv.querySelectorAll('dd.complete').forEach(el => el.remove());
            }
        }
    }

    changeSection(section) {
        const changeStep = section.replace('opc-', '');
        this.gotoSection(changeStep, false);
    }

    setBilling() {
        const useForShippingYes = document.getElementById('billing:use_for_shipping_yes');
        const useForShippingNo = document.getElementById('billing:use_for_shipping_no');
        const sameAsBilling = document.getElementById('shipping:same_as_billing');
        const opcShipping = document.getElementById('opc-shipping');

        if (useForShippingYes && useForShippingYes.checked) {
            shipping.syncWithBilling();
            opcShipping.classList.add('allow');
            this.gotoSection('shipping_method', true);
        }
        else if (useForShippingNo && useForShippingNo.checked) {
            sameAsBilling.checked = false;
            this.gotoSection('shipping', true);
        }
        else {
            sameAsBilling.checked = true;
            this.gotoSection('shipping', true);
        }
    }

    setShipping() {
        this.gotoSection('shipping_method', true);
    }

    setShippingMethod() {
        this.gotoSection('payment', true);
    }

    setPayment() {
        this.gotoSection('review', true);
    }

    setReview() {
        this.reloadProgressBlock();
    }

    back() {
        if (this.loadWaiting) return;

        // Navigate back to the previous available step
        let stepIndex = this.steps.indexOf(this.currentStep);
        let section = this.steps[--stepIndex];
        let sectionElement = document.getElementById(`opc-${section}`);

        // Traverse back to find the available section. Ex Virtual product does not have shipping section
        while (sectionElement === null && stepIndex > 0) {
            --stepIndex;
            section = this.steps[stepIndex];
            sectionElement = document.getElementById(`opc-${section}`);
        }

        this.changeSection(`opc-${section}`);
    }

    setStepResponse(response) {
        if (response.update_section) {
            const element = document.getElementById(`checkout-${response.update_section.name}-load`);
            updateElementHtmlAndExecuteScripts(element, response.update_section.html);
        }

        if (response.allow_sections) {
            response.allow_sections.forEach(section => {
                document.getElementById(`opc-${section}`).classList.add('allow');
            });
        }

        if (response.duplicateBillingInfo) {
            this.syncBillingShipping = true;
            shipping.setSameAsBilling(true);
        }

        if (response.goto_section) {
            this.gotoSection(response.goto_section, true);
            return true;
        }

        if (response.redirect) {
            location.href = encodeURI(response.redirect);
            return true;
        }

        return false;
    }
}

class Billing {
    constructor(form, addressUrl, saveUrl) {
        this.form = form;
        const formElement = document.getElementById(this.form);
        if (formElement) {
            formElement.addEventListener('submit', (event) => {
                this.save();
                event.preventDefault();
            });
        }
        this.addressUrl = addressUrl;
        this.saveUrl = saveUrl;
        this.onSave = this.nextStep.bind(this);
        this.onComplete = this.resetLoadWaiting.bind(this);

        this.initialize(...arguments);
    }

    initialize() {
        // Placeholder for 3rd party modules
    }

    newAddress(isNew) {
        const billingForm = document.getElementById('billing-new-address-form');
        if (isNew) {
            this.resetSelectedAddress();
            billingForm.style.display = 'block';
        } else {
            billingForm.style.display = 'none';
        }
    }

    resetSelectedAddress() {
        const selectElement = document.getElementById('billing-address-select');
        if (selectElement) {
            selectElement.value = '';
        }
    }

    setUseForShipping(flag) {
        document.getElementById('shipping:same_as_billing').checked = flag;
    }

    async save() {
        if (checkout.loadWaiting !== false) return;

        const validator = new Validation(this.form);
        if (validator.validate()) {
            checkout.setLoadWaiting('billing');

            try {
                const formData = new FormData(document.getElementById(this.form));
                const response = await fetch(this.saveUrl, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Server returned status ${response.status}`);
                }

                const callbackObj = {
                    status: response.status,
                    responseText: await response.text(),
                };

                try {
                    callbackObj.responseJson = JSON.parse(callbackObj.responseText);
                } catch {
                    throw new Error('Server returned invalid JSON');
                }

                this.onSave(callbackObj);
                this.onComplete(callbackObj);

            } catch (error) {
                checkout.ajaxFailure(error);
            }
        }
    }

    resetLoadWaiting(transport) {
        checkout.setLoadWaiting(false);
        const event = new CustomEvent('billing-request:completed', {
            detail: { transport: transport }
        });
        document.body.dispatchEvent(event);
    }

    nextStep(transport) {
        let response = transport.responseJSON || JSON.parse(transport.responseText) || {};

        if (response.error) {
            if (typeof response.message === 'string') {
                alert(stripTags(response.message));
            } else {
                if (window.billingRegionUpdater) {
                    billingRegionUpdater.update();
                }

                let msg = response.message;
                if (Array.isArray(msg)) {
                    alert(msg.join("\n"));
                }
                alert(stripTags(msg));
            }

            return false;
        }

        checkout.setStepResponse(response);
        if (window.payment) {
            payment.initWhatIsCvvListeners();
        }
    }
}

class Shipping {
    constructor(form, addressUrl, saveUrl, methodsUrl) {
        this.form = form;
        const formElement = document.getElementById(this.form);
        if (formElement) {
            formElement.addEventListener('submit', (event) => {
                this.save();
                event.preventDefault();
            });

            const countryElement = formElement.querySelector('#shipping\\:country_id');
            if (countryElement) {
                countryElement.addEventListener('change', () => {
                    if (window.shipping) shipping.setSameAsBilling(false);
                });
            }
        }
        this.addressUrl = addressUrl;
        this.saveUrl = saveUrl;
        this.methodsUrl = methodsUrl;
        this.onSave = this.nextStep.bind(this);
        this.onComplete = this.resetLoadWaiting.bind(this);

        this.initialize(...arguments);
    }

    initialize() {
        // Placeholder for 3rd party modules
    }

    newAddress(isNew) {
        const shippingForm = document.getElementById('shipping-new-address-form');
        if (isNew) {
            this.resetSelectedAddress();
            shippingForm.style.display = 'block';
        } else {
            shippingForm.style.display = 'none';
        }
        shipping.setSameAsBilling(false);
    }

    resetSelectedAddress() {
        const selectElement = document.getElementById('shipping-address-select');
        if (selectElement) {
            selectElement.value = '';
        }
    }

    setSameAsBilling(flag) {
        document.getElementById('shipping:same_as_billing').checked = flag;
        if (flag) {
            this.syncWithBilling();
        }
    }

    syncWithBilling() {
        const billingSelect = document.getElementById('billing-address-select');
        const shippingSelect = document.getElementById('shipping-address-select');

        if (billingSelect) {
            this.newAddress(!billingSelect.value);
        }

        document.getElementById('shipping:same_as_billing').checked = true;

        if (!billingSelect || !billingSelect.value) {
            const formElement = document.getElementById(this.form);
            const formElements = formElement.elements;

            Array.from(formElements).forEach(element => {
                if (element.id) {
                    const sourceField = document.getElementById(element.id.replace(/^shipping:/, 'billing:'));
                    if (sourceField) {
                        element.value = sourceField.value;
                    }
                }
            });

            shippingRegionUpdater.update();
            document.getElementById('shipping:region_id').value = document.getElementById('billing:region_id').value;
            document.getElementById('shipping:region').value = document.getElementById('billing:region').value;
        } else {
            shippingSelect.value = billingSelect.value;
        }
    }

    setRegionValue() {
        document.getElementById('shipping:region').value = document.getElementById('billing:region').value;
    }

    async save() {
        if (checkout.loadWaiting !== false) return;

        const validator = new Validation(this.form);
        if (validator.validate()) {
            checkout.setLoadWaiting('shipping');

            try {
                const formData = new FormData(document.getElementById(this.form));
                const response = await fetch(this.saveUrl, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Server returned status ${response.status}`);
                }

                const callbackObj = {
                    status: response.status,
                    responseText: await response.text(),
                };

                try {
                    callbackObj.responseJson = JSON.parse(callbackObj.responseText);
                } catch {
                    throw new Error('Server returned invalid JSON');
                }

                this.onSave(callbackObj);
                this.onComplete(callbackObj);

            } catch (error) {
                checkout.ajaxFailure(error);
            }
        }
    }

    resetLoadWaiting() {
        checkout.setLoadWaiting(false);
    }

    nextStep(transport) {
        let response = transport.responseJSON || JSON.parse(transport.responseText) || {};

        if (response.error) {
            if (typeof response.message === 'string') {
                alert(stripTags(response.message));
            } else {
                if (window.shippingRegionUpdater) {
                    shippingRegionUpdater.update();
                }
                let msg = response.message;
                if (Array.isArray(msg)) {
                    alert(msg.join("\n"));
                }
                alert(stripTags(msg));
            }
            return false;
        }

        checkout.setStepResponse(response);
    }
}

class ShippingMethod {
    constructor(form, saveUrl) {
        this.form = typeof form === 'string' ? document.getElementById(form) : form;
        this.saveUrl = saveUrl;
        this.onSave = this.nextStep.bind(this);
        this.onComplete = this.resetLoadWaiting.bind(this);

        if (this.form) {
            this.form.addEventListener('submit', (event) => {
                event.preventDefault();
                this.save();
            });
        }

        // Assuming you have a separate validation library
        this.validator = new Validation(this.form);

        this.initialize(...arguments);
    }

    initialize() {
        // Placeholder for 3rd party modules
    }

    validate() {
        const methods = document.getElementsByName('shipping_method');
        if (methods.length === 0) {
            alert(Translator.translate('Your order cannot be completed at this time as there is no shipping methods available for it. Please make necessary changes in your shipping address.'));
            return false;
        }

        if (!this.validator.validate()) {
            return false;
        }

        for (const method of methods) {
            if (method.checked) {
                return true;
            }
        }

        alert(Translator.translate('Please specify shipping method.'));
        return false;
    }

    async save() {
        if (checkout.loadWaiting !== false) return;

        if (this.validate()) {
            checkout.setLoadWaiting('shipping-method');

            try {
                const response = await fetch(this.saveUrl, {
                    method: 'POST',
                    body: new FormData(this.form)
                });

                if (!response.ok) {
                    throw new Error(`Server returned status ${response.status}`);
                }

                const callbackObj = {
                    status: response.status,
                    responseText: await response.text(),
                };

                try {
                    callbackObj.responseJson = JSON.parse(callbackObj.responseText);
                } catch {
                    throw new Error('Server returned invalid JSON');
                }

                this.onSave(callbackObj);
                this.onComplete(callbackObj);

            } catch (error) {
                checkout.ajaxFailure(error);
            }
        }
    }

    resetLoadWaiting() {
        checkout.setLoadWaiting(false);
    }

    nextStep(transport) {
        let response = transport.responseJSON || JSON.parse(transport.responseText) || {};

        if (response.error) {
            alert(response.message);
            return false;
        }

        if (response.update_section) {
            const element = document.getElementById(`checkout-${response.update_section.name}-load`);
            updateElementHtmlAndExecuteScripts(element, response.update_section.html);
        }

        payment.initWhatIsCvvListeners();

        if (response.goto_section) {
            checkout.gotoSection(response.goto_section, true);
            checkout.reloadProgressBlock();
            return;
        }

        if (response.payment_methods_html) {
            document.getElementById('checkout-payment-method-load')
                .innerHTML = response.payment_methods_html;
        }

        checkout.setShippingMethod();
    }
}

class Payment {
    constructor(form, saveUrl) {
        this.form = form;
        this.saveUrl = saveUrl;
        this.beforeInitFunc = new Map();
        this.afterInitFunc = new Map();
        this.beforeValidateFunc = new Map();
        this.afterValidateFunc = new Map();

        this.onSave = this.nextStep.bind(this);
        this.onComplete = this.resetLoadWaiting.bind(this);

        this.initialize(...arguments);
    }

    initialize() {
        // Placeholder for 3rd party modules
    }

    addBeforeInitFunction(code, func) {
        this.beforeInitFunc.set(code, func);
    }

    beforeInit() {
        this.beforeInitFunc.forEach(func => func());
    }

    init() {
        this.beforeInit();

        const form = document.getElementById(this.form);
        const elements = form.elements;
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.save();
        });

        let method = null;
        for (const element of elements) {
            if (element.name === 'payment[method]' || element.name === 'form_key') {
                if (element.checked) {
                    method = element.value;
                }
            } else {
                element.disabled = true;
            }
            element.setAttribute('autocomplete', 'off');
        }

        if (method) this.switchMethod(method);
        this.afterInit();
    }

    addAfterInitFunction(code, func) {
        this.afterInitFunc.set(code, func);
    }

    afterInit() {
        this.afterInitFunc.forEach(func => func());
    }

    switchMethod(method) {
        try {
            // Handle current method
            if (this.currentMethod) {
                const currentForm = document.getElementById('payment_form_' + this.currentMethod);
                if (currentForm) {
                    this.changeVisible(this.currentMethod, true);
                    currentForm.dispatchEvent(new CustomEvent('payment-method:switched-off', {
                        detail: { method_code: this.currentMethod }
                    }));
                }
            }

            // Handle new method
            const newForm = document.getElementById('payment_form_' + method);
            if (newForm) {
                this.changeVisible(method, false);
                newForm.dispatchEvent(new CustomEvent('payment-method:switched', {
                    detail: { method_code: method }
                }));
            } else {
                document.body.dispatchEvent(new CustomEvent('payment-method:switched', {
                    detail: { method_code: method }
                }));
            }

            // Handle free method
            if (method === 'free' &&
                typeof quoteBaseGrandTotal !== 'undefined' &&
                quoteBaseGrandTotal > 0.0001 &&
                !((document.getElementById('use_reward_points')?.checked) ||
                    (document.getElementById('use_customer_balance')?.checked))) {

                const methodElement = document.getElementById('p_method_' + method);
                if (methodElement) {
                    methodElement.checked = false;
                    const dtElement = document.getElementById('dt_method_' + method);
                    const ddElement = document.getElementById('dd_method_' + method);
                    if (dtElement) dtElement.style.display = 'none';
                    if (ddElement) ddElement.style.display = 'none';
                }
                method = '';
            }

            // Update method tracking
            if (method) {
                this.lastUsedMethod = method;
            }
            this.currentMethod = method;
        } catch (error) {
            console.error('Error in switchMethod:', error);
            throw error;
        }
    }

    changeVisible(method, mode) {
        const blocks = [`payment_form_${method}_before`, `payment_form_${method}`, `payment_form_${method}_after`];
        blocks.forEach(blockId => {
            const element = document.getElementById(blockId);
            if (element) {
                element.style.display = mode ? 'none' : '';
                element.querySelectorAll('input, select, textarea, button')
                    .forEach(field => field.disabled = mode);
            }
        });
    }

    addBeforeValidateFunction(code, func) {
        this.beforeValidateFunc.set(code, func);
    }

    beforeValidate() {
        let validateResult = true;
        let hasValidation = false;

        this.beforeValidateFunc.forEach(func => {
            hasValidation = true;
            if (func() === false) {
                validateResult = false;
            }
        });

        return hasValidation ? validateResult : false;
    }

    validate() {
        if (this.beforeValidate()) {
            return true;
        }

        const methods = document.getElementsByName('payment[method]');
        if (methods.length === 0) {
            alert(Translator.translate('Your order cannot be completed at this time as there is no payment methods available for it.'));
            return false;
        }

        if ([...methods].some(method => method.checked)) {
            return true;
        }

        if (this.afterValidate()) {
            return true;
        }

        alert(Translator.translate('Please specify payment method.'));
        return false;
    }

    addAfterValidateFunction(code, func) {
        this.afterValidateFunc.set(code, func);
    }

    afterValidate() {
        let validateResult = true;
        let hasValidation = false;

        this.afterValidateFunc.forEach(func => {
            hasValidation = true;
            if (func() === false) {
                validateResult = false;
            }
        });

        return hasValidation ? validateResult : false;
    }

    async save() {
        if (checkout.loadWaiting !== false) return;

        const validator = new Validation(this.form);
        if (this.validate() && validator.validate()) {
            checkout.setLoadWaiting('payment');

            try {
                const form = document.getElementById(this.form);
                const formData = new FormData(form);
                const urlEncodedData = new URLSearchParams(formData).toString();
                const response = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: urlEncodedData
                });

                if (!response.ok) {
                    throw new Error(`Server returned status ${response.status}`);
                }

                const callbackObj = {
                    status: response.status,
                    responseText: await response.text(),
                };

                try {
                    callbackObj.responseJson = JSON.parse(callbackObj.responseText);
                } catch {
                    throw new Error('Server returned invalid JSON');
                }

                this.onSave(callbackObj);
                this.onComplete(callbackObj);

            } catch (error) {
                checkout.ajaxFailure(error);
            }
        }
    }

    resetLoadWaiting() {
        checkout.setLoadWaiting(false);
    }

    nextStep(transport) {
        let response = transport.responseJSON || JSON.parse(transport.responseText) || {};

        if (response.error) {
            if (response.fields) {
                const fields = response.fields.split(',');
                for (const fieldId of fields) {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        Validation.ajaxError(field, response.error);
                    }
                }
                return;
            }
            alert(typeof response.message === 'string' ? response.message : response.error);
            return;
        }

        checkout.setStepResponse(response);
    }

    initWhatIsCvvListeners() {
        document.querySelectorAll('.cvv-what-is-this')
            .forEach(element => element.addEventListener('click', toggleToolTip));
    }
}

class Review
{
    constructor(saveUrl, successUrl, agreementsForm) {
        this.saveUrl = saveUrl;
        this.successUrl = successUrl;
        this.agreementsForm = agreementsForm;
        this.isSuccess = false;
        this.onSave = this.nextStep.bind(this);
        this.onComplete = this.resetLoadWaiting.bind(this);

        this.initialize(...arguments);
    }

    initialize() {
        // Placeholder for 3rd party modules
    }

    async save() {
        if (checkout.loadWaiting !== false) return;
        checkout.setLoadWaiting('review');

        const form = document.getElementById(payment.form);
        const formData = new FormData(form);
        if (this.agreementsForm) {
            const agreementFormData = new FormData(this.agreementsForm);
            for (let [key, value] of agreementFormData.entries()) {
                formData.append(key, value);
            }
        }
        formData.append('save', true);

        try {
            const response = await fetch(this.saveUrl, {
                method: 'POST',
                body: formData // No Content-Type header needed for FormData
            });

            if (!response.ok) {
                throw new Error(`Server returned status ${response.status}`);
            }

            const callbackObj = {
                status: response.status,
                responseText: await response.text(),
            };

            try {
                callbackObj.responseJson = JSON.parse(callbackObj.responseText);
            } catch {
                throw new Error('Server returned invalid JSON');
            }

            this.onSave(callbackObj);
            this.onComplete(callbackObj);

        } catch (error) {
            checkout.ajaxFailure(error);
        }
    }

    resetLoadWaiting() {
        checkout.setLoadWaiting(false, this.isSuccess);
    }

    nextStep(transport) {
        if (transport) {
            let response = transport.responseJSON || JSON.parse(transport.responseText) || {};

            if (response.redirect) {
                this.isSuccess = true;
                location.href = encodeURI(response.redirect);
                return;
            }

            if (response.success) {
                this.isSuccess = true;
                location.href = encodeURI(this.successUrl);
            } else {
                let msg = response.error_messages;
                if (Array.isArray(msg)) {
                    msg = stripTags(msg.join("\n"));
                }
                if (msg) {
                    alert(msg);
                }
            }

            if (response.update_section) {
                const element = document.getElementById(`checkout-${response.update_section.name}-load`);
                updateElementHtmlAndExecuteScripts(element, response.update_section.html);
            }

            if (response.goto_section) {
                checkout.gotoSection(response.goto_section, true);
            }
        }
    }
}
