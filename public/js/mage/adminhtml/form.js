/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2017-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class varienForm {
    constructor(formId, validationUrl) {
        this.formId = formId;
        this.validationUrl = validationUrl;
        this.submitUrl = false;

        const formElement = document.getElementById(this.formId);
        if(formElement){
            this.validator = new Validation(this.formId, {onElementValidate : this.checkErrors.bind(this)});
        }
        this.errorSections = new Map();
    }

    checkErrors(result, elm) {
        if(!result)
            varienElementMethods.setHasError.call(this, elm, true, this);
        else
            varienElementMethods.setHasError.call(this, elm, false, this);
    }

    validate() {
        varienGlobalEvents?.fireEvent('formValidate', this.formId);
        if(this.validator && this.validator.validate()){
            if(this.validationUrl){
                this._validate();
            }
            return true;
        }
        return false;
    }

    submit(url) {
        varienGlobalEvents?.fireEvent('formSubmit', this.formId);
        this.errorSections = new Map();
        this.canShowError = true;
        this.submitUrl = url;
        if(this.validator && this.validator.validate()){
            if(this.validationUrl){
                this._validate();
            }
            else{
                this._submit();
            }
            return true;
        }
        return false;
    }

    _validate() {
        const formElement = document.getElementById(this.formId);
        const formData = new FormData(formElement);

        mahoFetch(this.validationUrl, {
            method: 'POST',
            body: formData
        })
        .then(data => {
            this._processValidationResult(data);
        })
        .catch(error => {
            this._processFailure();
        });
    }

    _processValidationResult(response) {
        varienGlobalEvents?.fireEvent('formValidateAjaxComplete', response);
        if(response.error){
            setMessagesDivHtml(response.message);
        }
        else{
            this._submit();
        }
    }

    _processFailure() {
        location.href = BASE_URL;
    }

    _submit() {
        const formElement = document.getElementById(this.formId);
        if(this.submitUrl){
            formElement.action = this.submitUrl;
        }
        formElement.submit();
    }
}

/**
 * redeclare Validation.isVisible function
 *
 * use for not visible elements validation
 */
if (typeof Validation !== 'undefined') {
    Validation.isVisible = function(elm){
        while (elm && elm.tagName !== 'BODY') {
            if (elm.disabled) return false;
            if ((elm.classList.contains('template') && elm.classList.contains('no-display'))
                 || elm.classList.contains('ignore-validate')){
                return false;
            }
            elm = elm.parentNode;
        }
        return true;
    };
}

/**
 *  Additional elements methods
 */
const varienElementMethods = {
    setHasChanges(element, event) {
        const elem = (typeof element === 'string') ? document.getElementById(element) :
                     (element && element.nodeType) ? element : this;
        if(!elem || !elem.classList || elem.classList.contains('no-changes')) return;
        let elm = elem;
        while(elm && elm.tagName !== 'BODY') {
            if(elm.statusBar) {
                const statusBarElement = typeof elm.statusBar === 'string' ? document.getElementById(elm.statusBar) : elm.statusBar;
                if (statusBarElement) {
                    statusBarElement.classList.add('changed');
                }
            }
            elm = elm.parentNode;
        }
    },
    setHasError(element, flag, form) {
        let elm = element;
        while(elm && elm.tagName !== 'BODY') {
            if(elm.statusBar){
                if(!form.errorSections.has(elm.statusBar.id))
                    form.errorSections.set(elm.statusBar.id, flag);
                if(flag){
                    const statusBarElement = typeof elm.statusBar === 'string' ? document.getElementById(elm.statusBar) : elm.statusBar;
                    if (statusBarElement) {
                        statusBarElement.classList.add('error');
                        if(form.canShowError && statusBarElement.show){
                            form.canShowError = false;
                            statusBarElement.style.display = '';
                        }
                    }
                    form.errorSections.set(elm.statusBar.id, flag);
                }
                else if(!form.errorSections.get(elm.statusBar.id)){
                    const statusBarElement = typeof elm.statusBar === 'string' ? document.getElementById(elm.statusBar) : elm.statusBar;
                    if (statusBarElement) {
                        statusBarElement.classList.remove('error');
                    }
                }
            }
            elm = elm.parentNode;
        }
        this.canShowElement = false;
    }
};

// Add methods to Element prototype for backward compatibility
if (typeof Element !== 'undefined' && Element.prototype) {
    Object.assign(Element.prototype, varienElementMethods);
}

// Global bind changes
let varienWindowOnloadCache = {};
function varienWindowOnload(useCache){
    const dataElements = document.querySelectorAll('input, select, textarea');
    for(let i = 0; i < dataElements.length; i++){
        if(dataElements[i] && dataElements[i].id){
            if ((!useCache) || (!varienWindowOnloadCache[dataElements[i].id])) {
                dataElements[i].addEventListener('change', function() {
                    this.setHasChanges();
                });
                if (useCache) {
                    varienWindowOnloadCache[dataElements[i].id] = true;
                }
            }
        }
    }
}
window.addEventListener('load', varienWindowOnload);

class RegionUpdater {
    constructor(countryEl, regionTextEl, regionSelectEl, regions, disableAction, clearRegionValueOnDisable) {
        this.countryEl = typeof countryEl === 'string' ? document.getElementById(countryEl) : countryEl;
        this.regionTextEl = typeof regionTextEl === 'string' ? document.getElementById(regionTextEl) : regionTextEl;
        this.regionSelectEl = typeof regionSelectEl === 'string' ? document.getElementById(regionSelectEl) : regionSelectEl;
//        // clone for select element (#6924)
//        this._regionSelectEl = {};
//        this.tpl = new Template('<select class="#{className}" name="#{name}" id="#{id}">#{innerHTML}</select>');
        this.config = regions['config'];
        delete regions.config;
        this.regions = regions;
        this.disableAction = (typeof disableAction=='undefined') ? 'hide' : disableAction;
        this.clearRegionValueOnDisable = (typeof clearRegionValueOnDisable == 'undefined') ? false : clearRegionValueOnDisable;

        if (this.regionSelectEl.options.length<=1) {
            this.update();
        }
        else {
            this.lastCountryId = this.countryEl.value;
        }

        this.countryEl.changeUpdater = this.update.bind(this);

        this.countryEl.addEventListener('change', this.update.bind(this));
    }

    _checkRegionRequired() {
        let label, wildCard;
        const elements = [this.regionTextEl, this.regionSelectEl];
        const that = this;
        if (typeof this.config === 'undefined') {
            return;
        }
        const regionRequired = this.config.regions_required.indexOf(this.countryEl.value) >= 0;

        elements.forEach(function(currentElement) {
            if(!currentElement) {
                return;
            }
            if (typeof Validation !== 'undefined') {
                Validation.reset(currentElement);
            }
            label = document.querySelector('label[for="' + currentElement.id + '"]');
            if (label) {
                wildCard = label.querySelector('em') || label.querySelector('span.required');
                if (!wildCard) {
                    label.insertAdjacentHTML('beforeend', ' <span class="required">*</span>');
                    wildCard = label.querySelector('span.required');
                }
                const topElement = label.closest('tr') || label.closest('li');
                if (!that.config.show_all_regions && topElement) {
                    if (regionRequired) {
                        topElement.style.display = '';
                    } else {
                        topElement.style.display = 'none';
                    }
                }
            }

            if (label && wildCard) {
                if (!regionRequired) {
                    wildCard.style.display = 'none';
                } else {
                    wildCard.style.display = '';
                }
            }

            const isVisible = currentElement.offsetParent !== null;
            if (!regionRequired || !isVisible) {
                if (currentElement.classList.contains('required-entry')) {
                    currentElement.classList.remove('required-entry');
                }
                if ('select' === currentElement.tagName.toLowerCase() &&
                    currentElement.classList.contains('validate-select')
                ) {
                    currentElement.classList.remove('validate-select');
                }
            } else {
                if (!currentElement.classList.contains('required-entry')) {
                    currentElement.classList.add('required-entry');
                }
                if ('select' === currentElement.tagName.toLowerCase() &&
                    !currentElement.classList.contains('validate-select')
                ) {
                    currentElement.classList.add('validate-select');
                }
            }
        });
    }

    update() {
        if (this.regions[this.countryEl.value]) {
            if (this.lastCountryId != this.countryEl.value) {
                var i, option, region, def, regionId;

                def = this.regionSelectEl.getAttribute('defaultValue');
                if (this.regionTextEl) {
                    if (!def) {
                        def = this.regionTextEl.value.toLowerCase();
                    }
                    this.regionTextEl.value = '';
                }

                this.regionSelectEl.options.length = 1;
                for (regionId in this.regions[this.countryEl.value]) {
                    region = this.regions[this.countryEl.value][regionId];

                    option = document.createElement('OPTION');
                    option.value = regionId;
                    option.text = stripTags(region.name);
                    option.title = region.name;

                    if (this.regionSelectEl.options.add) {
                        this.regionSelectEl.options.add(option);
                    } else {
                        this.regionSelectEl.appendChild(option);
                    }

                    if (regionId == def || region.name.toLowerCase() == def || region.code.toLowerCase() == def) {
                        this.regionSelectEl.value = regionId;
                    }
                }
            }
            this.sortSelect();
            if (this.disableAction == 'hide') {
                if (this.regionTextEl) {
                    this.regionTextEl.style.display = 'none';
                    this.regionTextEl.style.disabled = true;
                }
                this.regionSelectEl.style.display = '';
                this.regionSelectEl.disabled = false;
            } else if (this.disableAction == 'disable') {
                if (this.regionTextEl) {
                    this.regionTextEl.disabled = true;
                }
                this.regionSelectEl.disabled = false;
            }
            this.setMarkDisplay(this.regionSelectEl, true);

            this.lastCountryId = this.countryEl.value;
        } else {
            this.sortSelect();
            if (this.disableAction == 'hide') {
                if (this.regionTextEl) {
                    this.regionTextEl.style.display = '';
                    this.regionTextEl.style.disabled = false;
                }
                this.regionSelectEl.style.display = 'none';
                this.regionSelectEl.disabled = true;
            } else if (this.disableAction == 'disable') {
                if (this.regionTextEl) {
                    this.regionTextEl.disabled = false;
                }
                this.regionSelectEl.disabled = true;
                if (this.clearRegionValueOnDisable) {
                    this.regionSelectEl.value = '';
                }
            } else if (this.disableAction == 'nullify') {
                this.regionSelectEl.options.length = 1;
                this.regionSelectEl.value = '';
                this.regionSelectEl.selectedIndex = 0;
                this.lastCountryId = '';
            }
            this.setMarkDisplay(this.regionSelectEl, false);

//            // clone required stuff from select element and then remove it
//            this._regionSelectEl.className = this.regionSelectEl.className;
//            this._regionSelectEl.name      = this.regionSelectEl.name;
//            this._regionSelectEl.id        = this.regionSelectEl.id;
//            this._regionSelectEl.innerHTML = this.regionSelectEl.innerHTML;
//            Element.remove(this.regionSelectEl);
//            this.regionSelectEl = null;
        }
        varienGlobalEvents.fireEvent("address_country_changed", this.countryEl);
        this._checkRegionRequired();
    }

    setMarkDisplay(elem, display) {
        if(elem.parentNode.parentNode){
            var marks = elem.parentNode.parentNode.querySelectorAll('.required');
            if(marks[0]){
                display ? marks[0].style.display = '' : marks[0].style.display = 'none';
            }
        }
    }
    sortSelect() {
        var elem = this.regionSelectEl;
        var tmpArray = new Array();
        var currentVal = elem.value;
        for (var i = 0; i < elem.options.length; i++) {
            if (i == 0) {
                continue;
            }
            tmpArray[i-1] = new Array();
            tmpArray[i-1][0] = elem.options[i].text;
            tmpArray[i-1][1] = elem.options[i].value;
        }
        tmpArray.sort();
        for (var i = 1; i <= tmpArray.length; i++) {
            var op = new Option(tmpArray[i-1][0], tmpArray[i-1][1]);
            elem.options[i] = op;
        }
        elem.value = currentVal;
        return;
    }
}

class selectUpdater {
    constructor(firstSelect, secondSelect, selectFirstMessage, noValuesMessage, values, selected) {
        this.first = document.getElementById(firstSelect);
        this.second = document.getElementById(secondSelect);
        this.message = selectFirstMessage;
        this.values = values;
        this.noMessage = noValuesMessage;
        this.selected = selected;

        this.update();

        this.first.addEventListener('change', this.update.bind(this));
    }

    update() {
        this.second.length = 0;
        this.second.value = '';

        if (this.first.value && this.values[this.first.value]) {
            var optionValue, optionTitle;
            for (optionValue in this.values[this.first.value]) {
                optionTitle = this.values[this.first.value][optionValue];

                this.addOption(this.second, optionValue, optionTitle);
            }
            this.second.disabled = false;
        } else if (this.first.value && !this.values[this.first.value]) {
            this.addOption(this.second, '', this.noMessage);
        } else {
            this.addOption(this.second, '', this.message);
            this.second.disabled = true;
        }
    }

    addOption(select, value, text) {
        const option = document.createElement('OPTION');
        option.value = value;
        option.text = text;

        if (this.selected && option.value == this.selected) {
            option.selected = true;
            this.selected = false;
        }

        if (select.options.add) {
            select.options.add(option);
        } else {
            select.appendChild(option);
        }
    }
};

/**
 * Custom event that can be dispatched on dependent form elements to trigger an update
 */
class formElementDependenceEvent extends Event {
    /**
     * @param {string} [eventName] - the name of the event, defaults to 'update', no other values are currently supported
     */
    constructor(eventName = 'update') {
        super(eventName);
    }
}

/**
 * Observer that watches for dependent form elements with support for complex conditions
 */
class formElementDependenceController {
    static MODE_NOT = 'NOT';
    static MODE_AND = 'AND';
    static MODE_OR  = 'OR';
    static MODE_XOR = 'XOR';

    /**
     * @param {Object.<string, Object>} elementsMap - key/value pairs of target fields and their conditions to be visible
     * @param {Object} [config] - config options, see Mage_Adminhtml_Block_Widget_Form_Element_Dependence::addConfigOptions()
     * @param {string|false} [config.on_event]
     * @param {Object.<string, string>} [config.field_map]
     * @param {Object.<string, string>} [config.field_values]
     * @param {number} [config.levels_up]
     * @param {boolean} [config.can_edit_price]
     */
    constructor(elementsMap, config = {}) {
        this.config = config;
        for (let [targetField, condition] of Object.entries(elementsMap)) {
            this.trackChange(null, targetField, condition);
            this.bindEventListeners(condition, [targetField, condition]);
        }
    }

    /**
     * Determine if the condition is a logical operator
     *
     * @param {string} operator
     * @returns {boolean}
     */
    isLogicalOperator(operator) {
        const operators = [
            formElementDependenceController.MODE_NOT,
            formElementDependenceController.MODE_AND,
            formElementDependenceController.MODE_OR,
            formElementDependenceController.MODE_XOR,
        ];
        return operators.includes(operator);
    }

    /**
     * Recursively bind onchange events to all elements that can trigger a change
     *
     * @param {Object} condition
     * @param {Array<any>} eventArgs
     */
    bindEventListeners(condition, eventArgs = []) {
        for (let [dependentField, subcondition] of Object.entries(condition)) {
            if (this.isLogicalOperator(subcondition?.operator)) {
                this.bindEventListeners(subcondition.condition, eventArgs);
            } else {
                const dependentEl = document.getElementById(this.mapFieldId(dependentField));
                if (dependentEl) {
                    if (this.config.on_event !== false) {
                        dependentEl.addEventListener(this.config.on_event ?? 'change', (event) => {
                            this.trackChange(event, ...eventArgs);
                        });
                    }
                    dependentEl.addEventListener('update', (event) => {
                        if (event instanceof formElementDependenceEvent) {
                            this.trackChange(event, ...eventArgs);
                        }
                    });
                }
            }
        }
    }

    /**
     * Map field alias to associated DOM ID
     *
     * @param {string} field - field alias
     * @returns {string}
     */
    mapFieldId(field) {
        return this.config.field_map?.[field] ?? field;
    }

    /**
     * Return the TR element containing the form element
     *
     * @param {string} id - the form element's DOM ID
     * @returns {HTMLElement?}
     */
    findParentRow(id) {
        const el = document.getElementById(this.mapFieldId(id));
        if (!el) {
            return document.getElementById('row_' + this.mapFieldId(id));
        }
        if (typeof this.config.levels_up === 'number' && this.config.levels_up > 0) {
            let parent = el;
            for (let i = 0; parent && i < this.config.levels_up; i++) {
                parent = parent.parentElement;
            }
            return parent;
        }
        return el.closest('tr');
    }

    /**
     * Return an array of selected values from a select or multiselect element
     *
     * @param {HTMLElement} el
     * @returns {Array<string>}
     */
    getSelectValues(el) {
        return Array.from(el.querySelectorAll('option:checked'), (option) => option.value);
    }

    /**
     * Toggle the 'no-display' class on an element
     *
     * @param {HTMLElement} el
     * @param {boolean} force
     */
    toggleElem(el, force = null) {
        el.classList.toggle('no-display', force);
    }

    /**
     * Add the 'no-display' class to an element
     *
     * @param {HTMLElement} el
     */
    hideElem(el) {
        this.toggleElem(el, true);
    }

    /**
     * Remove the 'no-display' class from an element
     *
     * @param {HTMLElement} el
     */
    showElem(el) {
        this.toggleElem(el, false);
    }

    /**
     * Recursively evaluate a complex condition
     *
     * @param {Object} condition - key/value pairs of field names and wanted values, or subconditions
     * @param {string} [mode] - logical operation to evaluate with, defaults to "AND"
     */
    evalCondition(condition, mode = formElementDependenceController.MODE_AND) {
        // If there are no subconditions, evaluate to true
        if (Object.keys(condition).length === 0) {
            return true;
        }
        const results = [];
        for (let [dependentField, subcondition] of Object.entries(condition)) {
            let result = false;
            if (this.isLogicalOperator(subcondition?.operator)) {
                // If we have a logical operator, recurse
                result = this.evalCondition(subcondition.condition, subcondition.operator);
            } else {
                // Otherwise check if we have this element in the form, or use fallback value
                let refValues = Array.isArray(subcondition) ? subcondition : [subcondition];
                let dependentValues = [];
                const dependentEl = document.getElementById(this.mapFieldId(dependentField));
                if (dependentEl) {
                    if (dependentEl.tagName === 'SELECT') {
                        dependentValues = this.getSelectValues(dependentEl);
                        refValues = refValues.map(String);
                    } else if (dependentEl.tagName === 'INPUT' && ['radio', 'checkbox'].includes(dependentEl.type)) {
                        dependentValues.push(dependentEl.checked);
                    } else {
                        dependentValues.push(dependentEl.value);
                        refValues = refValues.map(String);
                    }
                } else {
                    const fallbackValues = this.config.field_values?.[this.mapFieldId(dependentField)];
                    if (fallbackValues) {
                        dependentValues = Array.isArray(fallbackValues) ? fallbackValues : [fallbackValues];
                    }
                }
                result = dependentValues.some((val) => refValues.includes(val));
            }
            results.push(result)
        }
        if (mode === formElementDependenceController.MODE_NOT) {
            return results.every((value) => value === false);
        } else if (mode === formElementDependenceController.MODE_AND) {
            return results.every((value) => value === true);
        } else if (mode === formElementDependenceController.MODE_OR) {
            return results.some((value) => value === true);
        } else if (mode === formElementDependenceController.MODE_XOR) {
            return results.filter((value) => value === true).length === 1;
        }
    }

    /**
     * Recursively evaluate a complex condition
     *
     * @param {Event?} event - the event object that triggered this function
     * @param {string} field - field name alias or ID of the target element
     * @param {Object} condition - key/value pairs of field names and wanted values, or subconditions
     */
    trackChange(event, field, condition) {
        const rowEl = this.findParentRow(field);
        if (!rowEl) {
            return;
        }

        const shouldShowUp = this.evalCondition(condition);

        // Find all child form elements, except hidden fields because they may have custom logic
        rowEl.querySelectorAll('input:not([type=hidden]), textarea, select').forEach((el) => {
            // If Use Default is checked, don't toggle the main form element, only toggle the inherit box itself
            const inheritCheckboxEl = document.getElementById(el.id + '_inherit');
            if (inheritCheckboxEl && inheritCheckboxEl.checked) {
                return;
            }
            // Do not enable if can_edit_price option is set to true
            if (shouldShowUp && this.config.can_edit_price != undefined && !this.config.can_edit_price) {
                return;
            }
            el.disabled = !shouldShowUp;
        });

        this.toggleElem(rowEl, !shouldShowUp);
    }
}

// optional_zip_countries.phtml
function onAddressCountryChanged(countryElement) {
    var zipElementId = countryElement.id.replace(/country_id/, 'postcode');
    // Ajax-request and normal content load compatibility
    if (document.getElementById(zipElementId) != undefined) {
        setPostcodeOptional(document.getElementById(zipElementId), countryElement.value);
    } else {
        window.addEventListener("load", function () {
            setPostcodeOptional(document.getElementById(zipElementId), countryElement.value);
        });
    }
}

function setPostcodeOptional(zipElement, country) {
    var spanElement = zipElement.parentElement?.querySelector('label > span.required');
    if (!spanElement || (typeof optionalZipCountries == 'undefined')) {
        return; // nothing to do (for example in system config)
    }
    if (optionalZipCountries.indexOf(country) != -1) {
        Validation.reset(zipElement);
        while (zipElement.classList.contains('required-entry')) {
            zipElement.classList.remove('required-entry');
        }
        spanElement.style.display = 'none';
    } else {
        zipElement.classList.add('required-entry');
        spanElement.style.display = '';
    }
}

varienGlobalEvents?.attachEventHandler("address_country_changed", onAddressCountryChanged);

// Classes are now global by default with lowercase naming matching original prototypejs
