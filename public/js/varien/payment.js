/**
 * Maho
 *
 * @category    Varien
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class paymentForm {
    constructor(formId) {
        this.formId = formId;
        this.validator = new Validation(this.formId);
        const elements = document.querySelectorAll(`#${this.formId} *`);

        let method = null;
        for (const element of elements) {
            if (element.name === 'payment[method]' || element.name === 'form_key') {
                if (element.checked) {
                    method = element.value;
                }
            } else if (element.type && element.type.toLowerCase() !== 'submit') {
                element.disabled = true;
            }
            element.setAttribute('autocomplete', 'off');
        }

        if (method) this.switchMethod(method);
    }

    switchMethod(method) {
        const previousForm = document.getElementById(`payment_form_${this.currentMethod}`);
        if (previousForm) {
            previousForm.style.display = 'none';
            Array.from(previousForm.querySelectorAll('input, select'), element => element.disabled = true);
        }

        const newForm = document.getElementById(`payment_form_${method}`);
        if (newForm) {
            newForm.style.display = '';
            Array.from(newForm.querySelectorAll('input, select'), element => element.disabled = false);
            this.currentMethod = method;
        }
    }
}