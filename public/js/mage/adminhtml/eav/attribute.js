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
        for (const id of ['is_required', 'default_mode']) {
            document.getElementById(id)?.addEventListener('change', () => this.updateRequiredOptions());
        }
        document.getElementById('default_mode')?.addEventListener('change', () => this.updateWebsiteDefaults());
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
        if (!check) {
            return;
        }
        const isRequired = document.getElementById('is_required')?.value === '1'
            || document.getElementById('default_mode')?.value === 'required';
        check.classList.toggle('required-options-count', this.inputEl?.value === 'select' && isRequired);
    }

    /**
     * Write the mode of the Default field into the "Use Default (...)" option of each website.
     * The label of the mode comes from the option of that mode.
     */
    updateWebsiteDefaults() {
        const mode = document.getElementById('default_mode')?.value;
        if (!mode) {
            return;
        }
        for (const select of document.querySelectorAll('select.website-mode')) {
            const option = select.querySelector('option[value=""]');
            const label = select.querySelector(`option[value="${mode}"]`)?.text;
            if (option && label) {
                option.text = option.text.replace(/\([^)]*\)$/, `(${label})`);
            }
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

/**
 * Controls the editor of the condition that shows the attribute in a form. Each row names a field and the values
 * that show the attribute. The editor writes the condition into the hidden depends_on input, in the format of
 * Mage_Adminhtml_Block_Widget_Form_Element_Dependence: [{operator, condition: {field: [values]}}].
 */
class EavAttributeDependence {

    /**
     * @param {Object} config
     * @param {Object<string, {label: string, options: {value: string, label: string}[]}>} config.sources
     * @param {{operator: string, rows: {field: string, values: string[]}[]}} config.condition
     * @param {Object<string, string>} config.labels
     */
    constructor(config) {
        this.config = config;
        this.input = document.getElementById('depends_on');
        this.operatorEl = document.getElementById('depends_on_operator');
        this.rowsEl = document.getElementById('depends_on_rows');

        this.operatorEl.addEventListener('change', () => this.write());
        document.getElementById('depends_on_add').addEventListener('click', () => {
            this.addRow('', []);
            this.rowsEl.lastElementChild?.querySelector('select')?.focus();
        });
        for (const row of config.condition.rows) {
            this.addRow(row.field, row.values);
        }
        this.update();
    }

    /**
     * @param {string} field
     * @param {string[]} values
     */
    addRow(field, values) {
        const row = document.createElement('div');
        row.className = 'flex flex-wrap items-start gap-2';

        const fieldEl = document.createElement('select');
        fieldEl.className = 'select';
        fieldEl.setAttribute('aria-label', this.config.labels.field);
        fieldEl.add(new Option('', ''));
        for (const [code, source] of Object.entries(this.config.sources)) {
            fieldEl.add(new Option(`${source.label} (${code})`, code, false, code === field));
        }
        if (field && !this.config.sources[field]) {
            fieldEl.add(new Option(field, field, false, true));
        }

        const valuesEl = document.createElement('select');
        valuesEl.className = 'select multiselect';
        valuesEl.multiple = true;
        valuesEl.size = 5;
        valuesEl.setAttribute('aria-label', this.config.labels.values);
        this.fillValues(valuesEl, field, values);

        const deleteEl = document.createElement('button');
        deleteEl.type = 'button';
        deleteEl.className = 'd-btn d-btn-sm';
        deleteEl.textContent = this.config.labels.delete;

        fieldEl.addEventListener('change', () => {
            this.fillValues(valuesEl, fieldEl.value, []);
            this.update();
        });
        valuesEl.addEventListener('change', () => this.write());
        deleteEl.addEventListener('click', () => {
            row.remove();
            this.update();
        });

        row.append(fieldEl, valuesEl, deleteEl);
        this.rowsEl.append(row);
        // A list box shows its first options, so it scrolls to the first selected one when the tab shows it
        const selected = valuesEl.selectedOptions[0];
        if (selected) {
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    valuesEl.scrollTop = selected.index * (valuesEl.scrollHeight / valuesEl.options.length);
                    observer.disconnect();
                }
            });
            observer.observe(valuesEl);
        }
    }

    /**
     * @param {HTMLSelectElement} valuesEl
     * @param {string} field
     * @param {string[]} selected
     */
    fillValues(valuesEl, field, selected) {
        valuesEl.replaceChildren();
        for (const option of this.config.sources[field]?.options ?? []) {
            valuesEl.add(new Option(option.label, option.value, false, selected.includes(option.value)));
        }
        valuesEl.disabled = valuesEl.options.length === 0;
    }

    /**
     * Allow each field in one row only, name the delete buttons, and write the condition.
     */
    update() {
        const rows = [...this.rowsEl.children];
        const used = rows.map((row) => row.querySelector('select').value);
        rows.forEach((row, index) => {
            const fieldEl = row.querySelector('select');
            for (const option of fieldEl.options) {
                option.disabled = option.value !== '' && option.value !== fieldEl.value && used.includes(option.value);
            }
            row.querySelector('button').setAttribute('aria-label', this.config.labels.deleteRow.replace('%s', index + 1));
        });
        this.write();
    }

    write() {
        const condition = {};
        for (const row of this.rowsEl.children) {
            const [fieldEl, valuesEl] = row.querySelectorAll('select');
            const values = [...valuesEl.selectedOptions].map((option) => option.value);
            if (fieldEl.value && values.length) {
                condition[fieldEl.value] = values;
            }
        }
        this.input.value = Object.keys(condition).length
            ? JSON.stringify([{ operator: this.operatorEl.value, condition }])
            : '';
    }
}
