/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Class to control transactional email and newsletter templates
 */
class EmailTemplateEditForm {
    templateName = null;
    typeChange = false;
    unconvertedText = '';

    constructor(config) {
        this.templateName = config.templateName;
        this.forms = config.forms;
        this.urls = config.urls;
        this.elements = {
            convertButton: document.getElementById('convert_button'),
            convertButtonBack: document.getElementById('convert_button_back'),
            editorToggle: document.getElementById(`toggle${config.elements.templateText}`),
        };

        for (const [ key, id ] of Object.entries(config.elements)) {
            this.elements[key] = document.getElementById(id);
        }

        if (typeof config.paths === 'object' && config.paths !== null) {
            for (const [ fieldId, paths ] of Object.entries(config.paths)) {
                this.renderPaths(paths, fieldId);
            }
        }

        this.toggleEditMode('html');
    }

    getEditorInstance() {
        if (this.elements.templateText?.id) {
            return window[`wysiwyg${this.elements.templateText.id}`];
        }
    }

    toggleEditMode(mode) {
        if (this.elements.convertButton && this.elements.convertButtonBack) {
            if (mode === 'plainonly') {
                toggleVis(this.elements.convertButton, false);
                toggleVis(this.elements.convertButtonBack, false);
            } else if (mode === 'plain') {
                toggleVis(this.elements.convertButton, false);
                toggleVis(this.elements.convertButtonBack, true);
            } else if (mode === 'html') {
                toggleVis(this.elements.convertButton, true);
                toggleVis(this.elements.convertButtonBack, false);
            }
        }
        if (this.elements.editorToggle) {
            if (mode === 'plainonly') {
                this.getEditorInstance()?.turnOff();
                toggleVis(this.elements.editorToggle, false);
            } else if (mode === 'plain') {
                this.getEditorInstance()?.turnOff();
                toggleVis(this.elements.editorToggle, true);
            } else if (mode === 'html') {
                this.getEditorInstance()?.turnOn();
                toggleVis(this.elements.editorToggle, true);
            }
        }

        const templateStylesRow = this.elements.templateStyles?.closest('tr');
        if (templateStylesRow) {
            toggleVis(templateStylesRow, mode === 'html');
        }
    }

    stripTags() {
        if (!window.confirm(Translator.translate('Are you sure that you want to strip tags?'))) {
            return false;
        }

        // Turn off editor ensuring contents are synced to textarea
        this.toggleEditMode('plain');
        this.typeChange = true;

        // Store current value for returning to HTML version, then strip tags
        this.unconvertedText = this.elements.templateText.value;
        this.elements.templateText.value = stripTags(this.elements.templateText.value, true);

        return false;
    }

    unStripTags() {
        // Restore HTML version and sync back to editor
        this.elements.templateText.value = this.unconvertedText;
        this.getEditorInstance()?.syncPlainToWysiwyg();

        this.toggleEditMode('html');
        this.typeChange = false;

        return false;
    }

    save(saveAs = false) {
        const saveAsFlag = document.getElementById('save_as_flag');
        if (saveAsFlag) {
            saveAsFlag.value = saveAs ? '1' : '';
        }
        const changeFlag = document.getElementById('change_flag_element');
        if (changeFlag) {
            changeFlag.value = this.typeChange ? '1' : '';
        }
        const resumeFlag = document.getElementById('_resume_flag');
        if (resumeFlag) {
            resumeFlag.value = '';
        }
        this.forms.edit.submit();
        return false;
    }

    saveAs() {
        if (!this.elements.templateName) {
            return;
        }
        // Prompt for new template name
        let templateName = this.elements.templateName.value.trim();
        if (!templateName || templateName === this.templateName) {
            templateName = prompt(
                Translator.translate('Please enter new template name'),
                this.templateName + Translator.translate(' Copy'),
            );
            if (templateName === null) {
                return;
            }
            this.elements.templateName.value = templateName;
        }
        this.save(true);
        return false;
    }

    resume() {
        const resumeFlag = document.getElementById('_resume_flag');
        if (resumeFlag) {
            resumeFlag.value = '1';
        }
        this.forms.edit.submit();
        return false;
    }

    preview() {
        if (this.elements.previewType) {
            this.elements.previewType.value = this.typeChange ? 1 : 2;
        }
        if (this.elements.previewText) {
            this.elements.previewText.value = this.elements.templateText?.value;
        }
        if (this.elements.previewStyles) {
            this.elements.previewStyles.value = this.elements.templateStyles?.value;
        }
        if (this.elements.previewId) {
            this.elements.previewId.value = this.elements.templateId?.value;
        }
        this.forms.preview.submit();
        return false;
    }

    deleteTemplate() {
        confirmSetLocation(Translator.translate('Are you sure that you want to delete this template?'), this.urls.delete);
    }

    async load() {
        if (!this.forms.load?.validator.validate()) {
            return;
        }

        try {
            const formEl = document.getElementById(this.forms.load.formId);
            const formData = new FormData(formEl);
            const formAction = formEl.action;

            const result = await mahoFetch(formAction, {
                method: 'POST',
                body: formData,
            });

            for (const [ key, value ] of Object.entries(result)) {
                const element = document.getElementById(key);
                if (element) {
                    element.value = typeof value === 'string' ? value.trim() : value;
                }

                if (key === 'template_type') {
                    if (value == 1) {
                        this.typeChange = true;
                        this.toggleEditMode('plainonly')
                    } else {
                        this.typeChange = false;
                        this.toggleEditMode('html')
                    }
                }

                if (key === 'orig_template_used_default_for') {
                    const usedDefaultFor = document.getElementById('used_default_for');
                    if (value) {
                        this.renderPaths(value, 'used_default_for');
                        toggleVis(usedDefaultFor, true);
                    } else {
                        toggleVis(usedDefaultFor, false);
                    }
                }
            }

            this.getEditorInstance()?.syncPlainToWysiwyg();

        } catch (error) {
            alert(`Failed to load template: ${error.message}`);
        }
    }

    renderPaths(paths, fieldId) {
        const td = document.getElementById(fieldId)?.querySelector('td.value');
        if (td) {
            td.innerHTML = this.parsePath(paths, '<span class="path-delimiter">&nbsp;-&gt;&nbsp;</span>', '<br>');
        }
    }

    parsePath(value, pathDelimiter, lineDelimiter) {
        if (Array.isArray(value)) {
            const result = [];
            for (let i = 0; i < value.length; i++) {
                result.push(this.parsePath(value[i], pathDelimiter, pathDelimiter));
            }
            return result.join(lineDelimiter);
        }

        if (typeof value === 'object' && value?.title) {
            value = (value.url ? `<a href="${value.url}">${value.title}</a>` : value.title) +
                    (value.scope ? `&nbsp;&nbsp;<span class="path-scope-label">${value.scope}</span>` : '');
        }

        return value;
    }

    openVariableChooser() {
        const variables = [];
        const addVariablesFromField = (fieldId) => {
            try {
                const field = document.getElementById(fieldId);
                const value = JSON.parse(field?.value ?? null);
                if (Array.isArray(value)) {
                    variables.push(...value);
                }
            } catch (error) {
                console.error(`Failed to parse ${fieldId}:`, error);
            }
        }

        addVariablesFromField('variables');
        addVariablesFromField('template_variables');

        if (variables.length && this.elements.templateText?.id) {
            Variables.openVariableChooser(variables, { target_id: this.elements.templateText.id });
        }
    }
};
