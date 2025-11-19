/**
 * Maho
 *
 * @package    base_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class OrderReviewController {
    constructor(orderForm, orderFormSubmit, shippingSelect, shippingSubmitForm, shippingResultId, shippingSubmit) {
        this._canSubmitOrder = false;
        this._pleaseWait = false;
        this.shippingSelect = false;
        this.reloadByShippingSelect = false;
        this._copyElement = false;
        this.onSubmitShippingSuccess = false;
        this.shippingMethodsUpdateUrl = false;
        this._updateShippingMethods = false;
        this._ubpdateOrderButton = false;
        this.shippingMethodsContainer = false;
        this._submitUpdateOrderUrl = false;
        this._itemsGrid = false;

        if (!orderForm) return;

        this.form = orderForm;

        if (orderFormSubmit) {
            this.formSubmit = orderFormSubmit;
            orderFormSubmit.addEventListener('click', this._submitOrder.bind(this));
        }

        if (shippingSubmitForm) {
            this.reloadByShippingSelect = true;
            if (shippingSubmitForm && shippingSelect) {
                this.shippingSelect = shippingSelect;
                shippingSelect.addEventListener('change',
                    (e) => this._submitShipping(e, shippingSubmitForm.action, shippingResultId)
                );
                this._updateOrderSubmit(false);
            } else {
                this._canSubmitOrder = true;
            }
        } else {
            Array.from(this.form.elements).forEach(element => this._bindElementChange(element));

            if (shippingSelect && document.getElementById(shippingSelect)) {
                this.shippingSelect = document.getElementById(shippingSelect).id;
                this.shippingMethodsContainer = document.getElementById(this.shippingSelect).closest('div');
            } else {
                this.shippingSelect = shippingSelect;
            }
            this._updateOrderSubmit(false);
        }
    }

    addPleaseWait(element) {
        if (element) {
            this._pleaseWait = element;
        }
    }

    async _submitShipping(event, url, resultId) {
        if (this.shippingSelect && url && resultId) {
            this._updateOrderSubmit(true);

            if (this._pleaseWait) {
                this._pleaseWait.style.display = 'block';
            }

            if (this.shippingSelect.value !== '') {
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            isAjax: true,
                            shipping_method: this.shippingSelect.value
                        })
                    });

                    const html = await response.text();
                    document.getElementById(resultId).innerHTML = html;

                    if (this._pleaseWait) {
                        this._pleaseWait.style.display = 'none';
                    }

                    this._onSubmitShippingSuccess();
                } catch (error) {
                    console.error('Shipping submission error:', error);
                }
            }
        }
    }

    setUpdateButton(element, url, resultId) {
        if (element) {
            this._ubpdateOrderButton = element;
            this._submitUpdateOrderUrl = url;
            this._itemsGrid = resultId;

            element.addEventListener('click',
                (e) => this._submitUpdateOrder(e, url, resultId)
            );

            if(this.shippingSelect) {
                this._updateShipping();
            }

            this._updateOrderSubmit(!this._validateForm());
            this.formValidator.reset();
            this._clearValidation('');
        }
    }

    setCopyElement(element) {
        if (element) {
            this._copyElement = element;
            element.addEventListener('click', this._copyShippingToBilling.bind(this));
            this._copyShippingToBilling();
        }
    }

    setShippingAddressContainer(element) {
        if (element) {
            Array.from(element.elements).forEach(input => {
                if (input.type.toLowerCase() === 'radio' || input.type.toLowerCase() === 'checkbox') {
                    input.addEventListener('click', this._onShippingChange.bind(this));
                } else {
                    input.addEventListener('change', this._onShippingChange.bind(this));
                }
            });
        }
    }

    setShippingMethodContainer(element) {
        if (element) {
            this.shippingMethodsContainer = element;
        }
    }

    _copyElementValue(el) {
        const newId = el.id.replace('shipping:', 'billing:');
        const newElement = document.getElementById(newId);

        if (newId && newElement && newElement.type !== 'hidden') {
            newElement.value = el.value;
            newElement.setAttribute('readonly', 'readonly');
            newElement.classList.add('local-validation');
            newElement.style.opacity = 0.5;
            newElement.disabled = true;
        }
    }

    _copyShippingToBilling(event) {
        if (!this._copyElement) return;

        if (this._copyElement.checked) {
            this._copyElementValue(document.getElementById('shipping:country_id'));
            billingRegionUpdater.update();

            document.querySelectorAll('[id^="shipping:"]')
                .forEach(el => this._copyElementValue(el));

            this._clearValidation('billing');
        } else {
            const billingElements = document.querySelectorAll('[id^="billing:"]');
            billingElements.forEach(el => {
                el.disabled = false;
                el.removeAttribute('readonly');
                el.classList.remove('local-validation');
                el.style.opacity = 1;
            });
        }

        if (event) {
            this._updateOrderSubmit(true);
        }
    }

    async _submitUpdateOrder(event, url, resultId) {
        this._copyShippingToBilling();

        if (url && resultId && this._validateForm()) {
            if (this._copyElement?.checked) {
                this._clearValidation('billing');
            }

            this._updateOrderSubmit(true);

            if (this._pleaseWait) {
                this._pleaseWait.style.display = 'block';
            }

            this._toggleButton(this._ubpdateOrderButton, true);

            document.querySelectorAll('[id^="billing:"]').forEach(el => el.disabled = false);

            const formData = new FormData(this.form);
            const formObject = Object.fromEntries(formData);
            formObject.isAjax = true;

            if (this._copyElement.checked) {
                document.querySelectorAll('[id^="billing:"]').forEach(el => el.disabled = true);
                this._copyElement.disabled = false;
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formObject)
                });

                const html = await response.text();
                document.getElementById(resultId).innerHTML = html;

                if (this._pleaseWait && !this._updateShippingMethods) {
                    this._pleaseWait.style.display = 'none';
                }

                this._toggleButton(this._ubpdateOrderButton, false);
                this._updateShippingMethodsElement();
            } catch (error) {
                console.error('Update order error:', error);
            }
        } else {
            if (this._copyElement?.checked) {
                this._clearValidation('billing');
            }
        }
    }

    async _updateShippingMethodsElement() {
        if (this._updateShippingMethods) {
            try {
                const response = await fetch(this.shippingMethodsUpdateUrl);
                const html = await response.text();
                const container = document.getElementById(this.shippingMethodsContainer).parentElement;
                container.innerHTML = html;
                this._updateShipping();
                this._onSubmitShippingSuccess();
            } catch (error) {
                console.error('Shipping methods update error:', error);
            }
        } else {
            this._onSubmitShippingSuccess();
        }
    }

    _updateShipping() {
        const shippingSelectElement = document.getElementById(this.shippingSelect);
        if (shippingSelectElement) {
            shippingSelectElement.disabled = false;

            // Remove old event listeners
            const newElement = shippingSelectElement.cloneNode(true);
            shippingSelectElement.parentNode.replaceChild(newElement, shippingSelectElement);

            this._bindElementChange(newElement);
            newElement.addEventListener('change',
                (e) => this._submitUpdateOrder(e, this._submitUpdateOrderUrl, this._itemsGrid)
            );

            document.getElementById(`${this.shippingSelect}_update`).style.display = 'none';
            newElement.style.display = 'block';
        }

        this._updateShippingMethods = false;

        if (this._pleaseWait) {
            this._pleaseWait.style.display = 'none';
        }
    }

    _validateForm() {
        if (!this.form) return false;
        if (!this.formValidator) {
            this.formValidator = new Validation(this.form);
        }
        return this.formValidator.validate();
    }

    _onShippingChange(event) {
        const element = event.target;
        const shippingSelectElement = document.getElementById(this.shippingSelect);

        if (element !== shippingSelectElement && !(shippingSelectElement?.disabled)) {
            if (shippingSelectElement) {
                shippingSelectElement.disabled = true;
                shippingSelectElement.style.display = 'none';

                const advice = document.getElementById(`advice-required-entry-${this.shippingSelect}`);
                if (advice) {
                    advice.style.display = 'none';
                }
            }

            if (this.shippingMethodsContainer) {
                const container = document.getElementById(this.shippingMethodsContainer);
                if (container) container.style.display = 'none';
            }

            const updateElement = document.getElementById(`${this.shippingSelect}_update`);
            if (this.shippingSelect && updateElement) {
                updateElement.style.display = 'block';
            }

            this._updateShippingMethods = true;
        }
    }

    _bindElementChange(input) {
        input.addEventListener('change', this._onElementChange.bind(this));
    }

    _onElementChange() {
        this._updateOrderSubmit(true);
    }

    _clearValidation(idprefix) {
        if (idprefix) {
            document.querySelectorAll(`[id*="${idprefix}:"]`).forEach(el => {
                const parent = el.parentElement;
                parent.classList.remove('validation-failed', 'validation-passed', 'validation-error');
            });
        } else {
            this.formValidator.reset();
        }

        const selector = idprefix ? `.validation-advice[id*="${idprefix}:"]` : '.validation-advice';
        document.querySelectorAll(selector).forEach(el => el.remove());

        document.querySelectorAll(`.validation-failed${idprefix}`).forEach(el =>
            el.classList.remove('validation-failed')
        );
        document.querySelectorAll(`.validation-passed${idprefix}`).forEach(el =>
            el.classList.remove('validation-passed')
        );
        document.querySelectorAll(`.validation-error${idprefix}`).forEach(el =>
            el.classList.remove('validation-error')
        );
    }

    _submitOrder() {
        if (this._canSubmitOrder && (this.reloadByShippingSelect || this._validateForm())) {
            this.form.submit();
            this._updateOrderSubmit(true);

            if (this._ubpdateOrderButton) {
                this._ubpdateOrderButton.classList.add('no-checkout');
                this._ubpdateOrderButton.style.opacity = 0.5;
            }

            if (this._pleaseWait) {
                this._pleaseWait.style.display = 'block';
            }
            return;
        }
        this._updateOrderSubmit(true);
    }

    _onSubmitShippingSuccess() {
        this._updateOrderSubmit(false);
        if (this.onSubmitShippingSuccess) {
            this.onSubmitShippingSuccess();
        }
    }

    _updateOrderSubmit(shouldDisable) {
        const isDisabled = shouldDisable || (
            this.reloadByShippingSelect &&
            (!this.shippingSelect || this.shippingSelect.value === '')
        );

        this._canSubmitOrder = !isDisabled;

        if (this.formSubmit) {
            this._toggleButton(this.formSubmit, isDisabled);
        }
    }

    _toggleButton(button, disable) {
        button.disabled = disable;
        button.classList.remove('no-checkout');
        button.style.opacity = 1;

        if (disable) {
            button.classList.add('no-checkout');
            button.style.opacity = 0.5;
        }
    }
}
