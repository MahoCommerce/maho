/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class varienGrid {
    constructor() {
        this.initialize(...arguments);
    }

    initialize(containerId, url, pageVar, sortVar, dirVar, filterVar) {
        this.containerId = containerId;
        this.url = url;
        this.pageVar = pageVar || false;
        this.sortVar = sortVar || false;
        this.dirVar = dirVar || false;
        this.filterVar = filterVar || false;
        this.tableSufix = '_table';
        this.useAjax = false;
        this.rowClickCallback = false;
        this.checkboxCheckCallback = false;
        this.preInitCallback = false;
        this.initCallback = false;
        this.initRowCallback = false;
        this.doFilterCallback = false;

        this.reloadParams = false;

        this.trOnMouseOver  = this.rowMouseOver.bindAsEventListener(this);
        this.trOnMouseOut   = this.rowMouseOut.bindAsEventListener(this);
        this.trOnClick      = this.rowMouseClick.bindAsEventListener(this);
        this.trOnDblClick   = this.rowMouseDblClick.bindAsEventListener(this);
        this.trOnKeyPress   = this.keyPress.bindAsEventListener(this);
        this.thLinkOnClick  = this.doSort.bindAsEventListener(this);

        this.initGrid();
    }

    initGrid() {
        if (typeof this.preInitCallback === 'function') {
            this.preInitCallback(this);
        }

        const table = this.getTable();
        if (table) {
            this.rows = Array.from(table.querySelectorAll('tbody tr'));
            for (const [ index, row ] of Object.entries(this.rows)) {
                // row.classList.add(index % 2 ? 'odd' : 'even');
                row.addEventListener('mouseover', this.trOnMouseOver);
                row.addEventListener('mouseout', this.trOnMouseOut);
                row.addEventListener('mousedown', this.trOnClick);
                row.addEventListener('click', this.trOnClick);
                row.addEventListener('dblclick', this.trOnDblClick);
            }
            if (this.sortVar && this.dirVar) {
                const columns = Array.from(table.querySelectorAll('thead a'));
                for (const column of columns) {
                    column.addEventListener('click', this.thLinkOnClick);
                }
            }
        }

        this.bindFilterFields();
        this.bindFieldsChange();

        if (typeof this.initCallback === 'function') {
            try {
                this.initCallback(this);
            } catch (error) {
                console.error(error);
            }
        }
    }

    initGridAjax() {
        this.initGrid();
        this.initGridRows();
    }

    initGridRows() {
        if (typeof this.initRowCallback === 'function') {
            for (const row of this.rows) {
                try {
                    this.initRowCallback(this, row);
                } catch (error) {
                    console.error(error);
                }
            }
        }
    }

    getContainerId() {
        return this.containerId;
    }

    getContainer(suffix = '') {
        return document.getElementById(this.containerId + suffix);
    }

    getTable() {
        return this.getContainer(this.tableSufix);
    }

    rowMouseOver(event) {
        const element = event.target.closest('tr');
        if (element.title) {
            element.classList.add('on-mouse');
        }
    }

    rowMouseOut(event) {
        const element = event.target.closest('tr');
        element.classList.remove('on-mouse');
    }

    rowMouseClick(event) {
        if (event.button !== 1 && event.type === 'mousedown') {
            return; // Ignore mousedown for any button except middle
        }
        if (event.button === 2) {
            return; // Ignore right click
        }
        if (typeof this.rowClickCallback === 'function') {
            try {
                this.rowClickCallback(this, event);
            } catch (error) {
                console.error(error);
            }
        }
        varienGlobalEvents.fireEvent('gridRowClick', event);
    }

    rowMouseDblClick(event) {
        varienGlobalEvents.fireEvent('gridRowDblClick', event);
    }

    keyPress(event) {

    }

    doSort(event) {
        const element = event.target.closest('a');
        if (element.name && element.title) {
            this.addVarToUrl(this.sortVar, element.name);
            this.addVarToUrl(this.dirVar, element.title);
            this.reload(this.url);
        }
        event.preventDefault();
        return false;
    }

    loadByElement(element) {
        if (element && element.name) {
            this.reload(this.addVarToUrl(element.name, element.value));
        }
    }

    async reload(url) {
        url ??= this.url;

        if (!this.useAjax) {
            if (this.reloadParams) {
                url = this._addVarsToUrl(url, this.reloadParams);
            }
            setLocation(url);
            return;
        }

        try {
            const container = this.getContainer();
            const result = await mahoFetch(setQueryParams(url, { hideGridContainer: true }), {
                loaderArea: container,
                method: 'POST',
                body: new URLSearchParams(this.reloadParams || {}),
            });

            if (typeof result === 'string') {
                updateElementHtmlAndExecuteScripts(container, result);
                this.initGridAjax();
            }
        } catch (error) {
            console.error(error);
            alert(error.message);
        }
    }

    _addVarToUrl(url, varName, varValue) {
        return setRouteParams(url, { [varName]: varValue });
    }

    addVarToUrl(varName, varValue) {
        return this.url = this._addVarToUrl(this.url, varName, varValue);
    }

    _addVarsToUrl(url, vars) {
        return setRouteParams(url, vars);
    }

    addVarsToUrl(vars) {
        return this.url = this._addVarsToUrl(this.url, vars);
    }

    doExport() {
        const exportSelect = this.getContainer('_export');
        if (!exportSelect) {
            return;
        }
        let exportUrl = exportSelect.value;
        if (this.massaction && this.massaction.checkedString) {
            exportUrl = this._addVarToUrl(exportUrl, this.massaction.formFieldNameInternal, this.massaction.checkedString);
        }
        setLocation(exportUrl);
    }

    bindFilterFields() {
        this.getContainer()?.querySelectorAll('.filter input, .filter select').forEach((el) => {
            el.addEventListener('keypress', this.filterKeyPress.bind(this));
        });
    }

    bindFieldsChange() {
        this.getTable()?.querySelectorAll('tbody input, tbody select').forEach((el) => {
            el.addEventListener('change', el.setHasChanges.bind(el));
        });
    }

    filterKeyPress(event) {
        if (event.key === 'Enter') {
            this.doFilter();
        }
    }

    doFilter() {
        if (typeof this.doFilterCallback === 'function' && !this.doFilterCallback()) {
            return;
        }

        const filters = new URLSearchParams();
        this.getContainer()?.querySelectorAll('.filter input, .filter select').forEach((el) => {
            if (el.name && el.value && el.value.length) {
                filters.append(el.name, el.value);
            }
        });

        this.addVarsToUrl({
            [this.pageVar]: 1,
            [this.filterVar]: btoa(filters.toString()),
        });

        this.reload();
    }

    resetFilter() {
        this.addVarsToUrl({
            [this.pageVar]: 1,
            [this.filterVar]: btoa('__empty__'),
        });

        this.reload();
    }

    checkCheckboxes(element) {
        this.getContainer()?.querySelectorAll(`input[name="${element.name}"]`).forEach((el) => {
            this.setCheckboxChecked(el, el.checked);
        });
    }

    setCheckboxChecked(element, checked) {
        element.checked = checked;
        element.setHasChanges({});
        if (typeof this.checkboxCheckCallback === 'function') {
            this.checkboxCheckCallback(this, element, checked);
        }
    }

    inputPage(event, maxNum) {
        if (event.key === 'Enter') {
            this.setPage(event.target.value);
        }
    }

    setPage(pageNumber) {
        this.addVarToUrl(this.pageVar, pageNumber);
        this.reload();
    }
};

function shouldOpenGridRowNewTab(event) {
    return event.ctrlKey // Windows ctrl + click
        || event.metaKey // macOS command + click
        || event.button === 1 // Middle mouse click
}

function openGridRow(grid, event) {
    const trElement = event.target.closest('tr');
    if (['A', 'INPUT', 'SELECT', 'OPTION'].includes(event.target.tagName)) {
        return;
    }
    if (trElement.title) {
        if (shouldOpenGridRowNewTab(event)) {
            window.open(trElement.title, '_blank');
        } else {
            setLocation(trElement.title);
        }
    }
}

class varienGridMassaction {

    /* Predefined vars */
    checkedValues = new Map();
    checkedString = '';
    oldCallbacks = {};
    errorText = '';
    items = {};
    gridIds = [];
    useSelectAll = false;
    currentItem = false;
    lastChecked = { left: false, top: false, checkbox: false };
    fieldTemplate = new Template('<input type="hidden" name="#{name}" value="#{value}" />');

    constructor() {
        this.initialize(...arguments);
    }

    initialize(containerId, grid, checkedValues, formFieldNameInternal, formFieldName) {
        this.setOldCallback('row_click', grid.rowClickCallback);
        this.setOldCallback('init',      grid.initCallback);
        this.setOldCallback('init_row',  grid.initRowCallback);
        this.setOldCallback('pre_init',  grid.preInitCallback);

        this.useAjax         = false;
        this.grid            = grid;
        this.grid.massaction = this;
        this.containerId     = containerId;
        this.initMassactionElements();

        this.checkedString          = checkedValues;
        this.formFieldName          = formFieldName;
        this.formFieldNameInternal  = formFieldNameInternal;

        this.grid.initCallback      = this.onGridInit.bind(this);
        this.grid.preInitCallback   = this.onGridPreInit.bind(this);
        this.grid.initRowCallback   = this.onGridRowInit.bind(this);
        this.grid.rowClickCallback  = this.onGridRowClick.bind(this);
        this.initCheckboxes();
        this.checkCheckboxes();
    }

    getContainerId() {
        return this.containerId;
    }

    getContainer(suffix = '') {
        return document.getElementById(this.containerId + suffix);
    }

    setUseAjax(flag) {
        this.useAjax = flag;
    }

    setUseSelectAll(flag) {
        this.useSelectAll = flag;
    }

    initMassactionElements() {
        this.container      = this.getContainer();
        this.count          = this.getContainer('-count');
        this.formHiddens    = this.getContainer('-form-hiddens');
        this.formAdditional = this.getContainer('-form-additional');
        this.select         = this.getContainer('-select');
        this.form           = this.prepareForm();
        this.validator      = new Validation(this.form);
        this.select.addEventListener('change', this.onSelectChange.bind(this));
        this.lastChecked    = { left: false, top: false, checkbox: false };
        this.initMassSelect();
    }

    prepareForm() {
        let form = this.getContainer('-form');
        let formPlace = null;
        let formElement = this.formHiddens || this.formAdditional;

        if (!formElement) {
            formElement = this.container.getElementsByTagName('button')[0];
        }
        if (!form && formElement) {
            /* fix problem with rendering form in FF through innerHTML property */
            form = document.createElement('form');
            form.setAttribute('method', 'post');
            form.setAttribute('action', '');
            form.id = this.containerId + '-form';
            formPlace = formElement.parentNode.parentNode;
            formPlace.parentNode.appendChild(form);
            form.appendChild(formPlace);
        }

        return form;
    }

    setGridIds(gridIds) {
        this.gridIds = gridIds;
        this.updateCount();
    }

    getGridIds() {
        return this.gridIds;
    }

    setItems(items) {
        this.items = items;
        this.updateCount();
    }

    getItems() {
        return this.items;
    }

    getItem(itemId) {
        if (this.items[itemId]) {
            return this.items[itemId];
        }
        return false;
    }

    getOldCallback(callbackName) {
        return this.oldCallbacks[callbackName] || function(){};
    }

    setOldCallback(callbackName, callback) {
        this.oldCallbacks[callbackName] = callback;
    }

    onGridPreInit(grid) {
        this.initMassactionElements();
        this.getOldCallback('pre_init')(grid);
    }

    onGridInit(grid) {
        this.initCheckboxes();
        this.checkCheckboxes();
        this.updateCount();
        this.getOldCallback('init')(grid);
    }

    onGridRowInit(grid, row) {
        this.getOldCallback('init_row')(grid, row);
    }

    onGridRowClick(grid, event) {
        const tdElement = event.target.closest('td');
        const trElement = event.target.closest('tr');

        if (event.target.isMassactionCheckbox) {
            this.setCheckbox(event.target);
            return;
        }

        if (tdElement.querySelector('a, select')) {
            return;
        }

        if (tdElement.querySelector('input') === null && trElement.title) {
            if (shouldOpenGridRowNewTab(event)) {
                window.open(trElement.title, '_blank');
            } else {
                setLocation(trElement.title);
            }
            return;
        }

        const checkbox = this.findCheckbox(event);
        checkbox.checked = !checkbox.checked;
        this.setCheckbox(checkbox);
    }

    onSelectChange(event) {
        const item = this.getSelectedItem();
        if (item) {
            this.formAdditional.innerHTML = this.getContainer(`-item-${item.id}-block`).innerHTML;
        } else {
            this.formAdditional.innerHTML = '';
        }

        this.validator.reset();
    }

    findCheckbox(event) {
        if (['A', 'INPUT', 'SELECT'].includes(event.target.tagName)) {
            return false;
        }

        const checkboxes = Array.from(event.target.closest('tr').querySelectorAll('input.massaction-checkbox'));
        for (const checkbox of checkboxes) {
            if (checkbox.isMassactionCheckbox) {
                return checkbox;
            }
        };
        return false;
    }

    initCheckboxes() {
        for (const checkbox of this.getCheckboxes()) {
            checkbox.isMassactionCheckbox = true;
        }
    }

    checkCheckboxes() {
        for (const checkbox of this.getCheckboxes()) {
            checkbox.checked = varienStringArray.has(checkbox.value, this.checkedString);
        };
    }

    selectAll() {
        this.setCheckedValues(this.useSelectAll ? this.getGridIds() : this.getCheckboxesValuesAsString());
        this.checkCheckboxes();
        this.updateCount();
        this.clearLastChecked();
        return false;
    }

    unselectAll() {
        this.setCheckedValues('');
        this.checkCheckboxes();
        this.updateCount();
        this.clearLastChecked();
        return false;
    }

    selectVisible() {
        this.setCheckedValues(this.getCheckboxesValuesAsString());
        this.checkCheckboxes();
        this.updateCount();
        this.clearLastChecked();
        return false;
    }

    unselectVisible() {
        for (const key of this.getCheckboxesValues()) {
            this.checkedString = varienStringArray.remove(key, this.checkedString);
        };
        this.checkCheckboxes();
        this.updateCount();
        this.clearLastChecked();
        return false;
    }

    setCheckedValues(values) {
        this.checkedString = values;
    }

    getCheckedValues() {
        return this.checkedString;
    }

    getCheckboxes() {
        const result = [];
        for (const row of this.grid.rows) {
            result.push(...row.querySelectorAll('input.massaction-checkbox'));
        }
        return result;
    }

    getCheckboxesValues() {
        return this.getCheckboxes().map((checkbox) => checkbox.value);
    }

    getCheckboxesValuesAsString() {
        return this.getCheckboxesValues().join(',');
    }

    setCheckbox(checkbox) {
        if (checkbox.checked) {
            this.checkedString = varienStringArray.add(checkbox.value, this.checkedString);
        } else {
            this.checkedString = varienStringArray.remove(checkbox.value, this.checkedString);
        }
        this.updateCount();
    }

    updateCount() {
        this.count.textContent = varienStringArray.count(this.checkedString);
        if (!this.grid.reloadParams) {
            this.grid.reloadParams = {};
        }
        this.grid.reloadParams[this.formFieldNameInternal] = this.checkedString;
    }

    getSelectedItem() {
        if (this.getItem(this.select.value)) {
            return this.getItem(this.select.value);
        } else {
            return false;
        }
    }

    apply() {
        if (varienStringArray.count(this.checkedString) == 0) {
            alert(this.errorText);
            return;
        }

        const item = this.getSelectedItem();
        if (!item) {
            this.validator.validate();
            return;
        }

        this.currentItem = item;
        if (this.currentItem.confirm && !window.confirm(this.currentItem.confirm)) {
            return;
        }

        const fieldName = item.field ? item.field : this.formFieldName;

        this.formHiddens.innerHTML =
            this.fieldTemplate.evaluate({ name: fieldName, value: this.checkedString }) +
            this.fieldTemplate.evaluate({ name: 'massaction_prepare_key', value: fieldName });

        if (!this.validator.validate()) {
            return;
        }

        if (this.useAjax && item.url) {
            // TODO: mass action callbacks receive PrototypeJS's transport object
            // need to either convert or find way to be backwards compatible
            new Ajax.Request(item.url, {
                'method': 'post',
                'parameters': this.form.serialize(true),
                'onComplete': this.onMassactionComplete.bind(this)
            });
        } else if (item.url) {
            this.form.action = item.url;
            this.form.submit();
        }
    }

    onMassactionComplete(transport) {
        if (this.currentItem.complete) {
            try {
                const listener = this.getListener(this.currentItem.complete) || function(){};
                listener(this.grid, this, transport);
            } catch (error) {
                console.error(error);
            }
        }
    }

    getListener(strValue) {
        return eval(strValue);
    }

    initMassSelect() {
        this.getContainer().querySelectorAll('input.massaction-checkbox').forEach((el) => {
            el.addEventListener('click', this.massSelect.bind(this));
        });
    }

    clearLastChecked() {
        this.lastChecked = {
            left: false,
            top: false,
            checkbox: false,
        };
    }

    massSelect(event) {
        const currentCheckbox = event.target;
        const lastCheckbox = this.lastChecked.checkbox;

        const conditions = [
            event.button === 0,
            event.key === 'Shift',
            this.lastChecked.left !== false,
            this.lastChecked.top !== false,
            lastCheckbox !== currentCheckbox,
        ];

        if (conditions.every(Boolean)) {
            const start = this.getCheckboxOrder(lastCheckbox);
            const finish = this.getCheckboxOrder(currentCheckbox);
            if (start !== false && finish !== false) {
                this.selectCheckboxRange(
                    Math.min(start, finish),
                    Math.max(start, finish),
                    currentCheckbox.checked
                );
            }
        }

        const bbox = currentCheckbox.getBoundingClientRect();
        this.lastChecked = {
            left: bbox.left,
            top: bbox.top,
            checkbox: currentCheckbox, // "boundary" checkbox
        };
    }

    getCheckboxOrder(currentCheckbox) {
        for (const [ key, checkbox ] of Object.entries(this.getCheckboxes())) {
            if (currentCheckbox === checkbox) {
                return key;
            }
        };
        return false
    }

    selectCheckboxRange(start, finish, isChecked) {
        for (const [ key, checkbox ] of Object.entries(this.getCheckboxes())) {
            if (start <= key && key <= finish) {
                checkbox.checked = isChecked;
                this.setCheckbox(checkbox);
            }
        };
    }
};

const varienGridAction = {
    execute(select) {
        if (!select.value) {
            return;
        }

        let config;
        try {
            config = JSON.parse(select.value);
        } catch {
            console.error('Invalid JSON value:', select.value);
        }

        if (config.confirm && !window.confirm(config.confirm)) {
            select.options[0].selected = true;
            return;
        }

        if (config.popup) {
            const win = window.open(config.href, 'action_window', 'width=500,height=600,resizable=1,scrollbars=1');
            win.focus();
            select.options[0].selected = true;
        } else {
            setLocation(config.href);
        }
    },
};

const varienStringArray = {
    remove(str, haystack) {
        haystack = ',' + haystack + ',';
        haystack = haystack.replace(new RegExp(',' + str + ',', 'g'), ',');
        return this.trimComma(haystack);
    },

    add(str, haystack) {
        haystack = ',' + haystack + ',';
        if (haystack.search(new RegExp(',' + str + ',', 'g'), haystack) === -1) {
            haystack += str + ',';
        }
        return this.trimComma(haystack);
    },

    has(str, haystack) {
        haystack = ',' + haystack + ',';
        if (haystack.search(new RegExp(',' + str + ',', 'g'), haystack) === -1) {
            return false;
        }
        return true;
    },

    count(haystack) {
        if (typeof haystack !== 'string') {
            return 0;
        }
        if (match = haystack.match(new RegExp(',', 'g'))) {
            return match.length + 1;
        } else if (haystack.length !== 0) {
            return 1;
        }
        return 0;
    },

    each(haystack, fn) {
        haystack.split(',').forEach(fn);
    },

    trimComma(string) {
        //return haystack.split(',').filter(Boolean).join(',');
        string = string.replace(new RegExp('^(,+)','i'), '');
        string = string.replace(new RegExp('(,+)$','i'), '');
        return string;
    },
};

class serializerController {

    oldCallbacks = {};

    constructor() {
        this.initialize(...arguments);
    }

    initialize(hiddenDataHolder, predefinedData, inputsToManage, grid, reloadParamName) {
        // Grid inputs
        this.tabIndex = 1000;
        this.inputsToManage       = inputsToManage;
        this.multidimensionalMode = inputsToManage.length > 0;

        // Hash with grid data
        this.gridData = this.getGridDataHash(predefinedData);

        // Hidden input data holder
        this.hiddenDataHolder       = document.getElementById(hiddenDataHolder);
        this.hiddenDataHolder.value = this.serializeObject();

        // Set old callbacks
        this.setOldCallback('row_click', this.grid.rowClickCallback);
        this.setOldCallback('init_row', this.grid.initRowCallback);
        this.setOldCallback('checkbox_check', this.grid.checkboxCheckCallback);

        // Grid
        this.reloadParamName = reloadParamName;
        this.grid = grid;
        this.grid.reloadParams = {};
        this.grid.reloadParams[`${this.reloadParamName}[]`] = this.getDataForReloadParam();
        this.grid.rowClickCallback = this.rowClick.bind(this);
        this.grid.initRowCallback = this.rowInit.bind(this);
        this.grid.checkboxCheckCallback = this.registerData.bind(this);

        for (const row of this.grid.rows) {
            this.eachRow(row);
        }
    }

    setOldCallback(callbackName, callback) {
        this.oldCallbacks[callbackName] = callback;
    }

    getOldCallback(callbackName) {
        return this.oldCallbacks[callbackName] || function(){};
    }

    registerData(grid, element, checked) {
        if (this.multidimensionalMode) {
            if (checked) {
                if (element.inputElements) {
                    this.gridData.set(element.value, {});
                    for (const inputElement of element.inputElements) {
                        inputElement.disabled = true;
                        this.gridData.get(element.value)[inputElement.name] = inputElement.value;
                    }
                }
            } else {
                if (element.inputElements) {
                    for (const inputElement of element.inputElements) {
                        inputElement.disabled = true;
                    }
                }
                this.gridData.unset(element.value);
            }
        } else {
            if (checked) {
                this.gridData.set(element.value, element.value);
            } else {
                this.gridData.unset(element.value);
            }
        }

        this.hiddenDataHolder.value = this.serializeObject();
        this.grid.reloadParams = {};
        this.grid.reloadParams[`${this.reloadParamName}[]`] = this.getDataForReloadParam();
        this.getOldCallback('checkbox_check')(grid, element, checked);
    }

    eachRow(row) {
        this.rowInit(this.grid, row);
    }

    rowClick(grid, event) {
        const tdElement = event.target.closest('td');
        if (tdElement) {
            const checkbox = tdElement.querySelector('input');
            if (checkbox && !checkbox.disabled) {
                const checked = event.target.tagName === 'INPUT' ? checkbox.checked : !checkbox.checked;
                this.grid.setCheckboxChecked(checkbox, checked);
            }
        }
        this.getOldCallback('row_click')(grid, event);
    }

    inputChange(event) {
        const element = event.target;
        if (element?.checkboxElement?.checked) {
            this.gridData.get(element.checkboxElement.value)[element.name] = element.value;
            this.hiddenDataHolder.value = this.serializeObject();
        }
    }

    rowInit(grid, row) {
        if (this.multidimensionalMode) {
            const checkbox = row.querySelector('.checkbox');
            const selectors = [];
            for (const name of this.inputsToManage) {
                selectors.push(`input[name="${name}"]`, `select[name="${name}"]`);
            }

            const inputs = Array.from(row.querySelectorAll(selectors.join(',')));
            if (checkbox && inputs.length) {
                checkbox.inputElements = inputs;
                for (const input of inputs) {
                    input.checkboxElement = checkbox;
                    if (this.gridData.get(checkbox.value)?.[input.name]) {
                        input.value = this.gridData.get(checkbox.value)[input.name];
                    }
                    input.disabled = !checkbox.checked;
                    input.tabIndex = this.tabIndex++;
                    input.addEventListener('keyup', this.inputChange.bind(this));
                    input.addEventListener('change', this.inputChange.bind(this));
                }
            }
        }
        this.getOldCallback('init_row')(grid, row);
    }

    getGridDataHash(obj) {
        return new Map(this.multidimensionalMode ? obj : this.convertArrayToObject(obj));
    }

    getDataForReloadParam() {
        return this.multidimensionalMode ? this.gridData.keys() : this.gridData.values();
    }

    serializeObject() {
        if (this.multidimensionalMode) {
            const serialized = new URLSearchParams();
            for (const [ key, value ] of this.gridData) {
                serialized.set(key, btoa(new URLSearchParams(value).toString()));
            };
            return serialized.toString();
        } else {
            return this.gridData.values().join('&');
        }
    }

    convertArrayToObject(arr) {
        return { ...arr };
    }
};
