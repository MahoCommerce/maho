/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class varienGrid {
    constructor(containerId, url, pageVar, sortVar, dirVar, filterVar) {
        this.containerId = containerId;
        this.url = url;
        this.pageVar = pageVar || false;
        this.sortVar = sortVar || false;
        this.dirVar  = dirVar || false;
        this.filterVar  = filterVar || false;
        this.tableSufix = '_table';
        this.useAjax = false;
        this.rowClickCallback = false;
        this.checkboxCheckCallback = false;
        this.preInitCallback = false;
        this.initCallback = false;
        this.initRowCallback = false;
        this.doFilterCallback = false;

        this.reloadParams = false;

        this.trOnMouseOver  = this.rowMouseOver.bind(this);
        this.trOnMouseOut   = this.rowMouseOut.bind(this);
        this.trOnClick      = this.rowMouseClick.bind(this);
        this.trOnDblClick   = this.rowMouseDblClick.bind(this);
        this.trOnKeyPress   = this.keyPress.bind(this);

        this.thLinkOnClick      = this.doSort.bind(this);
        this.initGrid();
    }
    initGrid() {
        if(this.preInitCallback){
            this.preInitCallback(this);
        }
        const tableElement = document.getElementById(this.containerId + this.tableSufix);
        if(tableElement){
            this.rows = tableElement.querySelectorAll('tbody tr');
            for (let row = 0; row < this.rows.length; row++) {
                if(row % 2 === 0){
                    this.rows[row].classList.add('even');
                } else {
                    this.rows[row].classList.add('odd');
                }

                this.rows[row].addEventListener('mouseover', this.trOnMouseOver);
                this.rows[row].addEventListener('mouseout', this.trOnMouseOut);
                this.rows[row].addEventListener('mousedown', this.trOnClick);
                this.rows[row].addEventListener('click', this.trOnClick);
                this.rows[row].addEventListener('dblclick', this.trOnDblClick);
            }
        }
        if(this.sortVar && this.dirVar){
            const columns = document.querySelectorAll('#' + this.containerId + this.tableSufix + ' thead a');

            for(let col = 0; col < columns.length; col++){
                columns[col].addEventListener('click', this.thLinkOnClick);
            }
        }
        this.bindFilterFields();
        this.bindFieldsChange();
        if(this.initCallback){
            try {
                this.initCallback(this);
            }
            catch (e) {
                if(console) {
                    console.log(e);
                }
            }
        }
    }
    initGridAjax() {
        this.initGrid();
        this.initGridRows();
    }

    initGridRows() {
        if(this.initRowCallback){
            for (let row = 0; row < this.rows.length; row++) {
                try {
                    this.initRowCallback(this, this.rows[row]);
                } catch (e) {
                    if(console) {
                        console.log(e);
                    }
                }
            }
        }
    }

    getContainerId() {
        return this.containerId;
    }
    rowMouseOver(event) {
        const element = event.target.closest('tr');

        if (!element || !element.title) return;

        element.classList.add('on-mouse');

        if (!element.classList.contains('pointer')
            && (this.rowClickCallback !== openGridRow || element.title)) {
            if (element.title) {
                element.classList.add('pointer');
            }
        }
    }

    rowMouseOut(event) {
        const element = event.target.closest('tr');
        if (element) {
            element.classList.remove('on-mouse');
        }
    }
    rowMouseClick(event) {
        // Only handle left clicks and middle clicks
        if (event.button === 2) {
            return; // Ignore right click
        }
        // For mousedown events, only handle middle click (button 1)
        if (event.type === "mousedown" && event.button !== 1) {
            return;
        }
        if(this.rowClickCallback){
            try{
                this.rowClickCallback(this, event);
            }
            catch(e){}
        }
        if (typeof varienGlobalEvents !== 'undefined' && varienGlobalEvents.fireEvent) {
            varienGlobalEvents.fireEvent('gridRowClick', event);
        }
    }

    rowMouseDblClick(event) {
        if (typeof varienGlobalEvents !== 'undefined' && varienGlobalEvents.fireEvent) {
            varienGlobalEvents.fireEvent('gridRowDblClick', event);
        }
    }

    keyPress(event) {
        // Empty implementation
    }
    doSort(event) {
        const element = event.target.closest('a');

        if(element && element.name && element.title){
            this.addVarToUrl(this.sortVar, element.name);
            this.addVarToUrl(this.dirVar, element.title);
            this.reload(this.url);
        }
        event.preventDefault();
        event.stopPropagation();
        return false;
    }

    loadByElement(element) {
        if(element && element.name){
            this.reload(this.addVarToUrl(element.name, element.value));
        }
    }
    reload(url) {
        if (!this.reloadParams) {
            this.reloadParams = {form_key: FORM_KEY};
        }
        else {
            this.reloadParams.form_key = FORM_KEY;
        }
        url = url || this.url;
        if(this.useAjax && this.useAjax !== '0' && this.useAjax !== 'false'){
            const ajaxUrl = url + (url.includes('?') ? '&ajax=true' : '?ajax=true');
            const formData = new FormData();

            Object.entries(this.reloadParams || {}).forEach(([key, value]) => {
                formData.append(key, value);
            });

            mahoFetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(responseText => {
                try {
                    responseText = responseText.replace(/>\s+</g, '><');
                    const response = JSON.parse(responseText);
                    if (response.error) {
                        alert(response.message);
                    }
                    if(response.ajaxExpired && response.ajaxRedirect) {
                        setLocation(response.ajaxRedirect);
                        return;
                    }
                } catch (e) {
                    const containerElement = document.getElementById(this.containerId);
                    if (containerElement) {
                        containerElement.innerHTML = responseText;
                    }
                }
                this.initGridAjax();
            })
            .catch(error => {
                this._processFailure();
            });
            return;
        }
        else{
            if(this.reloadParams){
                Object.entries(this.reloadParams).forEach(([key, value]) => {
                    url = this.addVarToUrl(key, value);
                });
            }
            location.href = url;
        }
    }
    _processFailure() {
        location.href = BASE_URL;
    }

    _addVarToUrl(url, varName, varValue) {
        const re = new RegExp('\\/' + varName + '\\/.*?\\/');
        const parts = url.split(/\?/);
        url = parts[0].replace(re, '/');
        url += encodeURIComponent(varName) + '/' + encodeURIComponent(varValue) + '/';
        if(parts.length > 1) {
            url += '?' + parts[1];
        }
        return url;
    }

    addVarToUrl(varName, varValue) {
        this.url = this._addVarToUrl(this.url, varName, varValue);
        return this.url;
    }

    doExport() {
        const exportElement = document.getElementById(this.containerId + '_export');
        if(exportElement){
            let exportUrl = exportElement.value;
            if(this.massaction && this.massaction.checkedString) {
                exportUrl = this._addVarToUrl(exportUrl, this.massaction.formFieldNameInternal, this.massaction.checkedString);
            }
            location.href = xssFilter(exportUrl);
        }
    }
    bindFilterFields() {
        // Use event delegation on document body to catch all filter keypresses
        // This survives AJAX reloads since body doesn't get replaced
        // Track per-grid to support multiple grids on the same page
        document.body._gridFilterDelegated = document.body._gridFilterDelegated || {};
        if (!document.body._gridFilterDelegated[this.containerId]) {
            document.body.addEventListener('keypress', (event) => {
                if (event.target.matches(`#${this.containerId} .filter input, #${this.containerId} .filter select`)) {
                    this.filterKeyPress(event);
                }
            });
            document.body._gridFilterDelegated[this.containerId] = true;
        }
    }

    bindFieldsChange() {
        const containerElement = document.getElementById(this.containerId);
        if (!containerElement) {
            return;
        }
        const tableElement = document.getElementById(this.containerId + this.tableSufix);
        if (tableElement) {
            const tbody = tableElement.querySelector('tbody');
            if (tbody) {
                const dataElements = tbody.querySelectorAll('input, select');
                for(let i = 0; i < dataElements.length; i++){
                    dataElements[i].addEventListener('change', function() {
                        if (this.setHasChanges) {
                            this.setHasChanges();
                        }
                    });
                }
            }
        }
    }

    filterKeyPress(event) {
        if(event.keyCode === 13){ // Enter key
            this.doFilter();
        }
    }

    doFilter() {
        const filters = document.querySelectorAll('#' + this.containerId + ' .filter input, #' + this.containerId + ' .filter select');
        const elements = [];
        for(let i = 0; i < filters.length; i++){
            if(filters[i].value && filters[i].value.length) {
                elements.push(filters[i]);
            }
        }
        if (!this.doFilterCallback || (this.doFilterCallback && this.doFilterCallback())) {
            this.addVarToUrl(this.pageVar, 1);
            // Serialize elements manually since we don't have prototypejs Form.serializeElements
            const formData = new FormData();
            elements.forEach(element => {
                formData.append(element.name, element.value);
            });
            const serialized = new URLSearchParams(formData).toString();
            this.reload(this.addVarToUrl(this.filterVar, btoa(serialized)));
        }
    }

    resetFilter() {
        this.addVarToUrl(this.pageVar, 1);
        this.reload(this.addVarToUrl(this.filterVar, ''));
    }
    checkCheckboxes(element) {
        const containerElement = document.getElementById(this.containerId);
        if (containerElement) {
            const elements = containerElement.querySelectorAll('input[name="' + element.name + '"]');
            for(let i = 0; i < elements.length; i++){
                this.setCheckboxChecked(elements[i], element.checked);
            }
        }
    }

    setCheckboxChecked(element, checked) {
        element.checked = checked;
        if (element.setHasChanges) {
            element.setHasChanges({});
        }
        if(this.checkboxCheckCallback){
            this.checkboxCheckCallback(this, element, checked);
        }
    }

    inputPage(event, maxNum) {
        const element = event.target;
        const keyCode = event.keyCode || event.which;
        if(keyCode === 13){ // Enter key
            this.setPage(element.value);
        }
    }

    setPage(pageNumber) {
        this.reload(this.addVarToUrl(this.pageVar, pageNumber));
    }

    inputPage(event, lastPage) {
        if (event.keyCode === 13) { // Enter key
            const pageNumber = parseInt(event.target.value);
            if (pageNumber > 0 && pageNumber <= lastPage) {
                this.setPage(pageNumber);
            }
            event.preventDefault();
            return false;
        }
    }
}

function shouldOpenGridRowNewTab(evt){
    return evt.ctrlKey // Windows ctrl + click
        || evt.metaKey // macOS command + click
        || evt.button == 1 // Middle mouse click
}

function openGridRow(grid, evt){
    const trElement = evt.target.closest('tr');
    if(['a', 'input', 'select', 'option'].indexOf(evt.target.tagName.toLowerCase()) !== -1) {
        return;
    }
    if(trElement && trElement.title){
        // Prevent navigation for # URLs to avoid page jumping
        if(trElement.title === '#') {
            evt.preventDefault();
            return;
        }
        if (shouldOpenGridRowNewTab(evt)) {
            window.open(trElement.title, '_blank');
        } else {
            setLocation(trElement.title);
        }
    }
}

class varienGridMassaction {
    constructor(containerId, grid, checkedValues, formFieldNameInternal, formFieldName) {
        /* Predefined vars */
        this.checkedValues = new Map();
        this.checkedString = '';
        this.oldCallbacks = {};
        this.errorText = '';
        this.items = {};
        this.gridIds = [];
        this.useSelectAll = false;
        this.currentItem = false;
        this.lastChecked = { left: false, top: false, checkbox: false };
        this.fieldTemplate = (data) => `<input type="hidden" name="${data.name}" value="${data.value}" />`;

        // Initialize
        this.setOldCallback('row_click', grid.rowClickCallback);
        this.setOldCallback('init',      grid.initCallback);
        this.setOldCallback('init_row',  grid.initRowCallback);
        this.setOldCallback('pre_init',  grid.preInitCallback);

        this.useAjax        = false;
        this.grid           = grid;
        this.grid.massaction = this;
        this.containerId    = containerId;
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
    setUseAjax(flag) {
        this.useAjax = flag;
    }

    setUseSelectAll(flag) {
        this.useSelectAll = flag;
    }
    initMassactionElements() {
        this.container      = document.getElementById(this.containerId);
        this.count          = document.getElementById(this.containerId + '-count');
        this.formHiddens    = document.getElementById(this.containerId + '-form-hiddens');
        this.formAdditional = document.getElementById(this.containerId + '-form-additional');
        this.select         = document.getElementById(this.containerId + '-select');
        this.form           = this.prepareForm();
        this.validator      = new Validation(this.form);
        this.select.addEventListener('change', this.onSelectChange.bind(this));
        this.lastChecked    = { left: false, top: false, checkbox: false };
        this.initMassSelect();
    }
    prepareForm() {
        var form = document.getElementById(this.containerId + '-form'), formPlace = null,
            formElement = this.formHiddens || this.formAdditional;

        if (!formElement) {
            formElement = this.container.getElementsByTagName('button')[0];
            formElement && formElement.parentNode;
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
        if(this.items[itemId]) {
            return this.items[itemId];
        }
        return false;
    }
    getOldCallback(callbackName) {
        return this.oldCallbacks[callbackName] ? this.oldCallbacks[callbackName] : function() {};
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
    onGridRowClick(grid, evt) {

        var tdElement = evt.target.closest('td');
        var trElement = evt.target.closest('tr');

        if(!tdElement.querySelector('input')) {
            if(tdElement.querySelector('a') || tdElement.querySelector('select')) {
                return;
            }
            if (trElement.title) {
                if (shouldOpenGridRowNewTab(evt)) {
                    window.open(trElement.title, '_blank');
                } else {
                    setLocation(trElement.title);
                }
            }
            else{
                var checkbox = trElement.querySelectorAll('input');
                var isInput  = evt.target.tagName.toLowerCase() == 'input';
                var checked = isInput ? checkbox[0].checked : !checkbox[0].checked;

                if(checked) {
                    this.checkedString = varienStringArray.add(checkbox[0].value, this.checkedString);
                } else {
                    this.checkedString = varienStringArray.remove(checkbox[0].value, this.checkedString);
                }
                this.grid.setCheckboxChecked(checkbox[0], checked);
                this.updateCount();
            }
            return;
        }

        if(evt.target.isMassactionCheckbox) {
           this.setCheckbox(evt.target);
        } else {
           var checkbox = this.findCheckbox(evt);
           if (checkbox) {
               checkbox.checked = !checkbox.checked;
               this.setCheckbox(checkbox);
           }
        }
    }
    onSelectChange(evt) {
        var item = this.getSelectedItem();
        if(item) {
            this.formAdditional.innerHTML = document.getElementById(this.containerId + '-item-' + item.id + '-block').innerHTML;
        } else {
            this.formAdditional.innerHTML = '';
        }

        this.validator.reset();
    }
    findCheckbox(evt) {
        if(['a', 'input', 'select'].indexOf(evt.target.tagName.toLowerCase())!==-1) {
            return false;
        }
        var checkbox = false;
        const trElement = evt.target.closest('tr');
        const checkboxes = trElement.querySelectorAll('.massaction-checkbox');
        for (const element of checkboxes) {
            if(element.isMassactionCheckbox) {
                checkbox = element;
                break;
            }
        }
        return checkbox;
    }
    initCheckboxes() {
        this.getCheckboxes().forEach(function(checkbox) {
           checkbox.isMassactionCheckbox = true;
        }.bind(this));
    }
    checkCheckboxes() {
        this.getCheckboxes().forEach(function(checkbox) {
            checkbox.checked = varienStringArray.has(checkbox.value, this.checkedString);
        }.bind(this));
    }
    selectAll() {
        this.setCheckedValues((this.useSelectAll ? this.getGridIds() : this.getCheckboxesValuesAsString()));
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
        this.getCheckboxesValues().forEach(function(key){
            this.checkedString = varienStringArray.remove(key, this.checkedString);
        }.bind(this));
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
        var result = [];
        this.grid.rows.forEach(function(row){
            var checkboxes = row.querySelectorAll('.massaction-checkbox');
            checkboxes.forEach(function(checkbox){
                result.push(checkbox);
            });
        });
        return result;
    }
    getCheckboxesValues() {
        var result = [];
        this.getCheckboxes().forEach(function(checkbox) {
            result.push(checkbox.value);
        }.bind(this));
        return result;
    }
    getCheckboxesValuesAsString() {
        return this.getCheckboxesValues().join(',');
    }
    setCheckbox(checkbox) {
        if(checkbox.checked) {
            this.checkedString = varienStringArray.add(checkbox.value, this.checkedString);
        } else {
            this.checkedString = varienStringArray.remove(checkbox.value, this.checkedString);
        }
        this.updateCount();
    }
    updateCount() {
        this.count.innerHTML = varienStringArray.count(this.checkedString);
        if(!this.grid.reloadParams) {
            this.grid.reloadParams = {};
        }
        this.grid.reloadParams[this.formFieldNameInternal] = this.checkedString;
    }
    getSelectedItem() {
        if(this.getItem(this.select.value)) {
            return this.getItem(this.select.value);
        } else {
            return false;
        }
    }
    apply() {
        if(varienStringArray.count(this.checkedString) == 0) {
                alert(this.errorText);
                return;
            }

        var item = this.getSelectedItem();
        if(!item) {
            this.validator.validate();
            return;
        }
        this.currentItem = item;
        var fieldName = (item.field ? item.field : this.formFieldName);
        var fieldsHtml = '';

        if(this.currentItem.confirm && !window.confirm(this.currentItem.confirm)) {
            return;
        }

        this.formHiddens.innerHTML = '';
        this.formHiddens.insertAdjacentHTML('beforeend', this.fieldTemplate({name: fieldName, value: this.checkedString}));
        this.formHiddens.insertAdjacentHTML('beforeend', this.fieldTemplate({name: 'massaction_prepare_key', value: fieldName}));

        if(!this.validator.validate()) {
            return;
        }

        if(this.useAjax && item.url) {
            const formData = new FormData(this.form);

            mahoFetch(item.url, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                this.onMassactionComplete({responseText: typeof response === 'object' ? JSON.stringify(response) : response});
            })
            .catch(error => {
                console.error('Mass action error:', error);
            });
        } else if(item.url) {
            this.form.action = item.url;
            this.form.submit();
        }
    }
    onMassactionComplete(transport) {
        if(this.currentItem.complete) {
            try {
                var listener = this.getListener(this.currentItem.complete) || function() {};
                listener(this.grid, this, transport);
            } catch (e) {}
       }
    }
    getListener(strValue) {
        return eval(strValue);
    }
    initMassSelect() {
        document.querySelectorAll('input[class~="massaction-checkbox"]').forEach(
            (element) => {
                element.addEventListener('click', this.massSelect.bind(this));
            }
        );
    }
    clearLastChecked() {
        this.lastChecked = {
            left: false,
            top: false,
            checkbox: false
        };
    }
    massSelect(evt) {
        if(this.lastChecked.left !== false
            && this.lastChecked.top !== false
            && evt.button === 0
            && evt.shiftKey === true
        ) {
            var currentCheckbox = evt.target;
            var lastCheckbox = this.lastChecked.checkbox;
            if (lastCheckbox != currentCheckbox) {
                var start = this.getCheckboxOrder(lastCheckbox);
                var finish = this.getCheckboxOrder(currentCheckbox);
                if (start !== false && finish !== false) {
                    this.selectCheckboxRange(
                        Math.min(start, finish),
                        Math.max(start, finish),
                        currentCheckbox.checked
                    );
                }
            }
        }

        this.lastChecked = {
            left: evt.target.getBoundingClientRect().left,
            top: evt.target.getBoundingClientRect().top,
            checkbox: evt.target // "boundary" checkbox
        };
    }
    getCheckboxOrder(curCheckbox) {
        var order = false;
        this.getCheckboxes().forEach(function(checkbox, key){
            if (curCheckbox == checkbox) {
                order = key;
            }
        });
        return order;
    }
    selectCheckboxRange(start, finish, isChecked) {
        this.getCheckboxes().forEach((function(checkbox, key){
            if (key >= start && key <= finish) {
                checkbox.checked = isChecked;
                this.setCheckbox(checkbox);
            }
        }).bind(this));
    }
};

var varienGridAction = {
    execute(select) {
        if(!select.value) {
            return;
        }

        let config;
        try {
            config = JSON.parse(select.value);
        } catch (e) {
            return;
        }
        if(config.confirm && !window.confirm(config.confirm)) {
            select.options[0].selected = true;
            return;
        }

        if(config.popup) {
            var win = window.open(config.href, 'action_window', 'width=500,height=600,resizable=1,scrollbars=1');
            win.focus();
            select.options[0].selected = true;
        } else {
            setLocation(config.href);
        }
    }
};

var varienStringArray = {
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
        if (typeof haystack != 'string') {
            return 0;
        }
        var match;
        if (match = haystack.match(new RegExp(',', 'g'))) {
            return match.length + 1;
        } else if (haystack.length != 0) {
            return 1;
        }
        return 0;
    },
    each(haystack, fnc) {
        var haystack = haystack.split(',');
        for (var i=0; i<haystack.length; i++) {
            fnc(haystack[i]);
        }
    },
    trimComma(string) {
        string = string.replace(new RegExp('^(,+)','i'), '');
        string = string.replace(new RegExp('(,+)$','i'), '');
        return string;
    }
};

class serializerController {
    constructor(hiddenDataHolder, predefinedData, inputsToManage, grid, reloadParamName) {
        this.oldCallbacks = {};

        // Grid inputs
        this.tabIndex = 1000;
        this.inputsToManage = inputsToManage;
        this.multidimensionalMode = inputsToManage.length > 0;

        // Map with grid data
        this.gridData = this.getGridDataHash(predefinedData);

        // Hidden input data holder
        this.hiddenDataHolder = document.getElementById(hiddenDataHolder);
        this.hiddenDataHolder.value = this.serializeObject();

        this.grid = grid;

        // Set old callbacks
        this.setOldCallback('row_click', this.grid.rowClickCallback);
        this.setOldCallback('init_row', this.grid.initRowCallback);
        this.setOldCallback('checkbox_check', this.grid.checkboxCheckCallback);

        // Grid
        this.reloadParamName = reloadParamName;
        this.grid.rowClickCallback = this.rowClick.bind(this);
        this.grid.initRowCallback = this.rowInit.bind(this);
        this.grid.checkboxCheckCallback = this.registerData.bind(this);

        if (this.grid.rows) {
            this.grid.rows.forEach(row => this.eachRow(row));
        }
    }

    setOldCallback(callbackName, callback) {
        this.oldCallbacks[callbackName] = callback;
    }

    getOldCallback(callbackName) {
        return this.oldCallbacks[callbackName] || (() => {});
    }

    registerData(grid, element, checked) {
        if (this.multidimensionalMode) {
            if (checked) {
                if (element.inputElements) {
                    this.gridData.set(element.value, {});
                    for (let i = 0; i < element.inputElements.length; i++) {
                        element.inputElements[i].disabled = false;
                        this.gridData.get(element.value)[element.inputElements[i].name] = element.inputElements[i].value;
                    }
                }
            } else {
                if (element.inputElements) {
                    for (let i = 0; i < element.inputElements.length; i++) {
                        element.inputElements[i].disabled = true;
                    }
                }
                this.gridData.delete(element.value);
            }
        } else {
            if (checked) {
                this.gridData.set(element.value, element.value);
            } else {
                this.gridData.delete(element.value);
            }
        }

        this.hiddenDataHolder.value = this.serializeObject();
        this.getOldCallback('checkbox_check')(grid, element, checked);
    }

    eachRow(row) {
        this.rowInit(this.grid, row);
    }

    rowClick(grid, event) {
        const tdElement = event.target.closest('td');
        const trElement = event.target.closest('tr');
        const isInput = event.target.tagName === 'INPUT';

        // Check if this row has checkbox functionality
        if (tdElement && trElement) {
            const checkbox = tdElement.querySelector('input[type="checkbox"]') || trElement.querySelector('input[type="checkbox"]');

            if (checkbox && !checkbox.disabled) {
                // If clicking directly on checkbox, handle the change through registerData
                if (isInput && event.target === checkbox) {
                    // The checkbox state has already changed, just register the new state
                    this.registerData(grid, checkbox, checkbox.checked);
                    event.stopPropagation();
                    return;
                }

                // If clicking elsewhere in the checkbox cell, toggle the checkbox and register
                const checkboxCell = checkbox.closest('td');
                if (tdElement === checkboxCell) {
                    const newChecked = !checkbox.checked;
                    checkbox.checked = newChecked;
                    this.registerData(grid, checkbox, newChecked);
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
            }
        }

        // For non-checkbox interactions, call the original row click handler
        const originalCallback = this.getOldCallback('row_click');
        if (originalCallback && typeof originalCallback === 'function') {
            originalCallback(grid, event);
        }
    }

    inputChange = (event) => {
        const element = event.target;
        if (element && element.checkboxElement && element.checkboxElement.checked) {
            this.gridData.get(element.checkboxElement.value)[element.name] = element.value;
            this.hiddenDataHolder.value = this.serializeObject();
        }
    }

    rowInit(grid, row) {
        if (this.multidimensionalMode) {
            const checkbox = row.querySelector('.checkbox');
            const selectors = this.inputsToManage.map(name =>
                [`input[name="${name}"]`, `select[name="${name}"]`]
            ).flat();

            const inputs = [];
            selectors.forEach(selector => {
                inputs.push(...row.querySelectorAll(selector));
            });

            if (checkbox && inputs.length > 0) {
                checkbox.inputElements = inputs;

                inputs.forEach(input => {
                    input.checkboxElement = checkbox;
                    if (this.gridData.get(checkbox.value) && this.gridData.get(checkbox.value)[input.name]) {
                        input.value = this.gridData.get(checkbox.value)[input.name];
                    }
                    input.disabled = !checkbox.checked;
                    input.tabIndex = this.tabIndex++;
                    input.addEventListener('keyup', this.inputChange);
                    input.addEventListener('change', this.inputChange);
                });
            }
        } else {
            const checkbox = row.querySelector('input[type="checkbox"]');
            if (checkbox && this.gridData.has(checkbox.value)) {
                checkbox.checked = true;
            }
        }
        this.getOldCallback('init_row')(grid, row);
    }

    // Utility methods
    getGridDataHash(object) {
        return new Map(Object.entries(
            this.multidimensionalMode ? object : this.convertArrayToObject(object)
        ));
    }

    getDataForReloadParam() {
        return this.multidimensionalMode ? Array.from(this.gridData.keys()) : Array.from(this.gridData.values());
    }

    serializeObject() {
        if (this.multidimensionalMode) {
            const pairs = [];
            this.gridData.forEach((value, key) => {
                const encodedValue = btoa(this.objectToQueryString(value));
                pairs.push(`${encodeURIComponent(key)}=${encodeURIComponent(encodedValue)}`);
            });
            return pairs.join('&');
        } else {
            return Array.from(this.gridData.values()).join('&');
        }
    }

    convertArrayToObject(array) {
        const object = {};
        for (let i = 0, l = array.length; i < l; i++) {
            object[array[i]] = array[i];
        }
        return object;
    }

    objectToQueryString(obj) {
        const pairs = [];
        for (const [key, value] of Object.entries(obj)) {
            pairs.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
        }
        return pairs.join('&');
    }
}

// Backward compatibility aliases - make them global
window.varienGrid = varienGrid;
window.varienGridMassaction = varienGridMassaction;
window.serializerController = serializerController;
window.openGridRow = openGridRow;
window.shouldOpenGridRowNewTab = shouldOpenGridRowNewTab;
