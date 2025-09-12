/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
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
            .then(response => response.text())
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
        const messageElement = document.getElementById(this.getFieldId(container, 'message'));
        const formElement = document.getElementById(this.getFieldId(container, 'form'));

        if (messageElement && formElement) messageElement.formObj = formElement;
        if (formElement && !formElement.validator) {
            formElement.validator = new Validation(this.getFieldId(container, 'form'));
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
        this.initializePopup();
        this.bindActionLinks();
    }

    initializePopup() {
        document.getElementById('gift_options_configure')?.remove();

        const newPopupContainer = document.getElementById('gift_options_configure_new');
        if (newPopupContainer) {
            document.body.insertBefore(newPopupContainer, document.body.firstChild);
            newPopupContainer.id = 'gift_options_configure';
            this.createForm();
        }
    }

    createForm() {
        const formContents = document.getElementById('gift_options_form_contents');
        if (formContents) {
            const form = Object.assign(document.createElement('form'), {
                action: '#',
                id: 'gift_options_configuration_form',
                method: 'post'
            });
            formContents.parentNode.appendChild(form);
            form.appendChild(formContents);
        }
    }

    bindActionLinks() {
        document.querySelectorAll('.action-link').forEach(el => {
            el.addEventListener('click', this.showItemGiftOptions.bind(this));
        });
    }

    showItemGiftOptions(event) {
        const itemId = event.target.id.replace('gift_options_link_', '');

        this.giftOptionsWindowMask = document.getElementById('gift_options_window_mask');
        this.giftOptionsWindow = document.getElementById('gift_options_configure');

        this.giftOptionsWindow?.querySelectorAll('select').forEach(el => el.style.visibility = 'visible');

        if (this.giftOptionsWindowMask) {
            const htmlBody = document.getElementById('html-body') || document.body;
            Object.assign(this.giftOptionsWindowMask.style, {
                height: htmlBody.offsetHeight + 'px',
                display: 'block'
            });
        }

        if (this.giftOptionsWindow) {
            Object.assign(this.giftOptionsWindow.style, {
                marginTop: (-this.giftOptionsWindow.offsetHeight / 2) + 'px',
                display: 'block'
            });
        }

        this.setTitle(itemId);
        this.bindButtons();
        event.preventDefault();
    }

    bindButtons() {
        document.getElementById('gift_options_cancel_button')
            ?.addEventListener('click', () => this.closeWindow());
        document.getElementById('gift_options_ok_button')
            ?.addEventListener('click', this.onOkButton.bind(this));
    }

    setTitle(itemId) {
        const productTitle = document.getElementById(`order_item_${itemId}_title`)?.innerHTML || '';
        const titleElement = document.getElementById('gift_options_configure_title');
        if (titleElement) titleElement.innerHTML = productTitle;
    }

    onOkButton() {
        const giftOptionsForm = new varienForm('gift_options_configuration_form');
        giftOptionsForm.canShowError = true;
        if (giftOptionsForm.validate()) {
            giftOptionsForm.validator.reset();
            this.closeWindow();
            return true;
        }
        return false;
    }

    closeWindow() {
        [this.giftOptionsWindowMask, this.giftOptionsWindow].forEach(element => {
            if (element) element.style.display = 'none';
        });
    }
}

class GiftMessageSet {
    constructor() {
        this.destPrefix = 'current_item_giftmessage_';
        this.sourcePrefix = 'giftmessage_';
        this.fields = ['sender', 'recipient', 'message'];
        this.isObserved = false;
        this.bindActionLinks();
    }

    bindActionLinks() {
        document.querySelectorAll('.action-link').forEach(el => {
            el.addEventListener('click', this.setData.bind(this));
        });
    }

    setData(event) {
        this.id = event.target.id.replace('gift_options_link_', '');
        const giftMessageForm = document.getElementById(`gift-message-form-data-${this.id}`);
        const giftOptionsGiftmessage = document.getElementById('gift_options_giftmessage');

        if (giftMessageForm) {
            this.copyFieldValues();
            if (giftOptionsGiftmessage) giftOptionsGiftmessage.style.display = 'block';
        } else if (giftOptionsGiftmessage) {
            giftOptionsGiftmessage.style.display = 'none';
        }

        if (!this.isObserved) {
            document.getElementById('gift_options_ok_button')
                ?.addEventListener('click', this.saveData.bind(this));
            this.isObserved = true;
        }
    }

    copyFieldValues() {
        this.fields.forEach(field => {
            const sourceElement = document.getElementById(`${this.sourcePrefix}${this.id}_${field}`);
            const destElement = document.getElementById(`${this.destPrefix}${field}`);
            if (sourceElement && destElement) destElement.value = sourceElement.value;
        });
    }

    saveData() {
        this.fields.forEach(field => {
            const sourceElement = document.getElementById(`${this.sourcePrefix}${this.id}_${field}`);
            const destElement = document.getElementById(`${this.destPrefix}${field}`);
            if (sourceElement && destElement) sourceElement.value = destElement.value;
        });

        const formElement = document.getElementById(`${this.sourcePrefix}${this.id}_form`);
        if (formElement?.request) {
            formElement.request();
        } else if (typeof order !== 'undefined') {
            const data = order.serializeData(`gift_options_data_${this.id}`);
            order.loadArea(['items'], true, data?.toObject?.() || data);
        }
    }
}

const giftMessagesController = {
    toogleRequired: (source, objects) => GiftMessagesController.toogleRequired(source, objects),
    toogleGiftMessage: (container) => GiftMessagesController.toogleGiftMessage(container),
    saveGiftMessage: (container) => GiftMessagesController.saveGiftMessage(container)
};
