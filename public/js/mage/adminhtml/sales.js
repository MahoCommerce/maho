/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2017-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class AdminOrder
{
    gridProducts = new Map();
    overlayData = new Map();
    productConfigureAddFields = {};
    productPriceBase = {};
    dataArea = null;
    itemsArea = null;
    billingAddressContainer = '';
    shippingAddressContainer= '';
    collectElementsValue = true;
    giftMessageDataChanged = false;

    constructor() {
        this.initialize(...arguments);
    }

    initialize(data) {
        data = {
            customer_id: false,
            is_guest: false,
            store_id: false,
            currency_symbol: '',
            addresses: {},
            shippingAsBilling: false,
            shipping_method_reseted: false,
            ...data,
        }

        this.loadBaseUrl             = false;
        this.customerId              = data.customer_id;
        this.isGuest                 = data.is_guest;
        this.storeId                 = data.store_id;
        this.currencyId              = false;
        this.currencySymbol          = data.currency_symbol;
        this.addresses               = data.addresses;
        this.shippingAsBilling       = data.shippingAsBilling;
        this.isShippingMethodReseted = data.shipping_method_reseted;

        document.addEventListener('DOMContentLoaded', this.initAreas.bind(this));
    }

    initAreas() {
        this.dataArea = new OrderFormArea('data', this.getAreaEl('data'), this);
        this.itemsArea = new OrderFormArea('items', this.getAreaEl('items'), this);

        this.dataArea.onLoad = wrapFunction(this.dataArea.onLoad, (proceed) => {
            proceed();
            this.itemsArea.setNode(this.getAreaEl('items'));
            this.itemsArea.onLoad();
        });

        this.areasLoaded();
        this.itemsArea.onLoad();
    }

    areasLoaded() {
    }

    itemsLoaded() {
    }

    dataLoaded() {
        this.dataShow();
    }

    setLoadBaseUrl(url) {
        this.loadBaseUrl = url;
    }

    setAddresses(addresses) {
        this.addresses = addresses;
    }

    setCustomerIsGuest() {
        this.isGuest = true;
        this.setCustomerId(false);
    }

    setCustomerId(id) {
        this.customerId = id;
        this.loadArea('header', true);
        this.getAreaEl('header').callback = 'setCustomerAfter';
        toggleVis('back_order_top_button', false);
        toggleVis('reset_order_top_button', true);
    }

    setCustomerAfter() {
        this.customerSelectorHide();
        if (this.storeId) {
            this.getAreaEl('data').callback = 'dataLoaded';
            this.loadArea(['data'], true);
        } else {
            this.storeSelectorShow();
        }
    }

    setStoreId(id) {
        this.storeId = id;
        this.storeSelectorHide();
        this.sidebarShow();
        this.dataShow();
        this.loadArea(['header', 'data'], true);
    }

    setCurrencyId(id) {
        this.currencyId = id;
        this.loadArea(['data'], true);
    }

    setCurrencySymbol(symbol) {
        this.currencySymbol = symbol;
    }

    selectAddress(el, container) {
        const addressId = el.value || '0';
        const address = this.addresses[addressId] ?? {};

        this.fillAddressFields(container, address);

        if (this.isBillingField(el.id) && this.shippingAsBilling) {
            this.fillAddressFields(this.shippingAddressContainer, address);
        }

        const data = {
            ...this.serializeData(container),
            [el.name]: addressId,
        };

        if (this.isShippingField(container) && !this.isShippingMethodReseted) {
            this.resetShippingMethod(data);
        } else {
            this.saveData(data);
        }
    }

    isShippingField(fieldId) {
        if (this.shippingAsBilling) {
            return fieldId.includes('billing');
        }
        return fieldId.includes('shipping');
    }

    isBillingField(fieldId) {
        return fieldId.includes('billing');
    }

    bindAddressFields(containerId) {
        const container = this.getContainerEl(containerId);
        for (const field of container.querySelectorAll('input, select, textarea')) {
            field.addEventListener('change', this.changeAddressField.bind(this));
        }
    }

    changeAddressField(event) {
        const field = event.target;
        const pattern = /[^\[]*\[([^\]]*)_address\]\[([^\]]*)\](\[(\d)\])?/;

        const matchRes = field.name.match(pattern);
        if (matchRes === null) {
            return;
        }

        const type = matchRes[1];
        const name = matchRes[2];
        const data = this.isBillingField(field.id)
              ? this.serializeData(this.billingAddressContainer)
              : this.serializeData(this.shippingAddressContainer);

        if (type === 'billing' && this.shippingAsBilling && !this.isShippingMethodReseted) {
            data['reset_shipping'] = true;
        }
        if (type === 'shipping' && !this.shippingAsBilling && !this.isShippingMethodReseted) {
            data['reset_shipping'] = true;
        }

        const addressId = document.getElementById(`order-${type}_address_customer_address_id`).value;
        data[`order[${type}_address][customer_address_id]`] = addressId;

        if (type === 'billing' && this.shippingAsBilling) {
            this.copyDataFromBillingToShipping(field);
        }

        if (data['reset_shipping']) {
            this.resetShippingMethod(data);
        } else {
            this.saveData(data);
            if (!this.isShippingMethodReseted && ['country_id', 'customer_address_id'].includes(name)) {
                this.loadArea(['shipping_method', 'billing_method', 'totals', 'items'], true, data);
            }
        }
    }

    copyDataFromBillingToShipping(fromEl) {
        const toEl = document.getElementById(fromEl.id.replace('-billing_', '-shipping_'));
        if (!toEl) {
            return;
        }

        toEl.value = fromEl.value;
        if (typeof toEl.changeUpdater === 'function') {
            toEl.changeUpdater();
        }

        const container = this.getContainerEl(this.shippingAddressContainer);
        for (const field of container.getElementsByTagName('select')) {
            setElementDisable(field, true);
        }
    }

    fillAddressFields(containerId, data) {
        const pattern = /[^\[]*\[[^\]]*\]\[([^\]]*)\](\[(\d)\])?/;

        const container = this.getContainerEl(containerId);
        for (const field of container.querySelectorAll('input, select, textarea')) {

            // skip input type file @Security error code: 1000
            if (field.tagName === 'INPUT' && field.type.toLowerCase() === 'file') {
                continue;
            }

            const matchRes = field.name.match(pattern);
            if (matchRes === null) {
                continue;
            }

            const name = matchRes[1];
            const index = matchRes[3];
            const value = data[name] ?? '';

            if (index) {
                // multiple line
                field.value = value.split("\n")[index] ?? '';
            } else if (field.tagName === 'SELECT' && field.multiple) {
                // multiselect
                let values = [''];
                if (Array.isArray(value)) {
                    values = value;
                } else if (typeof value === 'string' || value instanceof String) {
                    values = value.split(',');
                }
                for (const optionEl of field.options) {
                    optionEl.selected = values.inclues(option.value);
                }
            } else {
                field.value = value;
            }
            if (typeof field.changeUpdater === 'function') {
                field.changeUpdater();
            }
            if (name === 'region' && data['region_id'] > 0 && !data['region']) {
                field.value = data['region_id'];
            }
        }
    }

    disableShippingAddress(flag) {
        this.shippingAsBilling = flag;

        const addressSelectEl = document.getElementById('order-shipping_address_customer_address_id');
        if (addressSelectEl) {
            addressSelectEl.disabled = flag;
        }

        const container = this.getContainerEl(this.shippingAddressContainer);
        if (!container) {
            return;
        }

        for (const field of container.querySelectorAll('input, select, textarea')) {
            field.disabled = flag;
        }
        for (const button of container.querySelectorAll('button')) {
            button.disabled = flag;
            button.classList.toggle('disabled', flag);
        }
    }

    turnOffShippingFields() {
        const container = this.getContainerEl(this.shippingAddressContainer);
        if (!container) {
            return;
        }

        for (const field of container.querySelectorAll('input, select, textarea, button')) {
            field.removeAttribute('name');
            field.removeAttribute('id');
            field.readOnly = true;
        }
    }

    setShippingAsBilling(flag) {
        this.disableShippingAddress(flag);
        this.loadArea(['shipping_method', 'billing_method', 'shipping_address', 'totals', 'giftmessage'], true, {
            ...this.serializeData(flag ? this.billingAddressContainer : this.shippingAddressContainer),
            shipping_as_billing: flag ? 1 : 0,
            reset_shipping: 1,
        });
    }

    resetShippingMethod(data) {
        this.isShippingMethodReseted = true;
        this.loadArea(['shipping_method', 'billing_method', 'totals', 'giftmessage', 'items'], true, {
            ...data,
            reset_shipping: 1,
        });
    }

    loadShippingRates() {
        this.isShippingMethodReseted = false;
        this.loadArea(['shipping_method', 'totals'], true, {
            collect_shipping_rates: 1,
        });
    }

    setShippingMethod(method) {
        this.loadArea(['shipping_method', 'totals', 'billing_method'], true, {
            'order[shipping_method]': method,
            shipping_as_billing: this.shippingAsBilling ? 1 : 0,
        });
    }

    switchPaymentMethod(method) {
        this.setPaymentMethod(method);
        this.loadArea(['card_validation'], true, {
            'order[payment_method]': method,
        });
    }

    setPaymentMethod(method) {
        if (this.paymentMethod && document.getElementById(`payment_form_${this.paymentMethod}`)) {
            const form = `payment_form_${this.paymentMethod}`;
            for (const blockId of [`${form}_before`, form, `${form}_after`]) {
                const blockEl = document.getElementById(blockId);
                if (!blockEl) {
                    continue;
                }
                toggleVis(blockEl, false);
                for (const field of blockEl.querySelectorAll('input, select, textarea')) {
                    field.disabled = true;
                }
            }
        }

        if (!this.paymentMethod || method) {
            const billingMethodForm = document.getElementById('order-billing_method_form');
            for (const field of billingMethodForm.querySelectorAll('input, select, textarea')) {
                if (field.type !== 'radio') {
                    field.disabled = true;
                }
            }
        }

        if (document.getElementById(`payment_form_${method}`)) {
            const form = `payment_form_${method}`;
            for (const blockId of [`${form}_before`, form, `${form}_after`]) {
                const blockEl = document.getElementById(blockId);
                if (!blockEl) {
                    continue;
                }
                toggleVis(blockEl, true);
                for (const field of blockEl.querySelectorAll('input, select, textarea')) {
                    field.disabled = false;
                    if (!blockId.endsWith('_before') && !blockId.endsWith('_after') && !field.bindChange) {
                        field.bindChange = true;
                        field.method = method;
                        field.addEventListener('change', this.changePaymentData.bind(this));
                    }
                }
            }
            this.paymentMethod = method;
        }
    }

    changePaymentData(event) {
        const method = event.target.method;
        if (!method) {
            return;
        }

        const data = this.getPaymentData(method);
        if (data) {
            this.loadArea(['card_validation'], true, data);
        }
    }

    getPaymentData(currentMethod) {
        if (typeof currentMethod === 'undefined') {
            if (this.paymentMethod) {
                currentMethod = this.paymentMethod;
            } else {
                return false;
            }
        }

        const data = {};

        const paymentForm = document.getElementById(`payment_form_${currentMethod}`);
        for (const field of paymentForm.querySelectorAll('input, select')) {
            data[field.name] = field.value;
        }

        if (typeof data['payment[cc_type]'] !== 'undefined' && (!data['payment[cc_type]'] || !data['payment[cc_number]'])) {
            return false;
        }
        return data;
    }

    applyCoupon(code) {
        this.loadArea(['items', 'shipping_method', 'totals', 'billing_method'], true, {
            'order[coupon][code]': code,
            reset_shipping: true,
        });
    }

    addProduct(id) {
        this.loadArea(['items', 'shipping_method', 'totals', 'billing_method'], true, {
            add_product: id,
            reset_shipping: true,
        });
    }

    removeQuoteItem(id) {
        this.loadArea(['items', 'shipping_method', 'totals', 'billing_method'], true, {
            remove_item: id,
            from: 'quote',
            reset_shipping: true,
        });
    }

    moveQuoteItem(id, to) {
        this.loadArea([`sidebar_${to}`, 'items', 'shipping_method', 'totals', 'billing_method'], this.getAreaId('items'), {
            move_item: id,
            to,
            reset_shipping: true,
        });
    }

    productGridShow() {
        this.showArea('search');
    }

    productGridHide() {
        this.hideArea('search');
        this.gridProducts.clear();
        productConfigure.clean('quote_items');
        sales_order_create_search_gridJsObject?.resetFilter();
    }

    productGridRowInit(grid, row) {
        const checkbox = row.querySelector('.checkbox');
        const inputs = row.querySelectorAll('.input-text');
        if (!checkbox || inputs.length === 0) {
            return;
        }

        checkbox.inputElements = inputs;
        for (const input of inputs) {
            input.checkboxElement = checkbox;

            const defaultValue = this.gridProducts.get(checkbox.value)?.[input.name];
            if (defaultValue) {
                if (input.name === 'giftmessage') {
                    input.checked = true;
                } else {
                    input.value = defaultValue;
                }
            }

            input.disabled = !checkbox.checked || input.classList.contains('input-inactive');
            input.addEventListener('keyup', this.productGridRowInputChange.bind(this));
            input.addEventListener('change',this.productGridRowInputChange.bind(this));
        }
    }

    productGridRowInputChange(event) {
        const inputEl = event.target;
        const checkboxEl = inputEl.checkboxElement;
        if (!checkboxEl?.checked) {
            return;
        }

        const product = this.gridProducts.get(checkboxEl.value);
        if (inputEl.name === 'giftmessage') {
            delete product[inputEl.name];
        } else if (inputEl.checked) {
            product[inputEl.name] = inputEl.value;
        }
    }

    productGridRowClick(grid, event) {
        const trElement = event.target.closest('tr');
        const qtyElement = trElement.querySelector('input[name=qty]');
        const isInputCheckbox = event.target.tagName === 'INPUT' && event.target.type === 'checkbox';
        const isInputQty = event.target.tagName === 'INPUT' && event.target.name === 'qty';
        if (!trElement || isInputQty) {
            return;
        }

        const checkbox = trElement.querySelector('input[type=checkbox]');
        const confLink = trElement.querySelector('a');
        const priceCol = trElement.querySelector('.price');
        if (!checkbox) {
            return;
        }

        // processing non composite product
        if (confLink.getAttribute('disabled')) {
            const checked = isInputCheckbox ? checkbox.checked : !checkbox.checked;
            grid.setCheckboxChecked(checkbox, checked);
            return;
        }

        // processing composite product
        if (isInputCheckbox && !checkbox.checked) {
            grid.setCheckboxChecked(checkbox, false);
            return;
        }

        // processing composite product
        if (!isInputCheckbox || (isInputCheckbox && checkbox.checked)) {
            const listType = confLink.getAttribute('list_type');
            const productId = confLink.getAttribute('product_id');
            if (typeof this.productPriceBase[productId] === 'undefined') {
                const priceBase = priceCol.textContent.match(/.*?([\d,]+\.?\d*)/);
                if (!priceBase) {
                    this.productPriceBase[productId] = 0;
                } else {
                    this.productPriceBase[productId] = parseFloat(priceBase[1].replace(/,/g, ''));
                }
            }

            productConfigure.setConfirmCallback(listType, () => {
                // sync qty of popup and qty of grid
                this._syncQuantityElements(productConfigure.getCurrentConfirmedQtyElement(), qtyElement);

                // calc and set product price
                const productPrice = parseFloat(this._calcProductPrice() + this.productPriceBase[productId]);
                priceCol.textContent = this.currencySymbol + productPrice.toFixed(2);

                // set checkbox checked
                grid.setCheckboxChecked(checkbox, true);
            });

            productConfigure.setCancelCallback(listType, () => {
                if (!productConfigure.itemConfigured(listType, productId)) {
                    grid.setCheckboxChecked(checkbox, false);
                }
            });

            productConfigure.setShowWindowCallback(listType, () => {
                // sync qty of grid and qty of popup
                this._syncQuantityElements(qtyElement, productConfigure.getCurrentFormQtyElement());
            });

            // Show product configure pop up window
            productConfigure.showItemConfiguration(listType, productId);
        }
    }

    _syncQuantityElements(fromEl, toEl) {
        if (!(toEl instanceof HTMLInputElement)) {
            return;
        }
        if (fromEl?.value && !isNaN(fromEl.value)) {
            toEl.value = fromEl.value;
        }
        if (toEl.value < 1) {
            toEl.value = 1;
        }
    }

    /**
     * Calc product price through its options
     */
    _calcProductPrice() {
        let productPrice = 0;
        for (const el of productConfigure.getCurrentConfirmedBlock().querySelectorAll('input, select, textarea')) {
            if (['select-one', 'select-multiple'].includes(el.type)) {
                for (const option of el.selectedOptions) {
                    productPrice += this._calcOptionPrice(option);
                }
            }
            if (['checkbox', 'radio'].includes(el.type) && el.checked) {
                productPrice += this._calcOptionPrice(el);
            }
            if (['file', 'text', 'textarea', 'hidden'].includes(el.type) && el.value) {
                productPrice += this._calcOptionPrice(el);
            }
        }
        return productPrice;
    }

    _calcOptionPrice(inputEl) {
        let optQty = 1;
        if (inputEl.hasAttribute('qtyId')) {
            const qtyEl = document.getElementById(inputEl.getAttribute('qtyId'));
            if (!qtyEl.value) {
                return 0;
            }
            optQty = parseFloat(qtyEl.value);
        }
        if (inputEl.hasAttribute('price') && !inputEl.disabled) {
            return parseFloat(inputEl.getAttribute('price')) * optQty;
        }
        return 0;
    }

    productGridCheckboxCheck(grid, element, checked) {
        if (checked) {
            if (element.inputElements) {
                const product = {};
                for (const inputEl of element.inputElements) {
                    if (!inputEl.classList.contains('input-inactive')) {
                        inputEl.disabled = false;
                        if (inputEl.name === 'qty' && !inputEl.value) {
                            inputEl.value = 1;
                        }
                    }
                    if (inputEl.checked || inputEl.name !== 'giftmessage') {
                        product[inputEl.name] = inputEl.value;
                    }
                }
                this.gridProducts.set(element.value, product);
            }
        } else {
            for (const inputEl of element.inputElements ?? []) {
                inputEl.disabled = true;
            }
            this.gridProducts.delete(element.value);
        }
        grid.reloadParams = {'products[]': this.gridProducts.keys()};
    }

    /**
     * Submit configured products to quote
     */
    productGridAddSelected() {
        const area = ['search', 'items', 'shipping_method', 'totals', 'giftmessage','billing_method'];

        // prepare additional fields and filtered items of products
        const fieldsPrepare = {};
        const itemsFilter = [];
        for (const [productId, product] of this.gridProducts) {
            let paramKey = `item[${productId}]`;
            for (const [key, value] of Object.entries(product)) {
                paramKey += `[${key}]`;
                fieldsPrepare[paramKey] = value;
            }
            itemsFilter.push(productId);
        }

        this.productConfigureSubmit('product_to_add', area, fieldsPrepare, itemsFilter);
        this.productGridHide();
    }

    selectCustomer(grid, event) {
        const element = event.target.closest('tr');
        if (element?.title) {
            this.setCustomerId(element.title);
        }
    }

    customerSelectorHide() {
        this.hideArea('customer-selector');
    }

    customerSelectorShow() {
        this.showArea('customer-selector');
    }

    storeSelectorHide() {
        this.hideArea('store-selector');
    }

    storeSelectorShow() {
        this.showArea('store-selector');
    }

    dataHide() {
        this.hideArea('data');
    }

    dataShow() {
        const submitButton = document.getElementById('submit_order_top_button');
        if (submitButton) {
            toggleVis(submitButton, true);
        }
        this.showArea('data');
    }

    clearShoppingCart(confirmMessage) {
        if (!confirm(confirmMessage)) {
            return;
        }

        this.collectElementsValue = false;
        order.sidebarApplyChanges({
            'sidebar[empty_customer_cart]': 1,
        });
    }

    sidebarApplyChanges(auxiliaryParams) {
        const sidebarEl = this.getAreaEl('sidebar');
        if (!sidebarEl) {
            return;
        }

        const data = {};
        if (this.collectElementsValue) {
            var elems = document.getElementById(this.getAreaId('sidebar')).querySelectorAll('input');
            for (var i=0; i < elems.length; i++) {
                if (elems[i].value) {
                    data[elems[i].name] = elems[i].value;
                }
            }
        }
        if (typeof auxiliaryParams === 'object' && auxiliaryParams !== null) {
            Object.assign(data, auxiliaryParams);
        }
        data.reset_shipping = true;
        this.loadArea(['sidebar', 'items', 'shipping_method', 'billing_method','totals', 'giftmessage'], true, data);
    }

    sidebarHide() {
        return;
    }

    sidebarShow() {
        return;
    }

    /**
     * Show configuration of product and add handlers on submit form
     *
     * @param productId
     */
    sidebarConfigureProduct(listType, productId, itemId) {
        // create additional fields
        const params = this.prepareParams({
            reset_shipping: true,
            add_product: productId,
        });

        const fields = [];
        for (const [name, value] of Object.entries(params)) {
            if (value === null) {
                delete params[name];
                continue;
            }
            if (typeof value === 'boolean') {
                params[name] = value ? 1 : 0;
            }
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            fields.push(input);
        }

        // add additional fields before triggered submit
        productConfigure.setBeforeSubmitCallback(listType, () => {
            productConfigure.addFields(fields);
        });

        // response handler
        productConfigure.setOnLoadIFrameCallback(listType, () => {
            this.loadArea(['items', 'shipping_method', 'billing_method','totals', 'giftmessage'], true);
        });

        // show item configuration
        productConfigure.showItemConfiguration(listType, itemId || productId);
        return false;
    }

    removeSidebarItem(id, from) {
        this.loadArea([`sidebar_${from}`], `sidebar_data_${from}`, {remove_item:id, from});
    }

    itemsUpdate() {
        const area = ['sidebar', 'items', 'shipping_method', 'billing_method', 'totals', 'giftmessage'];

        // prepare additional fields
        const fieldsPrepare = {
            update_items: 1,
        };

        const gridEl = document.getElementById('order-items_grid');
        for (const field of gridEl.querySelectorAll('input, select, textarea')) {
            if (!field.disabled && (field.type !== 'checkbox' || field.checked)) {
                fieldsPrepare[field.name] = field.value;
            }
        }

        Object.assign(fieldsPrepare, this.productConfigureAddFields);
        this.productConfigureSubmit('quote_items', area, fieldsPrepare);
        this.orderItemChanged = false;
    }

    itemsOnchangeBind() {
        const gridEl = document.getElementById('order-items_grid');
        for (const field of gridEl.querySelectorAll('input, select, textarea')) {
            if (!field.bindOnchange) {
                field.bindOnchange = true;
                field.addEventListener('change', this.itemChange.bind(this));
            }
        }
    }

    itemChange(event) {
        this.giftmessageOnItemChange(event);
        this.orderItemChanged = true;
    }

    /**
     * Submit batch of configured products
     *
     * @param listType
     * @param area
     * @param fieldsPrepare
     * @param itemsFilter
     */
    productConfigureSubmit(listType, area, fieldsPrepare, itemsFilter) {
        this.loadingAreas = this.prepareArea(area);

        // prepare additional fields
        const params = this.prepareParams({
            ...fieldsPrepare,
            reset_shipping: true,
        });
        params.json = 1;

        // create fields
        const fields = [];
        for (const [name, value] of Object.entries(params)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            fields.push(input);
        }
        productConfigure.addFields(fields);

        // filter items
        if (itemsFilter) {
            productConfigure.addItemsFilter(listType, itemsFilter);
        }

        // prepare and do submit
        productConfigure.addListType(listType, {
            urlSubmit: setRouteParams(this.loadBaseUrl, { block: this.loadingAreas }),
        });

        // Submit
        productConfigure.setOnLoadIFrameCallback(listType, this.loadAreaResponseHandler.bind(this));
        productConfigure.submit(listType);

        // clean
        this.productConfigureAddFields = {};
    }

    /**
     * Show configuration of quote item
     *
     * @param itemId
     */
    showQuoteItemConfiguration(itemId) {
        const listType = 'quote_items';
        const qtyElement = document.querySelector(`#order-items_grid input[name="item[${itemId}][qty]"]`);

        productConfigure.setConfirmCallback(listType, () => {
            // sync qty of popup and qty of grid
            this._syncQuantityElements(productConfigure.getCurrentConfirmedQtyElement(), qtyElement);
            this.productConfigureAddFields[`item[${itemId}][configured]`] = 1;
        });

        productConfigure.setShowWindowCallback(listType, () => {
            // sync qty of grid and qty of popup
            this._syncQuantityElements(qtyElement, productConfigure.getCurrentFormQtyElement());
        });

        // Show product configure pop up window
        productConfigure.showItemConfiguration(listType, itemId);
    }

    accountFieldsBind(containerId) {
        const container = this.getContainerEl(containerId);
        if (!container) {
            return;
        }
        for (const field of container.querySelectorAll('input, select, textarea')) {
            if (field.id === 'group_id') {
                field.addEventListener('change', this.accountGroupChange.bind(this));
            } else {
                field.addEventListener('change', this.accountFieldChange.bind(this));
            }
        }
    }

    accountGroupChange() {
        this.loadArea(['data'], true, this.serializeData('order-form_account'));
    }

    accountFieldChange() {
        this.saveData(this.serializeData('order-form_account'));
    }

    commentFieldsBind(containerId) {
        const container = this.getContainerEl(containerId);
        if (!container) {
            return;
        }
        for (const field of container.querySelectorAll('input, textarea')) {
            field.addEventListener('change', this.commentFieldChange.bind(this));
        }
    }

    commentFieldChange() {
        this.saveData(this.serializeData('order-comment'));
    }

    giftmessageFieldsBind(containerId) {
        const container = this.getContainerEl(containerId);
        if (!container) {
            return;
        }
        for (const field of container.querySelectorAll('input, textarea')) {
            field.addEventListener('change', this.giftmessageFieldChange.bind(this));
        }
    }

    giftmessageFieldChange() {
        this.giftMessageDataChanged = true;
    }

    giftmessageOnItemChange(event) {
        if (!event.target.name.includes('giftmessage') || event.target.type !== 'checkbox' || !event.target.checked) {
            return;
        }
        for (const message of document.getElementById('order-giftmessage').querySelectorAll('textarea')) {
            const name = message.id.split('_');
            if (name.length > 1 && event.target.name.includes(`[${name[1]}]`) && message.value !== '') {
                alert(Translator.translate('First, clean the Message field in Gift Message form'));
                event.target.checked = true;
            }
        }
    }

    async loadArea(area, loaderArea, params) {
        area = this.prepareArea(area);
        params = this.prepareParams(params);
        params.json = true;

        const url = setRouteParams(this.loadBaseUrl, { block: area });

        if (loaderArea) {
            this.loadingAreas = area;
        } else if (!this.loadingAreas) {
            this.loadingAreas = [];
        }

        try {
            const result = await mahoFetch(url, {
                method: 'POST',
                body: new URLSearchParams(params),
                loaderArea,
            });
            if (loaderArea) {
                this.loadAreaResponseHandler(result);
            }
        } catch (error) {
            console.error(error);
            alert(error.message);
        }
        if (typeof productConfigure !== 'undefined' && Array.isArray(area) && area.includes('items')) {
            productConfigure.clean('quote_items');
        }
    }

    loadAreaResponseHandler(response) {
        if (!this.loadingAreas) {
            this.loadingAreas = [];
        }
        if (typeof this.loadingAreas === 'string') {
            this.loadingAreas = [this.loadingAreas];
        }
        if (!this.loadingAreas.includes('message')) {
            this.loadingAreas.push('message');
        }

        for (const area of this.loadingAreas) {
            const areaEl = document.getElementById(this.getAreaId(area));
            if (!areaEl) {
                continue;
            }
            if (area !== 'message' || response[area]) {
                updateElementHtmlAndExecuteScripts(areaEl, response[area] ?? '');
            }
            if (typeof this[areaEl.callback] === 'function') {
                this[areaEl.callback]();
            }
        }
    }

    prepareArea(area) {
        if (Array.isArray(area) && this.giftMessageDataChanged) {
            return area.filter(val => val !=='giftmessage');
        }
        return area;
    }

    saveData(data) {
        this.loadArea(false, false, data);
    }

    showArea(area) {
        const areaId = this.getAreaId(area);
        const areaEl = document.getElementById(areaId);
        if (areaEl) {
            toggleVis(areaEl, true);
            this.areaOverlay();
        }
    }

    hideArea(area) {
        const areaId = this.getAreaId(area);
        const areaEl = document.getElementById(areaId);
        if (areaEl) {
            toggleVis(areaEl, false);
            this.areaOverlay();
        }
    }

    areaOverlay() {
        for (const overlay of Object.values(this.overlayData)) {
            overlay.fx();
        }
    }

    getAreaId(area) {
        return `order-${area}`;
    }

    getAreaEl(area) {
        return document.getElementById(this.getAreaId(area));
    }

    getContainerEl(container) {
        if (typeof container === 'string' || container instanceof String) {
            container = document.getElementById(container);
        }
        if (container instanceof Element) {
            return container;
        }
    }

    prepareParams(params) {
        if (typeof params?.toObject === 'function') {
            params = params.toObject();
        }
        if (!params) {
            params = {};
        }
        if (!params.customer_id) {
            params.customer_id = this.customerId;
        }
        if (!params.customer_is_guest) {
            params.customer_is_guest = this.isGuest ? 1 : 0;
        }
        if (!params.store_id) {
            params.store_id = this.storeId;
        }
        if (!params.currency_id) {
            params.currency_id = this.currencyId;
        }
        for (const [name, value] of Object.entries(this.serializeData('order-billing_method'))) {
            params[name] = value;
        }
        return params;
    }

    serializeData(containerId) {
        const container = this.getContainerEl(containerId);
        if (!container) {
            return {};
        }
        const data = {};
        for (const field of container.querySelectorAll('input, select, textarea')) {
            data[field.name] = field.value;
        }
        return data;
    }

    toggleCustomPrice(checkbox, priceInputId, tierBlockId) {
        const priceInput = document.getElementById(priceInputId);
        if (priceInput) {
            priceInput.disabled = !checkbox.checked;
            toggleVis(priceInput, checkbox.checked)
        }

        const tierBlock = document.getElementById(tierBlockId);
        if (tierBlock) {
            toggleVis(tierBlock, !checkbox.checked)
        }
    }

    submit() {
        const confirmText = Translator.translate('You have unsaved item changes. Discard and proceed with checkout?');
        if (this.orderItemChanged && !confirm(confirmText)) {
            this.itemsUpdate();
            return;
        }
        if (editForm.submit()) {
            disableElements('save');
        }
    }

    overlay(elId, show = true) {
        if (!this.overlayData.has(elId)) {
            this.overlayData.set(elId, {
                el: elId,
                order: this,
                fx(event) {
                    this.order.processOverlay(this.el, this.show);
                }
            });
        }

        this.overlayData.get(elId).show = show;
        this.processOverlay(elId, show);
    }

    processOverlay(elId, show) {
        const el = document.getElementById(elId);
        if (!el) {
            return false;
        }

        toggleVis(el, !show);
        el.parentElement.classList.toggle('ignore-validate', !show);
    }

    async validateVat(parameters) {
        const params = {
            country: document.getElementById(parameters.countryElementId).value,
            vat: document.getElementById(parameters.vatElementId).value
        };

        if (this.storeId !== false) {
            params.store_id = this.storeId;
        }

        let message = '';
        let groupChangeRequired = false;
        const currentCustomerGroupId = document.getElementById(parameters.groupIdHtmlId).value;

        try {
            const result = await mahoFetch(parameters.validateUrl, {
                method: 'POST',
                body: new URLSearchParams(params),
            });

            if (result.valid === true) {
                if (currentCustomerGroupId == result.group || !result.group) {
                    message = parameters.vatValidMessage;
                } else {
                    message = parameters.vatValidAndGroupChangeMessage;
                    groupChangeRequired = true;
                }
            } else if (result.success === true) {
                message = parameters.vatInvalidMessage.replace(/%s/, params.vat);
                groupChangeRequired = true;
            } else {
                message = parameters.vatValidationFailedMessage;
                groupChangeRequired = true;
            }
            if (groupChangeRequired && !result.group) {
                message = parameters.vatErrorMessage;
                groupChangeRequired = false;
            }
            if (groupChangeRequired) {
                this.processCustomerGroupChange(parameters.groupIdHtmlId, message, result.group);
            } else {
                alert(message);
            }
        } catch (error) {
            console.error(error);
            alert(parameters.vatErrorMessage)
        }
    }

    processCustomerGroupChange(groupIdHtmlId, message, groupId) {
        const groupSelectEl = document.getElementById(groupIdHtmlId);
        const oldGroup = groupSelectEl.querySelector(`option[value="${groupSelectEl.value}"]`).textContent;
        const newGroup = groupSelectEl.querySelector(`option[value="${groupId}"]`).textContent;

        const confirmText = message.replace('%s', newGroup).replace('%s', oldGroup);
        if (!confirm(confirmText)) {
            return;
        }

        for (const option of groupSelectEl.options) {
            option.selected = option.value == groupId;
        }
        this.accountGroupChange();
    }
};

class OrderFormArea {
    _name = null;
    _node = null;
    _parent =  null;
    _callbackName = null;

    constructor() {
        this.initialize(...arguments);
    }

    initialize(name, node, parent) {
        this._name = name;
        this._parent = parent;
        this._callbackName = node.callback ?? `${name}Loaded`;

        parent[this._callbackName] = wrapFunction(parent[this._callbackName].bind(parent), (proceed) => {
            proceed();
            this.onLoad();
        });

        this.setNode(node);
    }

    setNode(node) {
        this.node = node;
        this.node.callback ??= this._callbackName;
    }

    onLoad() {
    }
};
