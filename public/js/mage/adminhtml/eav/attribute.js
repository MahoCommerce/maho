// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

/**
 * Controls the edit page of an attribute of any entity type.
 */
class EavAttributeForm {

    /**
     * @param {Object} config
     * @param {string} config.tabsId - id of the tabs block of the page
     * @param {Object<string, string[]>} [config.hiddenFields] - fields to hide for each input type
     */
    constructor(config) {
        this.config = {
            tabsId: 'eav_attribute_tabs',
            hiddenFields: {},
            ...config,
        };

        this.inputEl = document.getElementById('frontend_input');
        this.inputEl?.addEventListener('change', () => this.refresh());
        document.getElementById('is_required')?.addEventListener('change', () => this.updateRequiredOptions());
        document.getElementById('is_wysiwyg_enabled')?.addEventListener('change', () => this.refresh());
        this.refresh();
    }

    /**
     * Submit the form. With continueEdit, the page opens again on the active tab.
     *
     * @param {boolean} [continueEdit=false]
     */
    save(continueEdit = false) {
        disableElements('save');
        let url;
        if (continueEdit) {
            const tabs = window[this.config.tabsId + 'JsTabs'];
            url = document.getElementById('edit_form').action + 'back/edit/tab/' + (tabs?.activeTab?.id ?? '');
        }
        if (editForm.submit(url) === false) {
            enableElements('save');
        }
        varienGlobalEvents.attachEventHandler('formValidateAjaxComplete', () => enableElements('save'));
    }

    refresh() {
        const input = this.inputEl?.value ?? '';

        const hasOptions = input === 'select' || input === 'multiselect';
        const panel = document.getElementById('matage-options-panel');
        if (panel) {
            panel.style.display = hasOptions ? '' : 'none';
        }

        window.optionDefaultInputType = input === 'multiselect' ? 'checkbox' : 'radio';
        for (const el of document.getElementsByName('default[]')) {
            el.type = window.optionDefaultInputType;
        }

        const hidden = this.config.hiddenFields[input] ?? [];
        const hideDefault = hasOptions || hidden.includes('_default_value');
        this.setRowVisibility('default_value_text', !hideDefault && !['date', 'boolean', 'textarea'].includes(input));
        this.setRowVisibility('default_value_textarea', !hideDefault && input === 'textarea');
        this.setRowVisibility('default_value_date', !hideDefault && input === 'date');
        this.setRowVisibility('default_value_yesno', !hideDefault && input === 'boolean');

        for (const id of ['is_unique', 'frontend_class', 'is_required']) {
            this.setRowVisibility(id, !hidden.includes(id));
        }

        const isText = input === 'text' || input === 'textarea';
        this.setRowVisibility('multiline_count', input === 'multiline');
        this.setRowVisibility('min_text_length', isText);
        this.setRowVisibility('max_text_length', isText);
        this.setRowVisibility('input_validation', input === 'text');

        const wysiwyg = document.getElementById('is_wysiwyg_enabled');
        this.setRowVisibility('is_wysiwyg_enabled', input === 'textarea');
        this.setRowVisibility(
            'is_html_allowed_on_front',
            ['text', 'select', 'multiselect'].includes(input) || (input === 'textarea' && wysiwyg?.value === '0'),
        );

        this.updateRequiredOptions();
    }

    updateRequiredOptions() {
        const check = document.getElementById('option-count-check');
        const required = document.getElementById('is_required');
        if (check && required) {
            check.classList.toggle('required-options-count', this.inputEl?.value === 'select' && required.value == 1);
        }
    }

    /**
     * Show or hide the table row of a form field.
     *
     * @param {string} id
     * @param {boolean} isVisible
     */
    setRowVisibility(id, isVisible) {
        const row = document.getElementById(id)?.closest('tr');
        if (row) {
            row.style.display = isVisible ? '' : 'none';
        }
    }
}
