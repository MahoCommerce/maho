/**
 * Maho
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 *
 */
class EavAttributeEditForm {

    constructor(formId, inputTypeDefs, config = {}) {
        this.inputTypeDefs = inputTypeDefs;
        this.config = config;
        this.formEl = document.getElementById(formId);
        if (!this.formEl) {
            throw new Error(`Form with ID ${formId} not found in DOM`);
        }
        this.bindEventListeners();
        this.updateForm();
    }

    bindEventListeners() {
        this.formEl.addEventListener('change', this.updateForm.bind(this), { capture: true });
    }

    setRowVisibility(id, isVisible) {
        const el = document.getElementById(id);
        if (el) {
            const tr = el.closest('tr');
            if (isVisible) {
                tr.classList.remove('no-display');
            } else {
                tr.blur();
                tr.classList.add('no-display');
            }
        }
    }

    setFieldsetVisibility(id, isVisible) {
        const el = document.getElementById(id);
        if (el) {
            if (isVisible) {
                el.classList.remove('no-display');
                el.previousElementSibling.classList.remove('no-display');
            } else {
                el.classList.add('no-display');
                el.previousElementSibling.classList.add('no-display');
            }
        }
    }

    getInputTypeValue() {
        const el = document.getElementById('frontend_input');
        return el ? el.value : '';
    }

    updateForm() {
        // Before update form callback
        if (typeof this.config.callbacks?.beforeUpdateForm === 'function') {
            this.config.callbacks.beforeUpdateForm();
        }

        // Reset visibility of all rows and fieldsets
        this.formEl.querySelectorAll('tr.no-display').forEach((el) => {
            el.classList.remove('no-display');
        });
        this.formEl.querySelectorAll('.fieldset.no-display').forEach((el) => {
            el.classList.remove('no-display');
            el.previousElementSibling.classList.remove('no-display');
        });

        // Manually trigger dependence block conditions
        this.formEl.querySelectorAll('input, select, textarea').forEach((el) => {
            el.dispatchEvent(new FormElementDependenceEvent());
        });

        const inputType = this.getInputTypeValue();

        // Hide fields defined in config.xml eav_inputtypes nodes
        const hiddenFields = this.inputTypeDefs[inputType]?.hide_fields ?? [];
        for (let field of hiddenFields) {
            if (field === '_front_fieldset') {
                this.setFieldsetVisibility('front_fieldset', false);
            } else if (field === '_scope') {
                this.setRowVisibility('is_global', false);
            } else {
                // TODO, check if is fieldset
                this.setRowVisibility(field, false);
            }
        }

        // Show default value field defined in config.xml eav_inputtypes nodes
        let defaultValueField = this.inputTypeDefs[inputType]?.default_value_field;
        if (hiddenFields.includes('_default_value')) {
            defaultValueField = '';
        }
        for (let field of ['text', 'textarea', 'date', 'yesno']) {
            this.setRowVisibility(`default_value_${field}`, `default_value_${field}` === defaultValueField);
        }

        // After update form callback
        if (typeof this.config.callbacks?.afterUpdateForm === 'function') {
            this.config.callbacks.afterUpdateForm();
        }
    }
}
