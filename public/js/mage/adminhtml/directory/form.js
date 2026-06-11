// SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

const DirectoryEditForm = {
    saveAndContinueEdit() {
        const formEl = document.getElementById('edit_form');
        if (!formEl) {
            return;
        }
        editForm.submit(setRouteParams(formEl.action, { back: 'edit' }));
    },

    refreshGrid(grid, gridMassAction) {
        grid.reload();
        gridMassAction.unselectAll();
    },

    async saveTranslation(idPrefix, saveUrl, grid) {
        const fieldsetEl = document.getElementById(idPrefix + 'add_fieldset');
        const elements = fieldsetEl.querySelectorAll('input, select, textarea');
        let validationResult = true;

        fieldsetEl.classList.remove('ignore-validate');
        for (const el of elements) {
            validationResult &&= Validation.validate(el, {
                useTitle: false,
                onElementValidate: function() {},
            });
        }
        fieldsetEl.classList.add('ignore-validate');

        if (!validationResult) {
            return;
        }

        const formData = new FormData();
        for (const el of elements) {
            formData.append(el.name, el.value);
        }

        clearMessagesDiv();

        try {
            const result = await mahoFetch(saveUrl, {
                method: 'POST',
                body: formData,
            });

            if (window[grid]) {
                window[grid].reload();
            }
            if (result?.messages) {
                setMessagesDivHtml(result.messages);
            }
        } catch (error) {
            setMessagesDiv(error.message, 'error');
        }
    }
}
