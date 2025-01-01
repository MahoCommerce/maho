/**
 * Maho
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const widgetTools = {
    dialogWindow: null,
    dialogWindowId: 'widget_window',

    getDivHtml(id, html) {
        if (!html) html = '';
        return `<div id="${id}">${html}</div>`;
    },

    appendHtml(targetEl, html) {
        const fragment = document.createRange().createContextualFragment(html);
        fragment.querySelectorAll('script[src]').forEach(script => script.remove());
        targetEl.append(...fragment.children);
    },

    async openDialog(widgetUrl) {
        if (document.getElementById(this.dialogWindowId)) {
            return;
        }
        try {
            const result = await mahoFetch(widgetUrl);

            this.dialogWindow = Dialog.info(result, {
                id: this.dialogWindowId,
                title: 'Insert Widget...',
                className: 'magento',
                windowClassName: 'popup-window',
                width: 950,
                onClose: this.closeDialog.bind(this)
            });
        } catch (error) {
            console.error(error);
            alert(error.message);
        }
    },

    closeDialog(window) {
        window ??= this.dialogWindow;
        window?.close();
    },
};

const WysiwygWidget = {}

WysiwygWidget.Widget = class {
    constructor() {
        this.initialize(...arguments);
    }

    initialize(formId, widgetId, widgetOptionsId, optionsSourceUrl, widgetTargetId) {
        this.formEl = document.getElementById(formId);
        widgetTools.appendHtml(this.formEl, widgetTools.getDivHtml(widgetOptionsId))

        this.widgetEl = document.getElementById(widgetId);
        this.widgetOptionsEl = document.getElementById(widgetOptionsId);
        this.optionsUrl = optionsSourceUrl;
        this.optionValues = new Map();
        this.widgetTargetId = widgetTargetId;

        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
            this.bMark = tinyMCE.activeEditor.selection.getBookmark();
        }

        this.widgetEl.addEventListener('change', this.loadOptions.bind(this));
        this.initOptionValues();
    }

    getOptionsContainerId() {
        return this.widgetOptionsEl.id + '_' + this.widgetEl.value.replaceAll(/\//g, '_');
    }

    switchOptionsContainer(containerId) {
        this.widgetOptionsEl.querySelectorAll(`div[id^=${this.widgetOptionsEl.id}]`).forEach((el) => {
            this.disableOptionsContainer(el.id);
        });

        if (containerId != undefined) {
            this.enableOptionsContainer(containerId);
        }
        this._showWidgetDescription();
    }

    enableOptionsContainer(containerId) {
        const containerEl = document.getElementById(containerId);
        if (!containerEl) {
            return;
        }
        containerEl.querySelectorAll(`.widgetOption`).forEach((el) => {
            el.classList.remove('skip-submit');
            if (el.classList.contains('obligatory')) {
                el.classList.remove('obligatory');
                el.classList.add('required-entry');
            }
        });
        containerEl.classList.remove('no-display');
    }

    disableOptionsContainer(containerId) {
        const containerEl = document.getElementById(containerId);
        if (!containerEl || containerEl.classList.contains('no-display')) {
            return;
        }
        containerEl.querySelectorAll(`.widgetOption`).forEach((el) => {
            // Avoid submitting fields of unactive container
            el.classList.add('skip-submit');

            // Form validation workaround for unactive container
            if (el.classList.contains('required-entry')) {
                el.classList.remove('required-entry');
                el.classList.add('obligatory');
            }
        });
        containerEl.classList.add('no-display');
    }

    // Assign widget options values when existing widget selected in WYSIWYG
    initOptionValues() {
        if (!this.wysiwygExists()) {
            return false;
        }

        const el = this.getWysiwygNode();
        if (!el || !el.id) {
            return;
        }

        const widgetCode = Base64.idDecode(e.id);
        if (widgetCode.indexOf('{{widget') === -1) {
            return;
        }

        this.optionValues = new Map();

        widgetCode.replaceAll(/([a-z0-9\_]+)\s*\=\s*[\"]{1}([^\"]+)[\"]{1}/gi, (...match) => {
            if (match[1] == 'type') {
                this.widgetEl.value = match[2];
            } else {
                this.optionValues.set(match[1], match[2]);
            }
        });

        this.loadOptions();
    }

    async loadOptions() {
        if (!this.widgetEl.value) {
            this.switchOptionsContainer();
            return;
        }

        const containerId = this.getOptionsContainerId();
        const containerEl = document.getElementById(containerId);

        if (containerEl) {
            this.switchOptionsContainer(containerId);
            return;
        }

        this._showWidgetDescription();

        try {
            const params = {
                widget_type: this.widgetEl.value,
                values: Object.fromEntries(this.optionValues),
            };

            const html = await mahoFetch(this.optionsUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    widget: JSON.stringify(params),
                }),
            });

            this.switchOptionsContainer();

            if (containerEl) {
                this.switchOptionsContainer(containerId);
            } else {
                widgetTools.appendHtml(this.widgetOptionsEl, widgetTools.getDivHtml(containerId, html));
            }

        } catch(error) {
            console.error(error);
            alert(error.message);
        }
    }

    _showWidgetDescription() {
        const noteEl = this.widgetEl.nextElementSibling?.querySelector('small');
        const descEl = document.getElementById(`widget-description-${this.widgetEl.selectedIndex}`);
        if (noteEl) {
            noteEl.textContent = descEl?.textContent;
        }
    }

    async insertWidget() {
        const widgetOptionsForm = new varienForm(this.formEl);
        if (widgetOptionsForm.validator && widgetOptionsForm.validator.validate() || !widgetOptionsForm.validator) {
            try {
                const formData = new FormData();
                for (const el of this.formEl.elements) {
                    if (!el.classList.contains('skip-submit')) {
                        formData.append(el.name, el.value);
                    }
                }

                // Add as_is flag to parameters if wysiwyg editor doesn't exist
                if (!this.wysiwygExists()) {
                    formData.set('as_is', 1);
                }

                const html = await mahoFetch(this.formEl.action, {
                    method: 'POST',
                    body: formData,
                })

                Windows.close('widget_window');

                if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                    tinyMCE.activeEditor.focus();
                    if (this.bMark) {
                        tinyMCE.activeEditor.selection.moveToBookmark(this.bMark);
                    }
                }

                this.updateContent(html);

            } catch(error) {
                console.error(error);
                alert(error.message);
            }
        }
    }

    updateContent(content) {
        if (this.wysiwygExists()) {
            this.getWysiwyg().execCommand('mceInsertContent', false, content);
        } else {
            const textarea = document.getElementById(this.widgetTargetId);
            updateElementAtCursor(textarea, content);
            varienGlobalEvents.fireEvent('tinymceChange');
        }
    }

    wysiwygExists() {
        return typeof tinyMCE !== 'undefined' && tinyMCE.get(this.widgetTargetId);
    }

    getWysiwyg() {
        return tinyMCE.activeEditor;
    }

    getWysiwygNode() {
        return tinyMCE.activeEditor.selection.getNode();
    }
};

WysiwygWidget.chooser = class {

    // HTML element A, on which click event fired when choose a selection
    chooserId = null;

    // Source URL for Ajax requests
    chooserUrl = null;

    // Chooser config
    config = null;

    // Chooser dialog window
    dialogWindow = null;

    // Chooser content for dialog window
    dialogContent = null;

    constructor() {
        this.initialize(...arguments);
    }

    initialize(chooserId, chooserUrl, config) {
        this.chooserId = chooserId;
        this.chooserUrl = chooserUrl;
        this.config = config;
    }

    getResponseContainerId() {
        return 'responseCnt' + this.chooserId;
    }

    getChooserControl() {
        return document.getElementById(this.chooserId + 'control');
    }

    getElement() {
        return document.getElementById(this.chooserId + 'value');
    }

    getElementLabel() {
        return document.getElementById(this.chooserId + 'label');
    }

    open() {
        document.getElementById(this.getResponseContainerId())?.classList.remove('no-display');
    }

    close() {
        document.getElementById(this.getResponseContainerId())?.classList.add('no-display');
        this.closeDialogWindow();
    }

    async choose(event) {
        // Open dialog window with previously loaded dialog content
        if (this.dialogContent) {
            this.openDialogWindow(this.dialogContent);
            return;
        }
        try {
            const html = await mahoFetch(this.chooserUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    element_value: this.getElementValue(),
                    element_label: this.getElementLabelText(),
                }),
            });

            this.dialogContent = widgetTools.getDivHtml(this.getResponseContainerId(), html);
            this.openDialogWindow(this.dialogContent);

        } catch (error) {
            console.error(error);
            alert(error.message);
        }
    }

    openDialogWindow(content) {
        this.dialogWindow = Dialog.info(content, {
            id: 'widget-chooser',
            title: this.config.buttons.open,
            className: 'magento',
            windowClassName: 'popup-window',
            width:950,
            onClose: this.closeDialogWindow.bind(this)
        });
    }

    closeDialogWindow(dialogWindow) {
        dialogWindow ??= this.dialogWindow;
        if (dialogWindow) {
            dialogWindow.close();
        }
        this.dialogWindow = null;
    }

    getElementValue(value) {
        return this.getElement().value;
    }

    getElementLabelText(value) {
        return this.getElementLabel().innerHTML;
    }

    setElementValue(value) {
        this.getElement().value = value;
    }

    setElementLabel(value) {
        this.getElementLabel().innerHTML = value;
    }
};
