/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const catalogWysiwygEditor = {
    elementId: null,

    getEditorInstance() {
        if (this.elementId) {
            return window[`wysiwyg${this.elementId}_editor`];
        }
    },

    async open(editorUrl, elementId) {
        if (!editorUrl || !elementId) {
            return;
        }
        try {
            const html = await mahoFetch(editorUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    element_id: `${elementId}_editor`,
                }),
            });

            this.openDialogWindow(html, elementId);
        } catch(error) {
            alert(error.message);
        }
    },

    openDialogWindow(content, elementId) {
        this.elementId = elementId;

        Dialog.confirm(content, {
            id: 'catalog-wysiwyg-editor',
            title: 'WYSIWYG Editor',
            className: 'magento',
            windowClassName: 'popup-window',
            ok: true,
            okLabel: 'Submit',
            onOk: this.okDialogWindow.bind(this),
            onClose: this.closeDialogWindow.bind(this),
        });

        // Sync value from original textarea to wysiwyg textarea
        const originalTextarea = document.getElementById(this.elementId);
        const wysiwygTextarea = document.getElementById(`${this.elementId}_editor`);
        if (originalTextarea && wysiwygTextarea) {
            wysiwygTextarea.value = originalTextarea.value;
        }

        // Wait for wysiwyg to be initialized and then set content
        mahoOnReady(() => {
            this.getEditorInstance()?.syncPlainToWysiwyg();
        });
    },

    okDialogWindow(dialogWindow) {
        if (!this.elementId) {
            return;
        }

        // Sync value from wysiwyg textarea to original textarea
        const originalTextarea = document.getElementById(this.elementId);
        const wysiwygTextarea = document.getElementById(`${this.elementId}_editor`);
        if (originalTextarea && wysiwygTextarea) {
            originalTextarea.value = wysiwygTextarea.value;
        }
    },

    closeDialogWindow(dialogWindow) {
        if (!this.elementId) {
            return;
        }

        this.getEditorInstance()?.destroy();
    }
};
