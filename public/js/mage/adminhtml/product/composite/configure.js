/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

document.addEventListener('DOMContentLoaded', () => {
    window.productConfigure = new ProductConfigure();
});

/**
 * Adminhtml Product Configure Dialog Controller
 */
class ProductConfigure // Maho.Admin.Controller.ProductConfigurePopup
{
    listTypes                   = {};
    itemsFilter                 = {};
    current                     = {};
    confirmedCurrentId          = null;
    confirmCallback             = {};
    cancelCallback              = {};
    onLoadIFrameCallback        = {};
    showWindowCallback          = {};
    beforeSubmitCallback        = {};
    _listTypeId                 = 1;

    compatibilityFlags = {
        /**
         * Magento 1.9 stores copies of product config forms and state in DOM and stores and copies it to dialogs on demand.
         * This causes issues with duplicate element IDs and is generally bad design. This updated class still stores config
         * templates in DOM along with a stored FormData object, but by default it detaches the container div that stores state.
         */
        detachConfigureContainerElement: true,

        /**
         * Magento 1.9 transfers state back from renamed and submitted form fields after using the `urlSubmit` method of form
         * submission. This updated class stores a FormData object along with confirmed block template, so transferring state back
         * isn't necessary, but this option is kept in case any callbacks change the form state and rely on restoring state.
         */
        restoreFormToConfirmedAfterSubmit: false,
    };

    constructor() {
        this.initialize(...arguments);
    }

    /**
     * Initialize object
     */
    initialize() {
        this.blockContainer     = document.getElementById('product_composite_configure');
        this.blockMsg           = document.getElementById('product_composite_configure_messages');
        this.blockConfirmed     = document.getElementById('product_composite_configure_confirmed');
        this.blockForm          = document.getElementById('product_composite_configure_form');
        this.blockFormFields    = document.getElementById('product_composite_configure_form_fields');
        this.blockFormAdd       = document.getElementById('product_composite_configure_form_additional');
        this.blockFormConfirmed = document.getElementById('product_composite_configure_form_confirmed');

        this.varienForm = new varienForm('product_composite_configure_form');

        // Detach and keep reference to container element
        if (this.compatibilityFlags.detachConfigureContainerElement === true) {
            this.blockContainer.parentNode.removeChild(this.blockContainer);
        }
    }

    /**
     * Initialize window elements
     */
    _initWindowElements() {
    }

    /**
     * Returns next unique list type id
     */
    _generateListTypeId () {
        return `_internal_lt_${this._listTypeId++}`;
    }

    /**
     * Configure AJAX URLs for getting and posting product configuration forms.
     * There are two options for sending data to Maho's backend, however both methods use the same URL for fetching the dialog
     * window's content. For each list type, provide `urlFetch` for fetching the product's configuration form. This class will
     * POST the ID of the item to be configured (which can be a quote item, wishlist item, etc) and the controller should return
     * an HTML template to be shown to the user. This class stores state so the dialog can be opened / closed and later processed.
     *
     * The template can contain a <script> block that will be hydrated upon dialog open and close. A helper method can be called
     * to create new `Product.Config` instances for dropdown / swatch functionality. For example the configure form may include:
     * ```html
     * <script>
     * # app/design/adminhtml/default/default/template/catalog/product/composite/fieldset/configurable.phtml
     * window.productConfigure?.registerConfigurableFieldset(<?= $this->getJsonConfig() ?>);
     * </script>
     * ```
     *
     * The first method to POST data is for singular product configurations. These dialogs will save data upon pressing the OK
     * button after successful form validation. Values are sent to the backend without prefixed form names. Example POST data:
     * ```
     *     super_attribute[92]: 17
     *     super_attribute[180]: 79
     *     options[21]: test
     *     qty: 1
     * ```
     *
     * To use the first method, set `{ urlFetch, urlConfirm }` for getting / posting data to the backend:
     * ```js
     *     # app/design/adminhtml/default/default/template/customer/tab/wishlist.phtml
     *     productConfigure.addListType('wishlist', {
     *         urlFetch: '<?= $this->getUrl("*\/customer_wishlist_product_composite_wishlist/configure", $params) ?>',
     *         urlConfirm: '<?= $this->getUrl("*\/customer_wishlist_product_composite_wishlist/update", $params) ?>',
     *     ]);
     * ```
     *
     * The second method allows the admin user to configure multiple products to batch submit to the Maho backend. There are
     * complex options that are possible due to legacy Magento compatibility. See `this.submit()` and `this._renameFields()` for
     * reference. POST data can either be prefixed with the list types allowing multiple sources to be configured at once, or
     * a singular list type can be submitted and form names will only contain item IDs. Examples of POST data:
     * ```
     *     item[404][super_attribute][92]: 17
     *     item[404][super_attribute][180]: 79
     *     item[404][qty]: 1
     *     item[877][super_attribute][92]: 20
     *     item[877][super_attribute][180]: 78
     *     item[877][options][19][]: 16
     *     item[877][qty]: 1
     * ```
     *
     * This method is mainly used on the `/admin/sales_order_create/` controller where the admin user can configure items from
     * multiple sources, primarily from the the `quote_items` list type, but additionally from sources in the customer sidebar
     * such as their shopping cart and wishlists. First `{ urlFetch }` for each list type to fetch configuration forms:
     * ```js
     *     # app/design/adminhtml/default/default/template/sales/order/create/sidebar.phtml
     *     productConfigure.addListType('sidebar_wishlist', {
     *         urlFetch: '<?= $this->getUrl('*\/customer_wishlist_product_composite_wishlist/configure') ?>'
     *     });
     *     # app/design/adminhtml/default/default/template/sales/order/create/js.phtml
     *     productConfigure.addListType('quote_items', {
     *         urlFetch: '<?= $this->getUrl('*\/sales_order_create/configureQuoteItems') ?>'
     *     });
     * ```
     * Then submit the entire form:
     * ```js
     *     # app/design/adminhtml/default/default/template/customer/tab/wishlist.phtml
     *     productConfigure.addListType('wishlist', { urlSubmit: '<?= $this->getUrl("...") ?>' });
     *     productConfigure.setOnLoadIFrameCallback(listType, this.loadAreaResponseHandler);
     *     productConfigure.submit(listType);
     * ```
     *
     * @param {string} type - scope name for product source, one of `quote_items|sidebar_wishlist|customer_cart_grid|...`
     * @param {Object} urls - one / many URLs for getting / posting data, i.e. `{ urlFetch, urlConfirm, urlSubmit }`
     */
    addListType(type, urls) {
        this.listTypes[type] ??= {};
        Object.assign(this.listTypes[type], urls);
        return this;
    }

    /**
     * Configure complex list type that is used to submit several list types at once. Only urlSubmit is possible for this.
     * For example:
     * ```js
     *     addComplexListType(['wishlist', 'product_list'], '<?= $this->getUrl("...") ?>');
     * ```
     *
     * @param {string[]} types - array of scope name for product source, i.e. `quote_items|sidebar_wishlist|customer_cart_grid`
     * @param {string} urlSubmit - submit controller URL
     * @returns {string}
     */
    addComplexListType(types, urlSubmit) {
        const type = this._generateListTypeId();
        this.listTypes[type] = { complexTypes: types, urlSubmit };
        return type;
    }

    /**
     * Add filter of items
     *
     * @param listType scope name
     * @param itemsFilter
     */
    addItemsFilter(listType, itemsFilter) {
        if (!listType || !itemsFilter) {
            return false;
        }
        this.itemsFilter[listType] ??= [];
        this.itemsFilter[listType].push(...itemsFilter);
        return this;
    }

    /**
     * Returns id of block where configuration for an item is stored
     *
     * @param listType scope name
     * @param itemId
     * @return string
     */
    _getConfirmedBlockId(listType, itemId) {
        return `${this.blockConfirmed.id}[${listType}][${itemId}]`;
    }

    /**
     * Returns block where configuration for an item is stored
     *
     * @param listType scope name
     * @param itemId
     * @return HTMLDivElement
     */
    _getConfirmedBlock(listType, itemId) {
        let blockEl = this.blockConfirmed.querySelector(`[data-list-type="${listType}"][data-item-id="${itemId}"]`);
        if (!blockEl) {
            blockEl = document.createElement('div');
            blockEl.id = this._getConfirmedBlockId(listType, itemId);
            blockEl.dataset.listType = listType;
            blockEl.dataset.itemId = itemId;
            this.blockConfirmed.appendChild(blockEl);
        }
        return blockEl;
    }

    /**
     * Stores item configuration form and current form state
     *
     * @param listType scope name
     * @param itemId
     * @param HTMLDivElement|string blockValue
     * @return HTMLDivElement
     */
    _setConfirmedBlock(listType, itemId, blockValue) {
        const blockEl = this._getConfirmedBlock(listType, itemId);

        // Create temporary form to ensure values are cleared
        const formEl = document.createElement('form');

        let blockHtml = '';
        if (blockValue instanceof Element) {
            formEl.innerHTML = blockValue.innerHTML;
            blockEl.formData = blockValue.closest('form')
                ? new FormData(blockValue.closest('form'))
                : new FormData(formEl);
            blockEl.restorePhase = true;
        } else if (typeof blockValue === 'string') {
            formEl.innerHTML = blockValue;
            blockEl.formData = new FormData(formEl);
        } else {
            throw new TypeError('blockValue must be of type Element or String');
        }

        // Reset form state and clear checked attributes
        formEl.reset();
        formEl.querySelectorAll('[selected]').forEach((el) => el.removeAttribute('selected'));
        formEl.querySelectorAll(':checked').forEach((el) => el.removeAttribute('checked'));

        // Store the template and restore state
        blockEl.innerHTML = formEl.innerHTML;
        this._restoreFormData(blockEl, blockEl.formData);

        return blockEl;
    }

    /**
     * Remove all confimred blocks by list type
     *
     * @param listType scope name
     */
    _removeConfirmedByListType(listType) {
        for (const blockEl of this.blockConfirmed.querySelectorAll(`[data-list-type="${listType}"]`)) {
            blockEl.remove();
        }
    }

    /**
     * Checks whether item has some configuration fields
     *
     * @param listType scope name
     * @param itemId
     * @return bool
     */
    itemConfigured(listType, itemId) {
        return typeof this._getConfirmedBlock(listType, itemId).formData !== 'undefined';
    }

    /**
     * Show configuration fields of item, if it not found then get it through AJAX
     *
     * @param listType scope name
     * @param itemId
     */
    async showItemConfiguration(listType, itemId) {
        if (!listType || !itemId) {
            return false;
        }

        if (!this.itemConfigured(listType, itemId)) {
            await this._requestItemConfiguration(listType, itemId);
        }

        this.current = { listType, itemId };
        this.confirmedCurrentId = this._getConfirmedBlockId(listType, itemId);
        this._showWindow();
    }

    /**
     * Fetch configuration form for product and store in product_composite_configure_confirmed element
     *
     * @param listType scope name
     * @param itemId
     */
    async _requestItemConfiguration(listType, itemId) {
        try {
            const url = this.listTypes[listType].urlFetch;
            if (!url) {
                throw new MahoError('Product configuration form request URL not specified.');
            }
            const blockHtml = await mahoFetch(url, {
                method: 'POST',
                body: new URLSearchParams({ id: itemId }),
            });

            this._setConfirmedBlock(listType, itemId, blockHtml);

        } catch (error) {
            console.error(error);
            setMessagesDiv(error.message, 'error');
        }
    }

    /**
     * Show configuration window
     */
    _showWindow() {
        this.window = Dialog.confirm(null, {
            title: Translator.translate('Configure Product'),
            ok: this.onConfirmBtn.bind(this),
            cancel: this.onCancelBtn.bind(this),
        });

        this.window.querySelector('.dialog-content').appendChild(this.blockForm);
        // this.varienForm.validator.reset();

        this._initWindowElements();
        this._processFieldsData('item_restore');

        if (typeof this.showWindowCallback[this.current.listType] === 'function') {
            this.showWindowCallback[this.current.listType]();
        }
    }

    /**
     * Close configuration window
     */
    _closeWindow() {
        this.blockContainer.appendChild(this.blockForm);
        this.clean('window');
    }

    /**
     * Triggered on confirm button click
     * Do submit configured data through iFrame if needed
     */
    onConfirmBtn() {
        if (!this.varienForm.validate()) {
            return false;
        }

        // This saves form via AJAX
        if (this.listTypes[this.current.listType].urlConfirm) {
            return this.submit();
        }

        this._processFieldsData('item_confirm');
        if (typeof this.confirmCallback[this.current.listType] === 'function') {
            this.confirmCallback[this.current.listType]();
        }

        this._closeWindow();
        return this;
    }

    /**
     * Triggered on cancel button click
     */
    onCancelBtn() {
        if (typeof this.cancelCallback[this.current.listType] === 'function') {
            this.cancelCallback[this.current.listType]();
        }
        this._closeWindow();
        return this;
    }

    /**
     * Submit configured data through AJAX
     *
     * @param listType scope name
     */
    async submit(listType) {
        if (listType) {
            this.current = { listType, itemId: null };
        }

        // Two methods of configuration submission:
        // 1) urlConfirm - submits a single product via AJAX, thrown errors will prevent dialog from closing.
        //    For example: configuring a product from the adminhtml customer cart and wishlist tabs
        // 2) urlSubmit - copy + rename all confirmed blocks of listType into form and submit via AJAX
        //    For example: configuring multiple listTypes on adminhtml sales_order_create page
        const { urlConfirm, urlSubmit } = this.listTypes[this.current.listType];

        try {
            const submitForm = async (url, extraFormData = null) => {
                if (typeof this.beforeSubmitCallback[this.current.listType] === 'function') {
                    this.beforeSubmitCallback[this.current.listType]();
                }
                const formData = new FormData(this.blockForm);
                for (const [ name, value ] of Object.entries(extraFormData ?? {})) {
                    formData.append(name, value);
                }
                const result = await mahoFetch(url, {
                    method: 'POST',
                    body: formData,
                });
                this.clean('current');

                if (typeof this.onLoadIFrameCallback[this.current.listType] === 'function') {
                    this.onLoadIFrameCallback[this.current.listType](result);
                }
                return true;
            }

            if (urlConfirm) {
                return await submitForm(setRouteParams(urlConfirm, { id: this.current.itemId }));
            }
            if (urlSubmit) {
                // Clear out product_composite_configure_form_fields
                this.blockFormFields.textContent = '';

                // Sales order create
                this._processFieldsData('current_confirmed_to_form');

                // Disable item controls that duplicate added fields (e.g. sometimes qty controls can intersect)
                // so they won't be submitted
                for (const element of this.blockFormConfirmed.querySelectorAll('input[name], select[name], textarea[name]')) {
                    element.dataset.disabled = element.disabled;
                    if (this.blockFormAdd.querySelector(`[name="${element.name}"]`)) {
                        element.disabled = true;
                    }
                }
                const extraFormData = new FormData();
                const complexTypes = this.listTypes[this.current.listType].complexTypes;
                if (complexTypes) {
                    extraFormData.append('configure_complex_list_types', complexTypes.join(','));
                }

                const result = await submitForm(urlSubmit, extraFormData);

                // Re-enable item controls
                for (const element of this.blockFormConfirmed.querySelectorAll('input[name], select[name], textarea[name]')) {
                    element.disabled = element.dataset.disabled;
                    delete element.dataset.disabled;
                }

                if (this.compatibilityFlags.restoreFormToConfirmedAfterSubmit === true) {
                    this._processFieldsData('form_confirmed_to_confirmed');
                }

                return result;
            }

            throw new MahoError(
                'Product configuration form error: %s',
                Translator.translate('Save URL not specified. Product configuration will not be saved. Press Cancel to exit.'),
            );
        } catch (error) {
            setMessagesDiv(error.message, 'error', urlConfirm ? this.blockMsg : null);
            return false;
        }
    }

    /**
     * Add dynamically additional fields for form
     *
     * @param fields
     */
    addFields(fields) {
        this.blockFormAdd.append(...fields);
        return this;
    }

    /**
     * Helper to find qty of currently confirmed item
     */
    getCurrentConfirmedBlock() {
        const { listType, itemId } = this.current;
        return this._getConfirmedBlock(listType, itemId);
    }

    /**
     * Helper to find qty of currently confirmed item
     */
    getCurrentConfirmedQtyElement() {
        return this.getCurrentConfirmedBlock().querySelector('input[name=qty]');
    }

    /**
     * Helper to find qty of active form
     */
    getCurrentFormQtyElement() {
        return this.blockFormFields.querySelector('input[name=qty]');
    }

    /**
     * Attach callback function triggered when confirm button was clicked
     *
     * @param confirmCallback
     */
    setConfirmCallback(listType, confirmCallback) {
        this.confirmCallback[listType] = confirmCallback;
        return this;
    }

    /**
     * Attach callback function triggered when cancel button was clicked
     *
     * @param cancelCallback
     */
    setCancelCallback(listType, cancelCallback) {
        this.cancelCallback[listType] = cancelCallback;
        return this;
    }

    /**
     * Attach callback function triggered when iFrame was loaded
     *
     * @param onLoadIFrameCallback
     */
    setOnLoadIFrameCallback(listType, onLoadIFrameCallback) {
        this.onLoadIFrameCallback[listType] = onLoadIFrameCallback;
        return this;
    }

    /**
     * Attach callback function triggered when iFrame was loaded
     *
     * @param showWindowCallback
     */
    setShowWindowCallback(listType, showWindowCallback) {
        this.showWindowCallback[listType] = showWindowCallback;
        return this;
    }

    /**
     * Attach callback function triggered before submitting form
     *
     * @param beforeSubmitCallback
     */
    setBeforeSubmitCallback(listType, beforeSubmitCallback) {
        this.beforeSubmitCallback[listType] = beforeSubmitCallback;
        return this;
    }

    /**
     * Clean confirmed blocks and form fields
     *
     * Method can be one of:
     *  - 'window': clear current dialog product and error message
     *  - 'current': clear confirmed block types with current product's listType
     *  - 'all'|false: clear all confirmed blocks and form fields
     *  - listType: clear all confurmed blocks by listType
     *
     * Additionally: form additional and form confirmed blocks will always be cleared
     *
     * @param {string} method
     */
    clean(method) {
        if (method === 'window') {
            this.blockFormFields.textContent = '';
            this.blockMsg.textContent = '';
        } else if (method === 'current') {
            this._removeConfirmedByListType(this.current.listType);
            for (const listType of this.listTypes[this.current.listType]?.complexTypes ?? []) {
                this._removeConfirmedByListType(listType);
            }
        } else if (this.listTypes[method]) {
            this._removeConfirmedByListType(method);
            for (const listType of this.listTypes[method]?.complexTypes ?? []) {
                this._removeConfirmedByListType(listType);
            }
        } else if (method === 'all' || !method) {
            this.current = {}
            this.blockConfirmed.textContent = '';
            this.blockFormFields.textContent = '';
            this.blockMsg.textContent = '';
        }
        this.blockFormAdd.textContent = '';
        this.blockFormConfirmed.textContent = '';
        this.blockForm.action = '';
        return this;
    }

    /**
     * Hook for product form to instantiate Product.Config() class with config
     */
    registerConfigurableFieldset(config) {
        ProductConfigure.spConfig = new Product.Config({
            containerId: this.blockFormFields.id,
            inputsInitialized: this.restorePhase ? true : null,
            ...config,
        });
    }

    /**
     * Process fields data: save, restore, move saved to form and back
     *
     * @param method can be 'item_confirm', 'item_restore', 'current_confirmed_to_form', 'form_confirmed_to_confirmed'
     */
    _processFieldsData(method) {
        const { listType, itemId } = this.current;
        const listInfo = this.listTypes[listType];

        // Confirm dialog product block to confirmed block container and preserve state
        if (method === 'item_confirm') {
            const blockEl = this._setConfirmedBlock(listType, itemId, this.blockFormFields.firstElementChild);
        }

        // Restore dialog product block from confirmed block container and restore state
        if (method === 'item_restore') {
            const blockEl = this._getConfirmedBlock(listType, itemId);
            this.blockFormFields.replaceChildren(blockEl.cloneNode(true));

            if (blockEl.restorePhase) {
                this.restorePhase = true;
            }

            this._restoreFormData(this.blockForm, blockEl.formData);

            for (const oldScriptEl of this.blockFormFields.querySelectorAll('script:not([src])')) {
                const newScriptEl = document.createElement('script');
                for (const attr of oldScriptEl.attributes) {
                    newScriptEl.setAttribute(attr.name, attr.value)
                }
                newScriptEl.appendChild(document.createTextNode(oldScriptEl.innerHTML));
                oldScriptEl.replaceWith(newScriptEl);
            }
            this.restorePhase = false;
        }

        // Copy + rename all confirmed blocks of listType into form
        if (method === 'current_confirmed_to_form') {
            const allowedListTypes = {
                [this.current.listType]: true,
            };
            for (let complexType of listInfo.complexTypes ?? []) {
                allowedListTypes[complexType] = true;
            }

            // Clear div element
            this.blockFormConfirmed.replaceChildren();

            for (const blockConfirmed of this.blockConfirmed.children) {
                const { listType, itemId } = blockConfirmed.dataset;

                if (!allowedListTypes[listType]) {
                    continue;
                }
                if (this.itemsFilter[listType] && !this.itemsFilter[listType].includes(itemId)) {
                    continue;
                }

                const blockFormConfirmed = this.blockFormConfirmed.appendChild(blockConfirmed.cloneNode(true));
                this._restoreFormData(blockFormConfirmed, blockConfirmed.formData ?? new FormData());
                this._renameFields(method, blockFormConfirmed, listInfo.complexTypes ? true : false);
            }

        }

        // Restore confirmed block from submitted form
        if (method === 'form_confirmed_to_confirmed') {
            for (const blockFormConfirmed of this.blockFormConfirmed.children) {
                const { listType, itemId } = blockFormConfirmed.dataset;
                this._renameFields(method, blockFormConfirmed, listInfo.complexTypes ? true : false);
                this._setConfirmedBlock(listType, itemId, blockFormConfirmed);
            }
        }
    }

    /**
     * Internal function for convert input names for form submission
     *
     * Method can be one of:
     *  - 'current_confirmed_to_form': convert confirmed block inputs by prefixing listType and itemId to name
     *         For example: name="qty" -> name="item[42][qty]" or name="list[quote_items][item][42][qty]"
     *  - 'form_confirmed_to_confirmed': convert submitted form inputs back by removing name prefixes
     *         For example: name="item[42][qty]" -> name="qty"
     *
     * Note: File inputs will use underscores instead of square bracketsbe flat renamed to `list_${listType}_item_${item}`
     *     For example: name="file" -> name="item_42_file" or name="list_quote_items_item_42_file"
     *
     * @param method can be 'current_confirmed_to_form', 'form_confirmed_to_confirmed'
     * @param blockItem
     * @param usePrefix - include listType in input name
     */
    _renameFields(method, blockItem, usePrefix = false) {
        const { listType, itemId } = blockItem.dataset;

        let pattern, patternFlat;
        let replacement, replacementFlat;
        if (method == 'current_confirmed_to_form') {
            // Replace name="qty" -> name="item[42][qty]" or name="list[quote_items][item][42][qty]"
            pattern = /(\w+)(\[?)/;
            replacement = usePrefix
                ? `list[${listType}][item][${itemId}][$1]$2`
                : `item[${itemId}][$1]$2`

            // Replace name="file" -> name="item_42_file" or name="list_quote_items_item_42_file"
            patternFlat = /(\w+)/;
            replacementFlat = usePrefix
                ? `list_${listType}_item_${itemId}_$1`
                : `item_${itemId}_$1`;

        } else if (method == 'form_confirmed_to_confirmed') {
            // Replace name="item[42][qty]" or name="list[quote_items][item][42][qty]" -> name="qty"
            pattern = usePrefix
                  ? new RegExp(`item\\[${itemId}\\]\\[(\\w+)\\](.*)`)
                  : new RegExp(`list\\[${listType}\\]\\[item\\]\\[${itemId}\\]\\[(\\w+)\\](.*)`);
            replacement = '$1$2';

            // Replace name="item_42_file" or name="list_quote_items_item_42_file" -> name="file"
            patternFlat = usePrefix
                  ? new RegExp(`item_${itemId}_(\\w+)`)
                  : new RegExp(`list_${listType}_item_${itemId}_(\\w+)`);
            replacementFlat = '$1';

        } else {
            return false;
        }

        for (const inputEl of blockItem.querySelectorAll('input[name], select[name], textarea[name]')) {
            inputEl.name = inputEl.type === 'file'
                ? inputEl.name.replace(patternFlat, replacementFlat)
                : inputEl.name.replace(pattern, replacement)
        };
    }

    /**
     * Internal function to restore form values to a block
     */
    _restoreFormData(blockEl, formData) {
        for (const [ key, value ] of formData.entries()) {
            const fieldEl = blockEl.querySelector(`[name="${key}"]`);
            if (!fieldEl) {
                continue;
            }
            if (fieldEl.tagName === 'SELECT') {
                for (const option of fieldEl.options) {
                    option.selected |= option.value === value;
                }
            } else if (fieldEl.type === 'checkbox' || fieldEl.type === 'radio') {
                blockEl.querySelector(`[name="${key}"][value="${value}"`).checked = true
            } else if (fieldEl.type === 'file') {
                if (value instanceof File && value.size) {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(value);
                    fieldEl.files = dataTransfer.files;
                }
            } else {
                fieldEl.value = value;
            }
        }
    }

    /**
     * Check if qty selected correctly
     *
     * @param object element
     * @param object event
     */
    changeOptionQty(element, event) {
        const checkQty = event?.keyCode !== 8 && event?.keyCode !== 46;
        const value = Number(element.value);
        if (checkQty && value <= 0 || isNaN(value)) {
            element.value = 1;
        }
    }
};
