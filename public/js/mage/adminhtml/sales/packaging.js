/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class Packaging
{
    constructor() {
        this.initialize(...arguments);
    }

    /**
     * Initialize object
     */
    initialize(params) {
        this.packageIncrement = 0;
        this.packages = [];
        this.itemsAll = [];
        this.createLabelUrl = params.createLabelUrl ? params.createLabelUrl : null;
        this.itemsGridUrl = params.itemsGridUrl ? params.itemsGridUrl : null;
        this.errorQtyOverLimit = params.errorQtyOverLimit;
        this.titleDisabledSaveBtn = params.titleDisabledSaveBtn;

        this.paramsCreateLabelRequest = {};
        this.validationErrorMsg = params.validationErrorMsg;

        this.defaultItemsQty            = params.shipmentItemsQty ? params.shipmentItemsQty : null;
        this.defaultItemsPrice          = params.shipmentItemsPrice ? params.shipmentItemsPrice : null;
        this.defaultItemsName           = params.shipmentItemsName ? params.shipmentItemsName : null;
        this.defaultItemsWeight         = params.shipmentItemsWeight ? params.shipmentItemsWeight : null;
        this.defaultItemsProductId      = params.shipmentItemsProductId ? params.shipmentItemsProductId : null;
        this.defaultItemsOrderItemId    = params.shipmentItemsOrderItemId ? params.shipmentItemsOrderItemId : null;

        this.shippingInformation = params.shippingInformation ? params.shippingInformation : null;
        this.thisPage            = params.thisPage ? params.thisPage : null;
        this.customizableContainers = params.customizable ? params.customizable : [];
        this.girthEnabled        = params.girthEnabled;

        this.eps = .000001;
    }

    bindWindowEventListeners() {
        this.window.querySelector('.AddPackageBtn').addEventListener('click', this.newPackage.bind(this));
    }

    bindPackageBlockEventListeners(packageBlock) {
        packageBlock.querySelector('.AddItemsBtn').addEventListener('click', (event) => {
            this.getItemsForPack(event.target);
        });
        packageBlock.querySelector('.DeletePackageBtn').addEventListener('click', (event) => {
            this.deletePackage(event.target);
        });
        packageBlock.querySelector('.AddSelectedBtn').addEventListener('click', (event) => {
            this.packItems(event.target);
        });
        packageBlock.querySelector('select[name=package_container]')?.addEventListener('change', (event) => {
            this.changeContainerType(event.target);
            this.checkSizeAndGirthParameter(event.target, this.girthEnabled);
        });
        packageBlock.querySelector('select[name=package_size]')?.addEventListener('change', (event) => {
            this.checkSizeAndGirthParameter(event.target, this.girthEnabled);
        });
        for (const selectEl of packageBlock.querySelectorAll('.measures')) {
            selectEl.addEventListener('change', (event) => {
                this.changeMeasures(event.target);
            });
        }
        packageBlock.querySelector('select[name=content_type]')?.addEventListener('change', (event) => {
            this.changeContentTypes(event.target, this.girthEnabled);
        });
    }

    setLabelCreatedCallback(callback) {
        this.labelCreatedCallback = callback;
    }
    setCancelCallback(callback) {
        this.cancelCallback = callback;
    }
    setConfirmPackagingCallback(callback) {
        this.confirmPackagingCallback = callback;
    }
    setItemQtyCallback(callback) {
        this.itemQtyCallback = callback;
    }
    setCreateLabelUrl(url) {
        this.createLabelUrl = url;
    }
    setParamsCreateLabelRequest(params) {
        Object.assign(this.paramsCreateLabelRequest, params);
    }

    showWindow() {
        const template = document.getElementById('packaging_window_template');
        if (!template) {
            return;
        }

        this.window = Dialog.confirm(template.innerHTML, {
            title: Translator.translate('Create Packages'),
            className: 'packaging-window',
            onOk: this.confirmPackaging.bind(this),
            onCancel: this.cancelPackaging.bind(this),
            okLabel: Translator.translate('Submit Shipment'),
        });

        this.packagesContent = this.window.querySelector('#packages_content');
        this.newPackage();
        this.bindWindowEventListeners();
    }

    updateMessage(message) {
        const block = this.window.querySelector('.messages');
        block.innerHTML = message;
        toggleVis(block, true);
    }

    clearMessage() {
        const block = this.window.querySelector('.messages');
        block.textContent = '';
        toggleVis(block, false);
    }

    cancelPackaging() {
        if (typeof this.cancelCallback === 'function') {
            this.cancelCallback();
        }
    }

    confirmPackaging(params) {
        if (!this.validate()) {
            this.updateMessage(this.validationErrorMsg);
            return false;
        }
        if (typeof this.confirmPackagingCallback === 'function') {
            return this.confirmPackagingCallback();
        }
        return true;
    }

    checkAllItems(headCheckbox) {
        for (const checkbox of headCheckbox.closest('table').querySelectorAll('tbody input[type=checkbox]')) {
            checkbox.checked = headCheckbox.checked;
            this._observeQty.call(checkbox);
        }
    }

    cleanPackages() {
        this.packagesContent.textContent = '';
        this.packages = [];
        this.itemsAll = [];
        this.packageIncrement = 0;
        this._setAllItemsPackedState();
        this.clearMessage();
    }

    async sendCreateLabelRequest() {
        this.clearMessage();
        if (!this.createLabelUrl) {
            this.updateMessage(Translator.translate('Invalid URL'));
            return false;
        }

        const packagesParams = [];

        for (const packageBlock of this.packagesContent.children) {
            const packageId = packageBlock.dataset.id;
            const weight = parseFloat(packageBlock.querySelector('input[name=container_weight]').value);
            const length = parseFloat(packageBlock.querySelector('input[name=container_length]').value);
            const width  = parseFloat(packageBlock.querySelector('input[name=container_width]').value);
            const height = parseFloat(packageBlock.querySelector('input[name=container_height]').value);

            packagesParams[packageId] = {
                weight_units:    packageBlock.querySelector('select[name=container_weight_units]').value,
                dimension_units: packageBlock.querySelector('select[name=container_dimension_units]').value,
                weight:          isNaN(weight) ? '' : weight,
                length:          isNaN(length) ? '' : length,
                width:           isNaN(width)  ? '' : width,
                height:          isNaN(height) ? '' : height,
            };

            const packageContainer = packageBlock.querySelector('select[name=package_container]');
            if (packageContainer) {
                packagesParams[packageId]['container'] = packageContainer.value;
            }

            const customsValue = packageBlock.querySelector('input[name=package_customs_value]');
            if (parseFloat(customsValue?.value)) {
                packagesParams[packageId]['customs_value'] = parseFloat(customsValue.value);
            } else {
                packagesParams[packageId]['customs_value'] = 0;
            }

            const packageSize = packageBlock.querySelector('select[name=package_size]');
            if (packageSize?.value) {
                packagesParams[packageId]['size'] = packageSize.value;
            }

            const containerGirth = packageBlock.querySelector('select[name=container_girth]');
            const containerGirthDimensionUnits = packageBlock.querySelector('select[name=container_girth_dimension_units]');
            if (containerGirth?.value) {
                packagesParams[packageId]['girth'] = containerGirth.value;
                packagesParams[packageId]['girth_dimension_units'] = containerGirthDimensionUnits?.value;
            }

            const contentType = packageBlock.querySelector('[name=content_type]');
            const contentTypeOther = packageBlock.querySelector('[name=content_type_other]');
            if (contentType && contentTypeOther) {
                packagesParams[packageId]['content_type'] = contentType.value;
                packagesParams[packageId]['content_type_other'] = contentTypeOther.value;
            } else {
                packagesParams[packageId]['content_type'] = '';
                packagesParams[packageId]['content_type_other'] = '';
            }

            const deliveryConfirmation = packageBlock.querySelector('select[name=delivery_confirmation_types]');
            if (deliveryConfirmation) {
                packagesParams[packageId]['delivery_confirmation'] =  deliveryConfirmation.value;
            }
        }

        for (const packageId of Object.keys(this.packages)) {
            if (isNaN(packageId) || !packagesParams[packageId]) {
                continue;
            }
            for (const [ key, val ] of Object.entries(packagesParams[packageId])) {
                this.paramsCreateLabelRequest[`packages[${packageId}][params][${key}]`] = val;
            }

            for (const [ packedItemId, packedItem ] of Object.entries(this.packages[packageId]['items'])) {
                if (isNaN(packedItemId)) {
                    continue;
                }
                for (const [ key, val ] of Object.entries(packedItem)) {
                    this.paramsCreateLabelRequest[`packages[${packageId}][items][${packedItemId}][${key}]`] = val;
                }

                this.paramsCreateLabelRequest[`packages[${packageId}][items][${packedItemId}][price]`] =
                    this.defaultItemsPrice[packedItemId];
                this.paramsCreateLabelRequest[`packages[${packageId}][items][${packedItemId}][name]`] =
                    this.defaultItemsName[packedItemId];
                this.paramsCreateLabelRequest[`packages[${packageId}][items][${packedItemId}][weight]`] =
                    this.defaultItemsWeight[packedItemId];
                this.paramsCreateLabelRequest[`packages[${packageId}][items][${packedItemId}][product_id]`] =
                    this.defaultItemsProductId[packedItemId];
                this.paramsCreateLabelRequest[`packages[${packageId}][items][${packedItemId}][order_item_id]`] =
                    this.defaultItemsOrderItemId[packedItemId];
            }
        }

        try {
            const response = await mahoFetch(this.createLabelUrl, {
                method: 'POST',
                body: new URLSearchParams(this.paramsCreateLabelRequest),
            });

            if (typeof this.labelCreatedCallback === 'function') {
                this.labelCreatedCallback(response);
            }
            return true;
        } catch (error) {
            this.updateMessage(error.message);
            return false;
        } finally {
            // Reset object
            const { code, carrier_title, method_title, price } = this.paramsCreateLabelRequest;
            if (code && carrier_title && method_title && price) {
                this.paramsCreateLabelRequest = { code, carrier_title, method_title, price };
            } else {
                this.paramsCreateLabelRequest = {};
            }
        }
    }

    validate() {
        const dimensionElements = this.window.querySelectorAll(
            'input[name=container_length], input[name=container_width], input[name=container_height]'
        );

        // If at least one dimensional el has a value, all must be filled
        const dimensionRequired = Array.from(dimensionElements).some((el) => !!el.value);
        for (const el of dimensionElements) {
            el.classList.toggle('required-entry', dimensionRequired);
        }

        const valid = Array.from(this.window.querySelectorAll('[id^=package_block] input')).map((el) => this.validateElement(el));
        return valid.every(Boolean);
    }

    validateElement(el) {
        for (const value of el.classList) {
            if (Validation.isVisible(el) && !Validation.get(value).test(el.value, el)) {
                el.classList.add('validation-failed');
                return false;
            }
        }
        el.classList.remove('validation-failed');
        return true;
    }

    validateCustomsValue() {
        const items = [];
        for (const packageBlock of this.packagesContent.children) {
            const itemsPrepare = packageBlock.querySelector('.package_prepare, .package_prepare');
            if (itemsPrepare) {
                items.push(...itemsPrepare.querySelectorAll('.grid tbody tr'));
            }
            const itemsPacked = packageBlock.querySelector('.package_items');;
            if (itemsPacked) {
                items.push(...itemsPacked.select('.grid tbody tr'));
            }
        }

        let isValid = true;
        for (const item of items) {
            const itemCustomsValue = item.querySelector('[name=customs_value]');
            if (!this.validateElement(itemCustomsValue)) {
                isValid = false;
            }
        }

        if (isValid) {
            this.clearMessage();
        } else {
            this.updateMessage(this.validationErrorMsg);
        }
        return isValid;
    }

    sortPackages() {
        const packagesSorted = [];
        this.packageIncrement = 0;
        for (const packageBlock of this.packagesContent.children) {
            const newPackageId = ++this.packageIncrement;
            const oldPackageId = packageBlock.dataset.id;
            packageBlock.dataset.id = newPackageId;
            packageBlock.id = `package_block_${newPackageId}`;
            packageBlock.querySelector('.package-number span').textContent = newPackageId;
            if (this.packages[oldPackageId]) {
                packagesSorted[newPackageId] = this.packages[oldPackageId];
            }
        }
        this.packages = packagesSorted;
    }

    newPackage() {
        const template = document.getElementById('packaging_package_template');
        const packageBlock = document.createElement('div');
        const packageId = ++this.packageIncrement;

        packageBlock.innerHTML = template.innerHTML;
        packageBlock.classList.add('package-block', ...template.classList);
        packageBlock.dataset.id = packageId;
        packageBlock.id = `package_block_${packageId}`;
        packageBlock.querySelector('.package-number span').textContent = packageId;

        this.packagesContent.appendChild(packageBlock);
        this.sortPackages();
        this.bindPackageBlockEventListeners(packageBlock);

        packageBlock.querySelector('select[name=package_container]')?.dispatchEvent(new Event('change'));
    }

    deletePackage(obj) {
        const packageBlock = obj.closest('div[id^=package_block]');
        const packageId = packageBlock.dataset.id;

        delete this.packages[packageId];
        packageBlock.remove();
        this.sortPackages();
        this.clearMessage();
        this._setAllItemsPackedState();
    }

    deleteItem(obj) {
        const item = obj.closest('tr');
        const itemId = item.querySelector('[type=checkbox]').value;

        const packageBlock = obj.closest('[id^=package_block]');
        const packageItems = packageBlock.querySelector('.package_items');
        const packageId = packageBlock.dataset.id;

        if (item.parentElement.rows.length === 1) {
            toggleVis(packageItems, false);
        }

        item.remove();
        delete this.packages[packageId]['items'][itemId];

        this.clearMessage();
        this._recalcContainerWeightAndCustomsValue(packageItems);
        this._setAllItemsPackedState();
    }

    recalcContainerWeightAndCustomsValue(obj) {
        const packageBlock = obj.closest('[id^=package_block]');
        const packageItems = packageBlock.querySelector('.package_items');
        if (packageItems) {
            if (!this.validateCustomsValue()) {
                return;
            }
            this._recalcContainerWeightAndCustomsValue(packageItems);
        }
    }

    async getItemsForPack(obj) {
        if (!this.itemsGridUrl) {
            return;
        }

        const packageBlock = obj.closest('[id^=package_block]');
        const packagePrepare = packageBlock.querySelector('.package_prepare, .package_prepare');
        const productGrid = packagePrepare.querySelector('.grid_prepare');

        try {
            const html = await mahoFetch(this.itemsGridUrl);

            updateElementHtmlAndExecuteScripts(productGrid, html);
            this._processPackagePrepare(productGrid);

            if (productGrid.querySelectorAll('.grid tbody tr').length) {
                setElementDisable(packageBlock.querySelector('.AddItemsBtn'), true);
                toggleVis(packagePrepare, true);
            } else {
                productGrid.textContent = '';
            }

        } catch (error) {
            console.error(error)
            productGrid.textContent = '';
        }
    }

    getPackedItemsQty() {
        const items = [];
        for (const packageId of Object.keys(this.packages)) {
            if (isNaN(packageId) || !this.packages[packageId]) {
                continue;
            }
            for (const packedItemId of Object.keys(this.packages[packageId]['items'])) {
                if (isNaN(packedItemId)) {
                    continue;
                }
                if (items[packedItemId]) {
                    items[packedItemId] += this.packages[packageId]['items'][packedItemId]['qty'];
                } else {
                    items[packedItemId] = this.packages[packageId]['items'][packedItemId]['qty'];
                }
            }
        }
        return items;
    }

    _parseQty(obj) {
        const qty = obj.classList.contains('qty-decimal')
            ? parseFloat(obj.value)
            : parseInt(obj.value);

        if (isNaN(qty) || qty <= 0) {
            return 1;
        }
        return qty;
    }

    _parseAllQty(obj) {
        const packageBlock = obj.closest('[id^=package_block]');
        const packagePrepare = packageBlock.querySelector('.package_prepare, .package_prepare');
        const productGrid = packagePrepare.querySelector('.grid_prepare');

        // Parse qty inputs
        for (const item of productGrid.querySelectorAll('.grid tbody tr')) {
            const qtyInput = item.querySelector('[name=qty]');
            qtyInput.value = this._parseQty(qtyInput);
        }
    }

    packItems(obj) {
        const packageBlock = obj.closest('[id^=package_block]');
        const packageId = packageBlock.dataset.id;
        const packagePrepare = packageBlock.querySelector('.package_prepare, .package_prepare');
        const productGrid = packagePrepare.querySelector('.grid_prepare');

        this.clearMessage();
        this._parseAllQty(obj);

        // Check if qty exceeds the total shipped quantity
        let validateQty = true;
        for (const item of productGrid.querySelectorAll('.grid tbody tr')) {
            const checkbox = item.querySelector('[type=checkbox]');
            const qtyInput = item.querySelector('[name=qty]');
            if (checkbox.checked && this._checkExceedsQty(checkbox.value, qtyInput.value)) {
                qtyInput.classList.add('validation-failed');
                validateQty = false;
            } else {
                qtyInput.classList.remove('validation-failed');
            }
        }

        if (!validateQty) {
            this.updateMessage(this.errorQtyOverLimit);
            return;
        }

        if (!this.validateCustomsValue()) {
            return;
        }

        toggleVis(packagePrepare, false);
        setElementDisable(packageBlock.querySelector('.AddItemsBtn'), false);

        const selectedItems = productGrid.querySelectorAll('.grid tbody tr:has([type=checkbox]:checked)');
        if (selectedItems.length === 0) {
            return;
        }

        // Clone the product grid if not already exist
        let packageItems = packageBlock.querySelector('.package_items');
        if (!packageItems) {
            packageItems = productGrid.cloneNode(true);
            packageItems.classList.replace('grid_prepare', 'package_items');
            packageItems.querySelector('.grid tbody').textContent = '';
            packagePrepare.after(packageItems);
        }

        this.packages[packageId] ??= { items: [], params: {} };

        // Loop through selected items and update or add new row to package_items
        for (const item of selectedItems) {
            const checkbox = item.querySelector('[type=checkbox]');
            const qtyInput = item.querySelector('[name=qty]');
            const itemId = checkbox.value;

            this.packages[packageId]['items'][itemId] ??= { qty: 0 };
            this.packages[packageId]['items'][itemId]['qty'] += +qtyInput.value;

            const existingItem = packageItems.querySelector(`.grid tbody tr:has([type=checkbox][value='${itemId}'])`);
            if (existingItem) {
                qtyInput.value = this.packages[packageId]['items'][itemId]['qty'];
                existingItem.replaceWith(item);
            } else {
                const row = packageItems.querySelector('.grid tbody').appendChild(item);
                row.querySelector('input[name=qty]')?.setAttribute('disabled', '');
            }
        }

        toggleVis(packageItems, true);
        this._recalcContainerWeightAndCustomsValue(packageItems);
        this._setAllItemsPackedState();
    }

    validateItemQty(itemId, qty) {
        return Math.min(this.defaultItemsQty[itemId], qty);
     }

    changeMeasures(obj) {
        const packageBlock = obj.closest('[id^=package_block]');
        for (const selectEl of packageBlock.querySelectorAll('.measures')) {
            selectEl.selectedIndex = obj.selectedIndex;
        }
    }

    checkSizeAndGirthParameter(obj, enabled) {
        if (enabled == 0) {
            return;
        }

        const currentNode = obj.closest('tbody');
        if (!currentNode) {
            return;
        }

        const packageSizeEl = currentNode.querySelector('select[name=package_size]');
        const packageContainerEl = currentNode.querySelector('select[name=package_container]');
        const packageGirthEl = currentNode.querySelector('input[name=container_girth]');
        const packageGirthUnitsEl = currentNode.querySelector('select[name=container_girth_dimension_units]');

        if (!packageSizeEl) {
            return;
        }

        const girthEnabled = packageSizeEl.value === 'LARGE' && ['NONRECTANGULAR', 'VARIABLE'].includes(packageContainerEl.value);
        if (girthEnabled) {
            setElementDisable(packageGirthEl, false);
            setElementDisable(packageGirthUnitsEl, false);
        } else {
            setElementDisable(packageGirthEl, true);
            setElementDisable(packageGirthUnitsEl, true);
            packageGirthEl.value = '';
        }

        const sizeEnabled = ['NONRECTANGULAR', 'RECTANGULAR', 'VARIABLE'].includes(packageContainerEl.value);
        if (sizeEnabled) {
            for (const option of packageSizeEl.options) {
                if (option.value === '') {
                    option.remove();
                }
            }
            setElementDisable(packageSizeEl, false);
        } else {
            packageSizeEl.options.add(new Option('', '', null, true));
            setElementDisable(packageSizeEl, true);
        }
    }

    changeContainerType(obj) {
        if (this.customizableContainers.length === 0) {
            return;
        }

        const currentNode = obj.closest('tbody');
        if (!currentNode) {
            return;
        }

        const disable = Object.values(this.customizableContainers).every((value) => value !== obj.value)
        const inputNames = ['container_length', 'container_width', 'container_height', 'container_dimension_units'];;

        for (const inputEl of currentNode.querySelectorAll(inputNames.map((n) => `[name=${n}]`).join(','))) {
            if (disable) {
                setElementDisable(inputEl, true);
                if (inputEl.nodeName === 'INPUT') {
                    inputEl.value = '';
                }
            } else {
                setElementDisable(inputEl, false);
            }
        }
    }

    changeContentTypes(obj) {
        const packageBlock = obj.closest('[id^=package_block]');
        const contentType = packageBlock.querySelector('[name=content_type]');
        const contentTypeOther = packageBlock.querySelector('[name=content_type_other]');
        if (contentType.value === 'OTHER') {
            setElementDisable(contentTypeOther, false);
            contentTypeOther.focus();
        } else {
            setElementDisable(contentTypeOther, true);
            contentTypeOther.value = '';
        }
    }

    _getItemsCount(items) {
        return items.reduce((acc, cur) => acc + (isNaN(cur) ? 0 : cur), 0);
    }

    /**
     * Show/hide disable/enable buttons in case of all items packed state
     */
    _setAllItemsPackedState() {
        const allPackedConditions = [
            this._getItemsCount(this.itemsAll) > 0,
            this._checkExceedsQtyFinal(this._getItemsCount(this.getPackedItemsQty()), this._getItemsCount(this.itemsAll)),
        ];

        if (allPackedConditions.every(Boolean)) {
            // Remove empty packages
            for (const packageBlock of this.packagesContent.children) {
                if (!packageBlock.querySelector('.package_items .grid tbody tr')) {
                    packageBlock.remove();
                }
            }

            // Resort packages
            this.sortPackages();

            // Disable add items / add package buttons
            setElementDisable(this.window.querySelector('.AddPackageBtn'), true);
            for (const addItemsBtn of this.packagesContent.querySelectorAll('.AddItemsBtn')) {
                setElementDisable(addItemsBtn, true);
            }
        } else {
            // Enable add items / add package buttons
            setElementDisable(this.window.querySelector('.AddPackageBtn'), false);
            for (const addItemsBtn of this.packagesContent.querySelectorAll('.AddItemsBtn')) {
                setElementDisable(addItemsBtn, false);
            }
        }
    }

    _processPackagePrapare(packagePrepare) {
        this._processPackagePrepare(packagePrepare);
    }

    _processPackagePrepare(packagePrepare) {
        const itemsAll = [];
        for (const item of packagePrepare.querySelectorAll('.grid tbody tr')) {
            const qtyEl = item.querySelector('[name=qty]');
            const itemId = item.querySelector('[type=checkbox]').value;

            let qtyValue = typeof this.itemQtyCallback === 'function'
                ? this.itemQtyCallback(itemId)
                : item.querySelector('[name=qty]').value;

            qtyValue = parseFloat(qtyValue === '' ? 0 : qtyValue);
            if (isNaN(qtyValue) || qtyValue < 0) {
                qtyValue = 1;
            }

            qtyValue = this.validateItemQty(itemId, qtyValue);
            qtyEl.value = qtyValue;

            if (qtyValue === 0) {
                item.remove();
                return;
            }

            const packedItems = this.getPackedItemsQty();
            itemsAll[itemId] = qtyValue;

            for (const packedItemId of Object.keys(packedItems)) {
                if (isNaN(packedItemId)) {
                    continue;
                }
                const packedQty = packedItems[packedItemId];
                if (itemId === packedItemId) {
                    if (qtyValue == packedQty || qtyValue <= packedQty) {
                        item.remove();
                    } else if (qtyValue > packedQty) {
                        // Fix float number precision
                        qtyEl.value = Number((qtyValue - packedQty).toFixed(4));
                    }
                }
            }
        }

        if (this.itemsAll.length === 0) {
            this.itemsAll = itemsAll;
        }

        for (const checkboxEl of packagePrepare.querySelectorAll('tbody input[type=checkbox]')) {
            checkboxEl.addEventListener('change', this._observeQty);
            this._observeQty.call(checkboxEl);
        }
    }

    _observeQty() {
        const qtyEl = this.closest('tr').querySelector('td:last-child input[name=qty]');
        setElementDisable(qtyEl, !this.checked);
    }

    _checkExceedsQty(itemId, qty) {
        const packedItemQty = this.getPackedItemsQty()[itemId] ? this.getPackedItemsQty()[itemId] : 0;
        const allItemQty = this.itemsAll[itemId];
        return (qty * (1 - this.eps) > (allItemQty *  (1 + this.eps)  - packedItemQty * (1 - this.eps)));
    }

    _checkExceedsQtyFinal(checkOne, defQty) {
        return checkOne * (1 + this.eps) >= defQty * (1 - this.eps);
    }

    _recalcContainerWeightAndCustomsValue(container) {
        const packageBlock = container.closest('[id^=package_block]');
        const packageId = packageBlock.dataset.id;
        const packageItems = packageBlock.querySelector('.package_items');

        let weight = 0, customsValue = 0;

        this._parseAllQty(container);

        for (const item of packageItems.querySelectorAll('.grid tbody tr')) {
            const checkbox = item.querySelector('[type=checkbox]');;
            const qtyInput = item.querySelector('[name=qty]');

            const itemCustomsValue = parseFloat(item.querySelector('[name=customs_value]')?.value) || 0;
            this.packages[packageId]['items'][checkbox.value]['customs_value'] = itemCustomsValue;
            customsValue += itemCustomsValue;

            weight += parseFloat(item.querySelector('.weight').textContent) * qtyInput.value;
        }

        const containerWeight = packageBlock.querySelector('[name=container_weight]');
        if (containerWeight) {
            containerWeight.value = weight.toFixed(4);
        }

        const containerCustomsValue = packageBlock.querySelector('[name=package_customs_value]');
        if (containerCustomsValue) {
            containerCustomsValue.value = customsValue > 0 ? customsValue.toFixed(2) : '';
        }
    }

    _getElementText(el) {
        return el.textContent;
    }
}
