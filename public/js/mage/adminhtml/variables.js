/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const Variables = {
    textareaElementId: null,
    variablesContent: null,
    dialogWindow: null,
    dialogWindowId: 'variables-chooser',
    insertFunction: 'Variables.insertVariable',

    init(textareaElementId, insertFunction) {
        if (document.getElementById(textareaElementId)) {
            this.textareaElementId = textareaElementId;
        }
        if (insertFunction) {
            this.insertFunction = insertFunction;
        }
    },

    resetData() {
        this.variablesContent = null;
        this.dialogWindow = null;
    },

    openVariableChooser(variables) {
        if (this.variablesContent === null && Array.isArray(variables)) {
            this.variablesContent = '<ul>';
            for (const group of variables) {
                if (!group.label || !Array.isArray(group.value)) {
                    continue;
                }
                this.variablesContent += `<li><strong>${group.label}</strong></li>`;
                for (const variable of group.value) {
                    if (!variable.value || !variable.label) {
                        continue;
                    }
                    const row = this.prepareVariableRow(variable.value, variable.label);
                    this.variablesContent += `<li style="padding-left: 20px;">${row}</li>`;
                }
            }
            this.variablesContent += '</ul>';
        }
        if (this.variablesContent) {
            this.openDialogWindow(this.variablesContent);
        }
    },

    openDialogWindow(variablesContent) {
        if (document.getElementById(this.dialogWindowId)) {
            return;
        }
        this.dialogWindow = Dialog.info(variablesContent, {
            id: this.dialogWindowId,
            title: 'Insert Variable...',
            className: 'magento',
            windowClassName: 'popup-window',
            width: 700,
            onClose: this.closeDialogWindow.bind(this)
        });
    },

    closeDialogWindow(window) {
        window ??= this.dialogWindow;
        window?.close();
    },

    prepareVariableRow(value, label) {
        value = escapeHtml(value, true).replace(/\\/g, '\\\\');
        return `<a href="#" onclick="${this.insertFunction}('${value}');return false;">${label}</a>`;
    },

    insertVariable(value) {
        this.closeDialogWindow();
        
        // Check if we have a QuillJS editor
        if (typeof quillEditors !== 'undefined' && quillEditors.has(this.textareaElementId)) {
            const quillEditor = quillEditors.get(this.textareaElementId);
            if (quillEditor && quillEditor.editor) {
                quillEditor.insertContent(value);
                return;
            }
        }
        
        const textareaElm = document.getElementById(this.textareaElementId);
        if (textareaElm) {
            updateElementAtCursor(textareaElm, value);
        }
    }
};

const OpenmagevariablePlugin = {
    editor: null,
    variables: null,
    textareaId: null,

    setEditor(editor) {
        this.editor = editor;
    },

    async loadChooser(url, textareaId) {
        this.textareaId = textareaId;
        try {
            if (this.variables === null) {
                this.variables = await mahoFetch(url);
            }
            Variables.init(null, 'OpenmagevariablePlugin.insertVariable');
            this.openChooser(this.variables);
        } catch (error) {
            console.error(error);
            alert(error.message);
        }
    },

    openChooser(variables) {
        Variables.openVariableChooser(variables);
    },

    insertVariable(value) {
        if (this.textareaId) {
            Variables.closeDialogWindow();
            
            // Check if we have a QuillJS editor
            if (typeof quillEditors !== 'undefined' && quillEditors.has(this.textareaId)) {
                const quillEditor = quillEditors.get(this.textareaId);
                if (quillEditor && quillEditor.editor) {
                    quillEditor.insertContent(value);
                    return;
                }
            }
            
            // Fallback to textarea
            const textareaElm = document.getElementById(this.textareaId);
            if (textareaElm) {
                updateElementAtCursor(textareaElm, value);
            }
        } else {
            Variables.closeDialogWindow();
            
            // Check if we're using QuillJS
            if (typeof quillEditors !== 'undefined' && this.editor && this.editor.container) {
                // Find the QuillJS instance by container
                const editorId = this.editor.container.previousElementSibling?.id;
                if (editorId && quillEditors.has(editorId)) {
                    const quillEditor = quillEditors.get(editorId);
                    if (quillEditor) {
                        quillEditor.insertContent(value);
                        return;
                    }
                }
            }
            
            // Fall back to TinyMCE if available
            if (this.editor && this.editor.execCommand) {
                this.editor.execCommand('mceInsertContent', false, value);
            }
        }
    },
};
