/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class VarienRulesForm {
    constructor(parent, newChildUrl) {
        this.parent = document.getElementById(parent) || parent;
        this.newChildUrl = newChildUrl;
        this.shownElement = null;
        this.updateElement = null;
        this.chooserSelectedItems = new Map();
        this.readOnly = false;

        const elems = this.parent.querySelectorAll('.rule-param');
        elems.forEach(elem => this.initParam(elem));
    }

    setReadonly(readonly) {
        this.readOnly = readonly;

        // Handle remove buttons
        const removeElems = this.parent.querySelectorAll('.rule-param-remove');
        removeElems.forEach(element => {
            element.style.display = this.readOnly ? 'none' : '';
        });

        // Handle new child buttons
        const newChildElems = this.parent.querySelectorAll('.rule-param-new-child');
        newChildElems.forEach(element => {
            element.style.display = this.readOnly ? 'none' : '';
        });

        // Handle labels
        const paramElems = this.parent.querySelectorAll('.rule-param');
        paramElems.forEach(container => {
            const label = container.querySelector('.label');
            if (label) {
                if (this.readOnly) {
                    label.classList.add('label-disabled');
                } else {
                    label.classList.remove('label-disabled');
                }
            }
        });
    }

    initParam(container) {
        container.rulesObject = this;

        const label = container.querySelector('.label');
        if (label) {
            label.addEventListener('click', (e) => this.showParamInputField(container, e));
        }

        const elem = container.querySelector('.element');
        if (elem) {
            const trig = elem.querySelector('.rule-chooser-trigger');
            if (trig) {
                trig.addEventListener('click', (e) => this.toggleChooser(container, e));
            }

            const apply = elem.querySelector('.rule-param-apply');
            if (apply) {
                apply.addEventListener('click', (e) => this.hideParamInputField(container, e));
            } else {
                const changeElem = elem.querySelector('.element-value-changer');
                if (changeElem) {
                    changeElem.container = container;
                    if (!changeElem.multiple) {
                        // Only hide on change for select elements, not for text inputs
                        if (changeElem.tagName === 'SELECT') {
                            changeElem.addEventListener('change', (e) => this.hideParamInputField(container, e));
                        }
                    }
                    changeElem.addEventListener('blur', (e) => this.hideParamInputField(container, e));

                    // Allow Enter key to submit the value for text inputs
                    if (changeElem.tagName === 'INPUT') {
                        changeElem.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter') {
                                this.hideParamInputField(container, e);
                            }
                        });
                    }
                }
            }
        }

        // Add attribute change handler for reloading value field when attribute changes
        const attributeElem = container.querySelector('select[name*="[attribute]"]');
        if (attributeElem) {
            attributeElem.addEventListener('change', (e) => this.onAttributeChange(container, e));
        }

        const remove = container.querySelector('.rule-param-remove');
        if (remove) {
            remove.addEventListener('click', (e) => this.removeRuleEntry(container, e));
        }
    }

    showChooserElement(chooser) {
        this.chooserSelectedItems.clear();

        if (chooser.classList.contains('no-split')) {
            this.chooserSelectedItems.set(this.updateElement.value, 1);
        } else {
            const values = this.updateElement.value.split(',');
            values.forEach(value => {
                const s = value.trim();
                if (s !== '') {
                    this.chooserSelectedItems.set(s, 1);
                }
            });
        }

        const formData = new FormData();
        formData.append('form_key', window.FORM_KEY || '');
        Array.from(this.chooserSelectedItems.keys()).forEach(key => {
            formData.append('selected[]', key);
        });

        mahoFetch(chooser.getAttribute('url'), {
            method: 'POST',
            body: formData
        })
        .then(responseText => {
            if (this._processSuccess(responseText)) {
                updateElementHtmlAndExecuteScripts(chooser, responseText);
                this.showChooserLoaded(chooser, responseText);
            }
        })
        .catch(error => {
            this._processFailure(error);
        });
    }

    showChooserLoaded(chooser, transport) {
        chooser.style.display = 'block';
    }

    showChooser(container, event) {
        const chooser = container.closest('li');
        if (!chooser) {
            return;
        }
        const chooserElement = chooser.querySelector('.rule-chooser');
        if (!chooserElement) {
            return;
        }
        this.showChooserElement(chooserElement);
    }

    hideChooser(container, event) {
        const chooser = container.closest('li');
        if (!chooser) {
            return;
        }
        const chooserElement = chooser.querySelector('.rule-chooser');
        if (!chooserElement) {
            return;
        }
        chooserElement.style.display = 'none';
    }

    toggleChooser(container, event) {
        if (this.readOnly) {
            return false;
        }

        const chooser = container.closest('li').querySelector('.rule-chooser');
        if (!chooser) {
            return;
        }

        if (chooser.style.display === 'block') {
            chooser.style.display = 'none';
            this.cleanChooser(container, event);
        } else {
            this.showChooserElement(chooser);
        }
    }

    cleanChooser(container, event) {
        const chooser = container.closest('li').querySelector('.rule-chooser');
        if (!chooser) {
            return;
        }
        chooser.innerHTML = '';
    }

    showParamInputField(container, event) {
        if (this.readOnly) {
            return false;
        }

        if (this.shownElement) {
            this.hideParamInputField(this.shownElement, event);
        }

        container.classList.add('rule-param-edit');
        const elemContainer = container.querySelector('.element');

        const inputElem = elemContainer?.querySelector('input.input-text');
        if (inputElem) {
            inputElem.focus();
            if (inputElem.id && inputElem.id.match(/__value$/)) {
                this.updateElement = inputElem;
            }
        }

        const changeElem = elemContainer?.querySelector('.element-value-changer');
        if (changeElem) {
            changeElem.focus();
        }

        this.shownElement = container;
    }

    hideParamInputField(container, event) {
        container.classList.remove('rule-param-edit');
        const label = container.querySelector('.label');

        if (!container.classList.contains('rule-param-new-child')) {
            const selectElem = container.querySelector('.element-value-changer');
            if (selectElem && selectElem.options) {
                const selectedOptions = Array.from(selectElem.options)
                    .filter(option => option.selected)
                    .map(option => option.text);

                const str = selectedOptions.join(', ');
                label.innerHTML = str !== '' ? str : '...';
            }

            const inputElem = container.querySelector('input.input-text');
            if (inputElem) {
                let str = inputElem.value.replace(/(^\s+|\s+$)/g, '');
                inputElem.value = str;
                if (str === '') {
                    str = '...';
                } else if (str.length > 100) {
                    str = str.substr(0, 100) + '...';
                }
                label.innerHTML = xssFilter(str);
            }
        } else {
            const elem = container.querySelector('.element-value-changer');
            if (elem && elem.value) {
                this.addRuleNewChild(elem);
            }
            if (elem) {
                elem.value = '';
            }
        }

        const elem = container.querySelector('.element-value-changer') || container.querySelector('input.input-text');
        if (elem && elem.id && elem.id.match(/__value$/)) {
            this.hideChooser(container, event);
            this.updateElement = null;
        }

        this.shownElement = null;
    }

    addRuleNewChild(elem) {
        const parent_id = elem.id.replace(/^.*__(.*)__.*$/, '$1');
        const children_ul = document.getElementById(
            elem.id.replace(/__/g, ':').replace(/[^:]*$/, 'children').replace(/:/g, '__')
        );

        let max_id = 0;
        const children_inputs = children_ul.querySelectorAll('input.hidden');
        children_inputs.forEach(el => {
            if (el.id.match(/__type$/)) {
                const i = parseInt(el.id.replace(/^.*__.*?([0-9]+)__.*$/, '$1'));
                max_id = i > max_id ? i : max_id;
            }
        });

        const new_id = parent_id + '--' + (max_id + 1);
        const new_type = elem.value;
        const new_elem = document.createElement('LI');
        new_elem.className = 'rule-param-wait';
        new_elem.innerHTML = window.Translator ? Translator.translate('Please wait, loading...') : 'Please wait, loading...';
        children_ul.insertBefore(new_elem, elem.closest('li'));

        const formData = new FormData();
        formData.append('form_key', window.FORM_KEY || '');
        formData.append('type', new_type.replace('/', '-'));
        formData.append('id', new_id);

        mahoFetch(this.newChildUrl, {
            method: 'POST',
            body: formData
        })
        .then(responseText => {
            if (this._processSuccess(responseText)) {
                updateElementHtmlAndExecuteScripts(new_elem, responseText);
                this.onAddNewChildComplete(new_elem);
            }
        })
        .catch(error => {
            this._processFailure(error);
        });
    }

    _processSuccess(responseText) {
        try {
            const response = JSON.parse(responseText);
            if (response.error) {
                alert(response.message);
            }
            if (response.ajaxExpired && response.ajaxRedirect) {
                location.href = response.ajaxRedirect;
            }
            return false;
        } catch (e) {
            // Not JSON, continue processing as HTML
            return true;
        }
    }

    _processFailure(error) {
        console.error('Request failed:', error);
        if (window.BASE_URL) {
            location.href = window.BASE_URL;
        }
    }

    onAddNewChildComplete(new_elem) {
        if (this.readOnly) {
            return false;
        }

        new_elem.classList.remove('rule-param-wait');
        const elems = new_elem.querySelectorAll('.rule-param');
        elems.forEach(elem => this.initParam(elem));
    }

    removeRuleEntry(container, event) {
        const li = container.closest('li');
        if (li && li.parentNode) {
            li.parentNode.removeChild(li);
        }
    }

    chooserGridInit(grid) {
        // grid.reloadParams = Array.from(this.chooserSelectedItems.keys());
    }

    chooserGridRowInit(grid, row) {
        if (!grid.reloadParams) {
            grid.reloadParams = {'selected[]': Array.from(this.chooserSelectedItems.keys())};
        }
    }

    chooserGridRowClick(grid, event) {
        const trElement = event.target.closest('tr');
        const isInput = event.target.tagName === 'INPUT';

        if (trElement) {
            const checkbox = trElement.querySelector('input');
            if (checkbox) {
                const checked = isInput ? checkbox.checked : !checkbox.checked;
                grid.setCheckboxChecked(checkbox, checked);
            }
        }
    }

    chooserGridCheckboxCheck(grid, element, checked) {
        if (checked) {
            if (!element.closest('th')) {
                this.chooserSelectedItems.set(element.value, 1);
            }
        } else {
            this.chooserSelectedItems.delete(element.value);
        }
        grid.reloadParams = {'selected[]': Array.from(this.chooserSelectedItems.keys())};
        this.updateElement.value = Array.from(this.chooserSelectedItems.keys()).join(', ');
    }

    onAttributeChange(container, event) {
        if (this.readOnly) {
            return false;
        }

        const attributeElem = event.target;
        // Find type element - it should be a sibling of the attribute element or in the same container
        let typeElem = container.querySelector('input[name*="[type]"]');
        if (!typeElem) {
            // Alternative: look for type element by ID pattern
            const attributeId = attributeElem.id;
            const typeId = attributeId.replace('__attribute', '__type');
            typeElem = document.getElementById(typeId);
        }
        if (!typeElem) {
            return;
        }

        const containerLi = container.closest('li');
        if (!containerLi) {
            return;
        }

        // Preserve existing operator and value before reloading
        const operatorElem = container.querySelector('select[name*="[operator]"]');
        const valueElem = container.querySelector('input[name*="[value]"], select[name*="[value]"]');
        const existingOperator = operatorElem ? operatorElem.value : null;
        const existingValue = valueElem ? valueElem.value : null;

        // Get the condition ID and type
        const conditionId = typeElem.value;
        const attributeValue = attributeElem.value;

        // Create type parameter with attribute
        const typeParam = conditionId.replace(/\//g, '-') + '|' + attributeValue;

        // Get element ID for this condition - remove prefix and suffix
        let elementId = typeElem.id.replace(/__type$/, '');
        // Remove the "conditions__" prefix if present
        elementId = elementId.replace(/^conditions__/, '');

        // Show loading indicator
        const loadingElem = document.createElement('span');
        loadingElem.innerHTML = ' Loading...';
        loadingElem.className = 'rule-param-loading';
        loadingElem.style.fontStyle = 'italic';
        loadingElem.style.color = '#666';
        containerLi.appendChild(loadingElem);

        // Make AJAX request to reload condition HTML with new attribute
        const formData = new FormData();
        formData.append('form_key', window.FORM_KEY || '');
        formData.append('type', typeParam);
        formData.append('id', elementId);

        mahoFetch(this.newChildUrl, {
            method: 'POST',
            body: formData
        })
        .then(responseText => {
            this.onAttributeChangeComplete(containerLi, loadingElem);
            if (this._processSuccess(responseText)) {
                // Create a temporary container for the new HTML
                const tempDiv = document.createElement('div');
                updateElementHtmlAndExecuteScripts(tempDiv, responseText);

                // Store reference to parent before removing current element
                const parentUl = containerLi.parentNode;
                const nextSibling = containerLi.nextSibling;

                // Remove the current condition
                parentUl.removeChild(containerLi);

                // Add all the new elements from the response
                while (tempDiv.firstChild) {
                    const newElement = tempDiv.firstChild;
                    if (nextSibling) {
                        parentUl.insertBefore(newElement, nextSibling);
                    } else {
                        parentUl.appendChild(newElement);
                    }

                    // Re-initialize rule params in this element
                    if (newElement.nodeType === 1) { // Element node
                        const elems = newElement.querySelectorAll ?
                            newElement.querySelectorAll('.rule-param') : [];
                        elems.forEach(elem => this.initParam(elem));

                        // Also check if the element itself is a rule-param
                        if (newElement.classList && newElement.classList.contains('rule-param')) {
                            this.initParam(newElement);
                        }

                        // Restore preserved values after reloading
                        if (existingOperator || existingValue) {
                            const newOperatorElem = newElement.querySelector('select[name*="[operator]"]');
                            const newValueElem = newElement.querySelector('input[name*="[value]"], select[name*="[value]"]');

                            if (newOperatorElem && existingOperator) {
                                // Check if the existing operator is still valid for this attribute
                                const operatorOption = newOperatorElem.querySelector(`option[value="${existingOperator}"]`);
                                if (operatorOption) {
                                    newOperatorElem.value = existingOperator;
                                }
                            }

                            if (newValueElem && existingValue) {
                                // Only restore value if the input type is compatible
                                if (newValueElem.type === 'text' || newValueElem.tagName === 'SELECT') {
                                    newValueElem.value = existingValue;
                                }
                            }
                        }
                    }
                }
            }
        })
        .catch(error => {
            this._processFailure(error);
        });
    }

    onAttributeChangeComplete(containerLi, loadingElem) {
        if (loadingElem && loadingElem.parentNode) {
            loadingElem.parentNode.removeChild(loadingElem);
        }
    }

}

// For backwards compatibility, create the old-style constructor
window.VarienRulesForm = VarienRulesForm;
