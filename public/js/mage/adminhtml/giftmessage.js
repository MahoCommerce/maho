/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class GiftMessagesController {
    static toogleRequired(source, objects) {
        const sourceElement = document.getElementById(source);
        const isRequired = sourceElement?.value.trim() !== '';

        objects.forEach(item => {
            const element = document.getElementById(item);
            if (!element) return;

            element.classList.toggle('required-entry', isRequired);
            const label = this.findFieldLabel(element);

            if (isRequired && label && !label.querySelector('span')) {
                label.insertAdjacentHTML('beforeend', '&nbsp;<span class="required">*</span>');
            } else if (!isRequired && label) {
                if (sourceElement?.formObj?.validator) sourceElement.formObj.validator.reset(item);
                label.querySelector('span')?.remove();
                element.advices?.forEach(advice => advice.value?.style && (advice.value.style.display = 'none'));
            }
        });
    }

    static toogleGiftMessage(container) {
        const containerElement = document.getElementById(container);
        if (!containerElement) return false;

        const isToggling = !containerElement.toogleGiftMessage;
        containerElement.toogleGiftMessage = isToggling;

        if (isToggling) {
            this.showGiftMessageEdit(containerElement, container);
        } else {
            return this.submitGiftMessage(containerElement, container);
        }
        return false;
    }

    static showGiftMessageEdit(containerElement, container) {
        document.getElementById(this.getFieldId(container, 'edit')).style.display = 'block';
        containerElement.querySelector('.action-link')?.classList.add('open');
        const defaultText = containerElement.querySelector('.default-text');
        const closeText = containerElement.querySelector('.close-text');
        if (defaultText) defaultText.style.display = 'none';
        if (closeText) closeText.style.display = 'block';
    }

    static submitGiftMessage(containerElement, container) {
        const formElement = this.setupFormValidation(container);
        if (!formElement?.validator?.validate()) return false;

        mahoFetch(formElement.action, { method: 'POST', body: new FormData(formElement) })
            .then(responseText => this.handleGiftMessageResponse(containerElement, container, responseText));
        return false;
    }

    static handleGiftMessageResponse(containerElement, container, responseText) {
        containerElement.querySelector('.action-link')?.classList.remove('open');
        const defaultText = containerElement.querySelector('.default-text');
        const closeText = containerElement.querySelector('.close-text');
        if (defaultText) defaultText.style.display = 'block';
        if (closeText) closeText.style.display = 'none';
        document.getElementById(this.getFieldId(container, 'edit')).style.display = 'none';

        const hasMessage = responseText.includes('YES');
        const editLink = defaultText?.querySelector('.edit');
        const addLink = defaultText?.querySelector('.add');
        if (editLink) editLink.style.display = hasMessage ? 'block' : 'none';
        if (addLink) addLink.style.display = hasMessage ? 'none' : 'block';
    }

    static setupFormValidation(container) {
        const formElement = document.getElementById(container);

        if (formElement && !formElement.validator) {
            if (formElement.tagName === 'FORM') {
                formElement.validator = new Validation(formElement);
            } else {
                const form = formElement.querySelector('form');
                if (form) {
                    form.validator = new Validation(form);
                    return form;
                }
            }
        }

        return formElement;
    }

    static saveGiftMessage(container) {
        const formElement = this.setupFormValidation(container);
        if (formElement?.validator?.validate()) {
            mahoFetch(formElement.action, { method: 'POST', body: new FormData(formElement) });
        }
    }

    static getFieldId(container, name) {
        return `${container}_${name}`;
    }

    static findFieldLabel(field) {
        return field.closest('td')?.previousElementSibling?.querySelector('label') || null;
    }
}

class GiftOptionsPopup {
    constructor() {
        this.bindActionLinks();
    }

    bindActionLinks() {
        document.querySelectorAll('.action-link').forEach(el => {
            el.addEventListener('click', this.showItemGiftOptions.bind(this));
        });
    }

    showItemGiftOptions(event) {
        const itemId = event.target.id.replace('gift_options_link_', '');
        const productTitle = this.getProductTitle(itemId);

        // Store the current item ID for saving later
        this.currentItemId = itemId;

        // Get the gift options form content
        const formContent = this.getGiftOptionsFormContent();

        if (!formContent) {
            console.warn('Gift options form content not found');
            return;
        }

        Dialog.confirm(formContent, {
            title: `Gift Options for ${productTitle}`,
            width: 600,
            height: 400,
            okLabel: 'OK',
            onOk: () => this.validateAndSubmitForm(),
            onOpen: (dialog) => this.setupForm(dialog, itemId),
            className: 'gift-options-dialog'
        });

        event.preventDefault();
    }

    getProductTitle(itemId) {
        return document.getElementById(`order_item_${itemId}_title`)?.textContent?.trim() || 'Product';
    }

    getGiftOptionsFormContent() {
        const formContents = document.getElementById('gift_options_form_contents');
        if (formContents) {
            // Just return the inner HTML content, not the container
            const formWrapper = document.createElement('form');
            formWrapper.id = 'gift_options_configuration_form';
            formWrapper.action = '#';
            formWrapper.method = 'post';
            formWrapper.innerHTML = formContents.innerHTML;

            return formWrapper.outerHTML;
        }
        return null;
    }

    setupForm(dialog, itemId) {
        this.loadExistingValues(dialog, itemId);
        dialog.querySelector('input, select, textarea')?.focus();
    }

    loadExistingValues(dialog, itemId) {
        const dialogForm = dialog.querySelector('form');
        if (!dialogForm) return;

        ['sender', 'recipient', 'message'].forEach(field => {
            const sourceField = document.querySelector(`[name="giftmessage[${itemId}][${field}]"]`);
            const destField = dialogForm.querySelector(`[name="current_item_giftmessage_${field}"]`);

            if (sourceField && destField) {
                destField.value = sourceField.value;
            }
        });
    }

    validateAndSubmitForm() {
        const form = document.getElementById('gift_options_configuration_form');
        if (!form) return true; // Allow dialog to close if no form

        const giftOptionsForm = new varienForm('gift_options_configuration_form');
        giftOptionsForm.canShowError = true;

        if (giftOptionsForm.validate()) {
            // Save the form values back to the original fields first
            this.saveFormValues(form);

            // Now trigger the server-side save if there's a container form
            this.triggerServerSave();

            giftOptionsForm.validator.reset();
            return true; // Allow dialog to close
        }

        return false; // Prevent dialog from closing
    }

    saveFormValues(dialogForm) {
        if (!this.currentItemId) return;

        ['sender', 'recipient', 'message'].forEach(field => {
            const destField = dialogForm.querySelector(`[name="current_item_giftmessage_${field}"]`);
            const sourceField = document.querySelector(`[name="giftmessage[${this.currentItemId}][${field}]"]`);

            if (destField && sourceField) {
                sourceField.value = destField.value;
            }
        });
    }

    triggerServerSave() {
        if (!this.currentItemId) return;

        const sourceField = document.querySelector(`[name="giftmessage[${this.currentItemId}][message]"]`);
        const form = sourceField?.closest('form');
        if (form?.id) {
            GiftMessagesController.saveGiftMessage(form.id);
        }
    }
}
