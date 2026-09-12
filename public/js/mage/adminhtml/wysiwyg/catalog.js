// SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
// SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
// SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
// SPDX-License-Identifier: AFL-3.0

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

        // The editor starts before the textarea holds the content. The content arrives here,
        // and the editor removes what its schema does not allow.
        mahoOnReady(() => {
            const editor = this.getEditorInstance();
            editor?.syncPlainToWysiwyg();
            if (editor && !editor.confirmContentLoss()) {
                Dialog.close();
            }
        });
    },

    okDialogWindow(dialogWindow) {
        if (!this.elementId) {
            return;
        }

        // The editor syncs after a delay. A fast Submit would copy an old value.
        this.getEditorInstance()?.updateTextArea();

        // Sync value from wysiwyg textarea to original textarea
        const originalTextarea = document.getElementById(this.elementId);
        const wysiwygTextarea = document.getElementById(`${this.elementId}_editor`);
        if (originalTextarea && wysiwygTextarea) {
            originalTextarea.value = wysiwygTextarea.value;
            originalTextarea.dispatchEvent(new Event('change', { bubbles: false, cancelable: true }));
        }
    },

    closeDialogWindow(dialogWindow) {
        if (!this.elementId) {
            return;
        }

        this.getEditorInstance()?.destroy();
    }
};
