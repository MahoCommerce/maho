/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const catalogWysiwygEditor = {
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
        Dialog.confirm(content, {
            id: 'catalog-wysiwyg-editor',
            title: 'WYSIWYG Editor',
            className: 'magento',
            windowClassName: 'popup-window',
            okLabel: 'Submit',
            ok: this.okDialogWindow.bind(this),
            onClose: this.closeDialogWindow.bind(this),
            firedElementId: elementId,
        });

        document.getElementById(`${elementId}_editor`).value = document.getElementById(elementId).value;
    },

    okDialogWindow(dialogWindow) {
        const elementId = dialogWindow.options.firedElementId;
        if (!elementId) {
            return;
        }

        const wysiwygObj = window[`wysiwyg${elementId}_editor`];
        wysiwygObj.turnOff();

        let content = document.getElementById(`${elementId}_editor`)?.value;
        
        // Check if we have a QuillJS editor
        if (typeof quillEditors !== 'undefined' && quillEditors.has(wysiwygObj.id)) {
            const quillEditor = quillEditors.get(wysiwygObj.id);
            if (quillEditor && quillEditor.editor) {
                content = quillEditor.editor.root.innerHTML;
            }
        }
        // Legacy TinyMCE support
        else if (typeof tinymce !== 'undefined' && tinymce.get(wysiwygObj.id)) {
            content = tinymce.get(wysiwygObj.id).getContent();
        }

        if (content) {
            document.getElementById(elementId).value = content;
        }
    },

    closeDialogWindow(dialogWindow) {
        const elementId = dialogWindow.options.firedElementId;
        if (!elementId) {
            return;
        }

        // destroy the instance of editor
        const wysiwygObj = window[`wysiwyg${elementId}_editor`];
        wysiwygObj.destroy();
    }
};
