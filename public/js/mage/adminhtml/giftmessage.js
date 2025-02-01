/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const giftMessagesController = {
    saveGiftMessage(container) {
        const formEl = document.getElementById(`${container}_form`);
        const messageEl = document.getElementById(`${container}_message`);

        messageEl.formObj = formEl;

        if(!formEl.validator) {
            formEl.validator = new Validation(formEl);
        }

        if(!formEl.validator.validate()) {
            return;
        }

        mahoFetch(formEl.action, {
            method: 'POST',
            body: new FormData(formEl),
            loaderArea: container,
        });
    },
};

class GiftOptionsPopup {

    template = null;
    giftOptionsWindow = null;
    giftOptionsForm = null;
    productId = null;

    // Definitions to copy fields to popup
    sourcePrefix = 'giftmessage_';
    destPrefix = 'current_item_giftmessage_';
    fields = ['sender', 'recipient', 'message'];

    constructor() {
        this.template = document.getElementById('gift_options_configure').innerHTML;

        document.querySelectorAll('a[id^=gift_options_link_]').forEach((el) => {
            el.addEventListener('click', this.showItemGiftOptions.bind(this));
        });
    }

    showItemGiftOptions(event) {
        this.productId = event.target.id.replace('gift_options_link_', '');

        const productTitle = document.getElementById(`order_item_${this.productId}_title`)?.textContent;
        const dialogTitle = productTitle
              ? Translator.translate('Gift Options for') + ' ' + productTitle
              : Translator.translate('Gift Options');

        this.giftOptionsWindow = Dialog.info(this.template, {
            title: dialogTitle,
            width: 600,
            height: 400,
            ok: this.onOkButton.bind(this),
            cancel: true,
        });


        // Copy fields from hidden inputs if available
        for (const field of this.fields) {
            const sourceEl = document.getElementById(`${this.sourcePrefix}${this.productId}_${field}`);
            const destEl = document.getElementById(`${this.destPrefix}${field}`);
            if (sourceEl && destEl) {
                destEl.value = sourceEl.value;
            }
        }

        this.giftOptionsForm = new varienForm('gift_options_configuration_form');
        this.giftOptionsForm.canShowError = true;

        event.preventDefault();
    }

    onOkButton() {
        if (!this.giftOptionsForm.validate()) {
            return false;
        }

        // Copy fields back to hidden inputs
        for (const field of this.fields) {
            const sourceEl = document.getElementById(`${this.sourcePrefix}${this.productId}_${field}`);
            const destEl = document.getElementById(`${this.destPrefix}${field}`);
            if (sourceEl && destEl) {
                sourceEl.value = destEl.value;
            }
        }

        // Save on order view page
        const formEl = document.getElementById(`${this.sourcePrefix}${this.productId}_form`);
        if (formEl) {
            mahoFetch(formEl.action, {
                method: 'POST',
                body: new FormData(formEl),
            });
            return;
        }

        // Save on order create page
        if (typeof order !== 'undefined') {
            order.loadArea(['items'], true, order.serializeData(`gift_options_data_${this.productId}`));
        }
    }
};
