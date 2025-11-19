/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const Variables = {
    dialogWindow: null,
    dialogWindowId: 'variables-chooser',

    textareaElementId: null,
    insertFunction: this.insertVariable,
    variablesData: new Map(),

    init(textareaElementId, insertFunction) {
        if (document.getElementById(textareaElementId)) {
            this.textareaElementId = textareaElementId;
        }
        if (typeof insertFunction === 'function') {
            this.insertFunction = insertFunction;
        }
        this.insertFunction ??= this.insertVariable;
    },

    resetData() {
        this.dialogWindow = null;
        this.variablesData = new Map();
    },

    async openDialog(url, opts = {}) {
        if (document.getElementById(this.dialogWindowId)) {
            return;
        }

        const variables = await this.fetchVariables(url);
        if (!Array.isArray(variables)) {
            return;
        }

        const textareaElementId = url.match(/variable_target_id\/(\w+)/)?.[1] ?? opts.target_id;
        if (textareaElementId) {
            this.init(textareaElementId);
        }

        opts.onOk = wrapFunction(
            opts.onOk ?? function(){},
            (next, dialog) => {
                this.insertFunction(dialog);
                next(dialog);
            },
        );

        const html = this.buildHtml(variables);
        this.dialogWindow = Dialog.info(html, {
            id: this.dialogWindowId,
            title: 'Insert Variable...',
            className: 'magento',
            windowClassName: 'popup-window',
            width: 700,
            ok: true,
            okLabel: 'Insert',
            ...opts,
        });
    },

    closeDialog(window) {
        window ??= this.dialogWindow;
        window?.close();
    },

    async fetchVariables(url) {
        if (this.variablesData.has(url)) {
            return this.variablesData.get(url);
        }
        try {
            const result = await mahoFetch(url);
            if (Array.isArray(result)) {
                this.variablesData.set(url, result);
                return result;
            }
        } catch (error) {
            alert(error.message);
        }
        return false;
    },

    buildHtml(variables) {
        let html = '';
        for (const group of variables) {
            if (!group.label || !Array.isArray(group.value)) {
                continue;
            }
            html += `<h3>${group.label}</h3>`;
            html += '<ul>';
            for (const variable of group.value) {
                if (!variable.value || !variable.label) {
                    continue;
                }
                const label = escapeHtml(variable.label);
                const value = escapeHtml(variable.value, true);
                html += `<li><label><input type="radio" name="variable" value="${value}"> ${label}</label></li>`;
            }
            html += '</ul>';
        }
        return html;
    },

    openVariableChooser(variables, opts) {
        const fakeUrl = generateRandomString(6);
        this.variablesData.set(fakeUrl, variables);
        this.openDialog(fakeUrl, opts);
    },

    initSelected(value) {
        if (!value) {
            return;
        }
        for (const inputEl of this.dialogWindow?.querySelectorAll('input[name=variable]')) {
            if (inputEl.value === value) {
                inputEl.checked = true;
            }
        }
    },

    insertVariable(dialog) {
        const directive = this.dialogWindow?.querySelector('input[name=variable]:checked')?.value;

        if (dialog instanceof HTMLDialogElement) {
            dialog.returnValue = directive;
        }

        const textareaElm = document.getElementById(this.textareaElementId);
        if (textareaElm?.checkVisibility()) {
            updateElementAtCursor(textareaElm, directive);
        }
    },
};
