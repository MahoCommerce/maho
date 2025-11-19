/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

var Product = Product ?? {};

Product.Gallery = class {
    constructor(containerId, imageTypes) {
        this.images = [];
        this.file2id = {
            'no_selection': 0
        };
        this.idIncrement = 1;
        this.containerId = containerId;
        this.container = document.getElementById(this.containerId);
        this.imageTypes = imageTypes;

        document.addEventListener('uploader:fileSuccess', (event) => {
            const memo = event.detail;
            if (memo && this._checkCurrentContainer(memo.containerId)) {
                this.handleUploadComplete([{response: memo.response}]);
            }
        });

        this.images = JSON.parse(this.getElement('save').value);
        this.imagesValues = JSON.parse(this.getElement('save_image').value);
        this.template = (vars) => {
            let html = '<tr id="' + vars.id + '" class="preview">' + this.getElement('template').innerHTML + '</tr>';
            Object.entries(vars).forEach(([key, value]) => {
                const regex = new RegExp('__' + key + '__', 'g');
                html = html.replace(regex, value);
            });
            return html;
        };
        this.fixParentTable();
        this.updateImages();
        varienGlobalEvents.attachEventHandler('moveTab', this.onImageTabMove.bind(this));
    }
    _checkCurrentContainer(child) {
        return document.getElementById(this.containerId).querySelector('#' + child);
    }
    onImageTabMove(event) {
        var imagesTab = false;
        let parentItem = this.container.parentElement;
        while (parentItem) {
            if (parentItem.tabObject) {
                imagesTab = parentItem.tabObject;
                break;
            }
            parentItem = parentItem.parentElement;
        }

        if (imagesTab && event.tab && event.tab.name && imagesTab.name == event.tab.name) {
            this.container.querySelectorAll('input[type="radio"]').forEach(function (radio) {
                radio.addEventListener('change', this.onChangeRadio);
            }.bind(this));
            this.updateImages();
        }

    }
    fixParentTable() {
        let parentItem = this.container.parentElement;
        while (parentItem) {
            if (parentItem.tagName.toLowerCase() == 'td') {
                parentItem.style.width = '100%';
            }
            if (parentItem.tagName.toLowerCase() == 'table') {
                parentItem.style.width = '100%';
                break;
            }
            parentItem = parentItem.parentElement;
        }
    }
    getElement(name) {
        return document.getElementById(this.containerId + '_' + name);
    }
    showUploader() {
        this.getElement('add_images_button').style.display = 'none';
        this.getElement('uploader').style.display = '';
    }
    handleUploadComplete(files) {
        files.forEach(function (item) {
            var response = item.response;
            var newImage = {};
            newImage.url = response.url;
            newImage.file = response.file;
            newImage.label = '';
            newImage.position = this.getNextPosition();
            newImage.disabled = 0;
            newImage.removed = 0;
            this.images.push(newImage);
        }.bind(this));
        this.container.setHasChanges();
        this.updateImages();
    }
    updateImages() {
        this.getElement('save').value = JSON.stringify(this.images);
        Object.entries(this.imageTypes).forEach(function (pair) {
            this.getFileElement('no_selection', 'cell-' + pair[0] + ' input').checked = true;
        }.bind(this));
        this.images.forEach(function (row) {
            if (!document.getElementById(this.prepareId(row.file))) {
                this.createImageRow(row);
            }
            this.updateVisualisation(row.file);
        }.bind(this));
        this.updateUseDefault(false);
    }
    onChangeRadio(evt) {
        var element = evt.target;
        element.setHasChanges();
    }
    createImageRow(image) {
        var vars = Object.assign({}, image);
        vars.id = this.prepareId(image.file);
        var html = this.template(vars);
        this.getElement('list').insertAdjacentHTML('beforeend', html);

        document.getElementById(vars.id).querySelectorAll('input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', this.onChangeRadio);
        }.bind(this));
    }
    prepareId(file) {
        if (typeof this.file2id[file] == 'undefined') {
            this.file2id[file] = this.idIncrement++;
        }
        return this.containerId + '-image-' + this.file2id[file];
    }
    getNextPosition() {
        var maxPosition = 0;
        this.images.forEach(function (item) {
            if (parseInt(item.position) > maxPosition) {
                maxPosition = parseInt(item.position);
            }
        });
        return maxPosition + 1;
    }
    updateImage(file) {
        var index = this.getIndexByFile(file);

        const use_default_label = document.getElementById("use_default_label");
        const use_default_position = document.getElementById("use_default_position");

        if (use_default_label && use_default_label.checked) {
            this.images[index].label = null;
            this.images[index].label_use_default = true;
        } else {
            this.images[index].label = this.getFileElement(file, 'cell-label input').value;
            this.images[index].label_use_default = false;
        }

        if (use_default_position && use_default_position.checked) {
            this.images[index].position = null;
            this.images[index].position_use_default = true;
        } else {
            this.images[index].position = this.getFileElement(file, 'cell-position input').value;
            this.images[index].position_use_default = false;
        }

        this.images[index].removed = (this.getFileElement(file, 'cell-remove input').checked ? 1 : 0);
        this.images[index].disabled = (this.getFileElement(file, 'cell-disable input').checked ? 1 : 0);
        this.getElement('save').value = JSON.stringify(this.images);
        this.updateState(file);
        this.container.setHasChanges();
    }
    loadImage(file) {
        var image = this.getImageByFile(file);
        this.getFileElement(file, 'cell-image img').src = image.url;
        this.getFileElement(file, 'cell-image img').style.display = '';
        this.getFileElement(file, 'cell-image .place-holder').style.display = 'none';
    }
    setProductImages(file) {
        Object.entries(this.imageTypes)
            .forEach(
                function (pair) {
                    if (this.getFileElement(file,
                        'cell-' + pair[0] + ' input').checked) {
                        this.imagesValues[pair[0]] = (file == 'no_selection' ? null
                            : file);
                    }
                }.bind(this));

        this.getElement('save_image').value = JSON.stringify(this.imagesValues);
    }
    updateVisualisation(file) {
        var image = this.getImageByFile(file);

        const use_default_label = document.getElementById("use_default_label");
        const use_default_position = document.getElementById("use_default_position");

        if(use_default_label && use_default_label.checked) {
            this.getFileElement(file, 'cell-label input').value = image.label_default;
        } else {
            this.getFileElement(file, 'cell-label input').value = image.label;
        }

        if(use_default_position && use_default_position.checked) {
            this.getFileElement(file, 'cell-position input').value = image.position_default;
        } else {
            this.getFileElement(file, 'cell-position input').value = image.position;
        }

        this.getFileElement(file, 'cell-remove input').checked = (image.removed == 1);
        this.getFileElement(file, 'cell-disable input').checked = (image.disabled == 1);
        Object.entries(this.imageTypes).forEach(function (pair) {
            if (this.imagesValues[pair[0]] == file) {
                this.getFileElement(file, 'cell-' + pair[0] + ' input').checked = true;
            }
        }.bind(this));
        this.updateState(file);
    }
    updateState(file) {
        // deprecated
    }
    getFileElement(file, element) {
        var selector = '#' + this.prepareId(file) + ' .' + element;
        var elems = document.querySelectorAll(selector);
        if (!elems[0]) {
            try {
                console.log(selector);
            } catch (e2) {
                alert(selector);
            }
        }

        return elems[0];
    }
    getImageByFile(file) {
        if (this.getIndexByFile(file) === null) {
            return false;
        }

        return this.images[this.getIndexByFile(file)];
    }
    getIndexByFile(file) {
        var index;
        this.images.forEach(function (item, i) {
            if (item.file == file) {
                index = i;
            }
        });
        return index;
    }
    updateUseDefault(el) {
        var inputs = document.querySelectorAll('#' + this.containerId + '_default td input');
        for (var i=0; i<inputs.length; i++) {
            var input = inputs[i];
            var radios = document.querySelectorAll('#' + this.containerId + '_list .preview .cell-' + input.value + ' input');
            for (var j=0; j<radios.length; j++) {
                var radio = radios[j];
                radio.disabled = input.checked;
            }
        }

        if (typeof el == "object" && el.id) {
            this.images.forEach(function (row) {
                this.updateImage(row.file);
            }.bind(this));
        }

        if (arguments.length == 0) {
            this.container.setHasChanges();
        }
    }
    handleUploadProgress(file) {
    }
    handleUploadError(fileId) {
    }
};

Product.Configurable = class {
    constructor(attributes, links, idPrefix, grid, readonly) {
        this.templatesSyntax = new RegExp('(^|.|\\r|\\n)(\'{{\\s*(\\w+)\\s*}}\')', "");
        this.attributes = attributes; // Attributes
        this.idPrefix = idPrefix; // Container id prefix
        this.links = new Map(Object.entries(links)); // Associated products
        this.newProducts = []; // For product that's created through Create
        // Empty and Copy from Configurable
        this.readonly = readonly;

        /* Generation templates */
        this.addAttributeTemplate = this.createTemplateFunction(
            document.getElementById(idPrefix + 'attribute_template').innerHTML.replace(/__id__/g,
            "'{{html_id}}'").replace(/ template no-display/g, ''));
        this.addValueTemplate = this.createTemplateFunction(
            document.getElementById(idPrefix + 'value_template').innerHTML.replace(/__id__/g,
            "'{{html_id}}'").replace(/ template no-display/g, ''));
        this.pricingValueTemplate = this.createTemplateFunction(document.getElementById(idPrefix + 'simple_pricing').innerHTML);
        this.pricingValueViewTemplate = this.createTemplateFunction(document.getElementById(idPrefix + 'simple_pricing_view').innerHTML);

        this.container = document.getElementById(idPrefix + 'attributes');

        /* Listeners */
        this.onLabelUpdate = this.updateLabel.bind(this); // Update
        // attribute
        // label
        this.onValuePriceUpdate = this.updateValuePrice
            .bind(this); // Update pricing value
        this.onValueTypeUpdate = this.updateValueType.bind(this); // Update
        // pricing
        // type
        this.onValueDefaultUpdate = this.updateValueUseDefault
            .bind(this);

        /* Grid initialization and attributes initialization */
        this.createAttributes(); // Creation of default attributes

        this.grid = grid;
        this.grid.rowClickCallback = this.rowClick.bind(this);
        this.grid.initRowCallback = this.rowInit.bind(this);
        this.grid.checkboxCheckCallback = this.registerProduct.bind(this); // Associate/Unassociate
        // simple
        // product

        this.grid.rows.forEach(function (row) {
            this.rowInit(this.grid, row);
        }.bind(this));
    }

    createTemplateFunction(template) {
        return new Template(template, Template.HANDLEBARS_PATTERN).evaluate.bind(new Template(template, Template.HANDLEBARS_PATTERN));
    }

    createAttributes() {
        this.attributes.forEach(function (attribute, index) {
            var li = document.createElement('LI');
            li.className = 'attribute';
            li.id = this.idPrefix + '_attribute_' + index;
            attribute.html_id = li.id;
            if (attribute && attribute.label && (!attribute.label || attribute.label.trim() === '')) {
                attribute.label = '&nbsp;';
            }
            var label_readonly = '';
            var use_default_checked = '';
            if (attribute.use_default == '1' || attribute.id == null) {
                use_default_checked = ' checked="checked"';
                label_readonly = ' readonly="readonly"';
            }

            var template = this.addAttributeTemplate(attribute);
            template = template.replace(new RegExp(' readonly="label"', 'ig'), label_readonly);
            template = template.replace(new RegExp(' checked="use_default"', 'ig'), use_default_checked);
            li.innerHTML = template;
            li.attributeObject = attribute;

            this.container.appendChild(li);
            li.attributeValues = li.querySelector('.attribute-values');

            if (attribute.values) {
                attribute.values.forEach(function (value) {
                    this.createValueRow(li, value); // Add pricing values
                }.bind(this));
            }

            /* Observe label change */
            li.querySelector('.attribute-label').addEventListener('change', this.onLabelUpdate);
            li.querySelector('.attribute-label').addEventListener('keyup', this.onLabelUpdate);
            li.querySelector('.attribute-use-default-label').addEventListener('change', this.onLabelUpdate);
        }.bind(this));
        if (!this.readonly) {
            new Sortable(this.container, {
                handle: '.attribute-name-container',
                filter: '.disabled',
                animation: 150,
                onUpdate: this.updatePositions.bind(this),
            });
        }
        this.updateSaveInput();
    }

    updateLabel(event) {
        var li = event.target.closest('LI');
        var labelEl = li.querySelector('.attribute-label');
        var defEl = li.querySelector('.attribute-use-default-label');

        li.attributeObject.label = labelEl.value;
        if (defEl.checked) {
            labelEl.readOnly = true;
            li.attributeObject.use_default = 1;
        } else {
            labelEl.readOnly = false;
            li.attributeObject.use_default = 0;
        }

        this.updateSaveInput();
    }
updatePositions(param) {
        Array.from(this.container.children).forEach(function (row, index) {
            row.attributeObject.position = index;
        });
        this.updateSaveInput();
    }
addNewProduct(productId, attributes) {
        if (this.checkAttributes(attributes)) {
            this.links.set(productId, this.cloneAttributes(attributes));
        } else {
            this.newProducts.push(productId);
        }

        this.updateGrid();
        this.updateValues();
        this.grid.reload(null);
    }
createEmptyProduct() {
        this.createPopup(this.createEmptyUrl);
    }
createNewProduct() {
        this.createPopup(this.createNormalUrl);
    }
createPopup(url) {
        if (this.win && !this.win.closed) {
            this.win.close();
        }

        this.win = window.open(url, '',
            'width=1000,height=700,resizable=1,scrollbars=1');
        this.win.focus();
    }
registerProduct(grid, element, checked) {
        if (checked) {
            if (element.linkAttributes) {
                this.links.set(element.value, element.linkAttributes);
            }
        } else {
            this.links.delete(element.value);
        }
        this.updateGrid();
        this.grid.rows.forEach(function (row) {
            this.revalidateRow(this.grid, row);
        }.bind(this));
        this.updateValues();
    }
updateProduct(productId, attributes) {
        var isAssociated = false;

        if (typeof this.links.get(productId) != 'undefined') {
            isAssociated = true;
            this.links.delete(productId);
        }

        if (isAssociated && this.checkAttributes(attributes)) {
            this.links.set(productId, this.cloneAttributes(attributes));
        } else if (isAssociated) {
            this.newProducts.push(productId);
        }

        this.updateGrid();
        this.updateValues();
        this.grid.reload(null);
    }
cloneAttributes(attributes) {
        var newObj = [];
        for (var i = 0, length = attributes.length; i < length; i++) {
            newObj[i] = Object.assign({}, attributes[i]);
        }
        return newObj;
    }
rowClick(grid, event) {
        var trElement = event.target.closest('tr');
        var isInput = event.target.tagName.toUpperCase() == 'INPUT';

        if (event.target.closest('td').querySelector('a')) {
            return;
        }

        if (trElement) {
            var checkbox = trElement.querySelector('input');
            if (checkbox && !checkbox.disabled) {
                var checked = isInput ? checkbox.checked : !checkbox.checked;
                grid.setCheckboxChecked(checkbox, checked);
            }
        }
    }
rowInit(grid, row) {
        var checkbox = row.querySelector('.checkbox');
        var input = row.querySelector('.value-json');
        if (checkbox && input) {
            checkbox.linkAttributes = JSON.parse(input.value);
            if (!checkbox.checked) {
                if (!this.checkAttributes(checkbox.linkAttributes)) {
                    row.classList.add('invalid');
                    checkbox.disabled = true;
                } else {
                    row.classList.remove('invalid');
                    checkbox.disabled = false;
                }
            }
        }
    }
revalidateRow(grid, row) {
        var checkbox = row.querySelector('.checkbox');
        if (checkbox) {
            if (!checkbox.checked) {
                if (!this.checkAttributes(checkbox.linkAttributes)) {
                    row.classList.add('invalid');
                    checkbox.disabled = true;
                } else {
                    row.classList.remove('invalid');
                    checkbox.disabled = false;
                }
            }
        }
    }
checkAttributes(attributes) {
        var result = true;
        this.links.forEach(function (value, key) {
            var fail = false;
            for (var i = 0; i < value.length && !fail; i++) {
                for (var j = 0; j < attributes.length && !fail; j++) {
                    if (value[i].attribute_id == attributes[j].attribute_id
                        && value[i].value_index != attributes[j].value_index) {
                        fail = true;
                    }
                }
            }
            if (!fail) {
                result = false;
            }
        });
        return result;
    }
updateGrid() {
        this.grid.reloadParams = {
            'products[]': this.links.size ? Array.from(this.links.keys()) : [0],
            'new_products[]': this.newProducts
        };
    }
updateValues() {
        var uniqueAttributeValues = new Map();
        /* Collect unique attributes */
        this.links.forEach(function (value, key) {
            for (var i = 0, length = value.length; i < length; i++) {
                var attribute = value[i];
                if (!uniqueAttributeValues.has(attribute.attribute_id)) {
                    uniqueAttributeValues.set(attribute.attribute_id, new Map());
                }
                uniqueAttributeValues.get(attribute.attribute_id).set(
                    attribute.value_index, attribute);
            }
        });
        /* Updating attributes value container */
        Array.from(this.container.children)
            .forEach(
                function (row) {
                    var attribute = row.attributeObject;
                    // Instead of removing unused attribute values, only add new ones
                    // that are used by associated products but not yet displayed
                    if (uniqueAttributeValues.get(attribute.attribute_id)) {
                        uniqueAttributeValues.get(attribute.attribute_id).forEach(
                            function (value, key) {
                                // Check if this value is already in the attribute values
                                var valueExists = false;
                                for (var i = 0; i < attribute.values.length; i++) {
                                    if (attribute.values[i] && attribute.values[i].value_index == value.value_index) {
                                        valueExists = true;
                                        break;
                                    }
                                }
                                // Only add if it doesn't exist yet
                                if (!valueExists) {
                                    attribute.values.push(value);
                                    this.createValueRow(row, value);
                                }
                            }.bind(this));
                    }
                }.bind(this));
        this.updateSaveInput();
        this.updateSimpleForm();
    }
createValueRow(container, value) {
        var templateVariables = new Map();
        if (!this.valueAutoIndex) {
            this.valueAutoIndex = 1;
        }
        templateVariables.set('html_id', container.id + '_'
            + this.valueAutoIndex);
        Object.entries(value).forEach(([key, val]) => {
            templateVariables.set(key, val);
        });
        var pricingValue = parseFloat(templateVariables.get('pricing_value'));
        if (!isNaN(pricingValue)) {
            templateVariables.set('pricing_value', pricingValue);
        } else {
            templateVariables.delete('pricing_value');
        }
        this.valueAutoIndex++;

        var li = document.createElement('LI');
        li.className = 'attribute-value';
        li.id = templateVariables.get('html_id');
        li.innerHTML = this.addValueTemplate(Object.fromEntries(templateVariables));
        li.valueObject = value;
        if (typeof li.valueObject.is_percent == 'undefined') {
            li.valueObject.is_percent = 0;
        }

        if (typeof li.valueObject.pricing_value == 'undefined') {
            li.valueObject.pricing_value = '';
        }

        container.attributeValues.appendChild(li);

        var priceField = li.querySelector('.attribute-price');
        var priceTypeField = li.querySelector('.attribute-price-type');

        if (priceTypeField != undefined && priceTypeField.options != undefined) {
            if (parseInt(value.is_percent)) {
                priceTypeField.options[1].selected = !(priceTypeField.options[0].selected = false);
            } else {
                priceTypeField.options[1].selected = !(priceTypeField.options[0].selected = true);
            }
        }

        priceField.addEventListener('keyup', this.onValuePriceUpdate);
        priceField.addEventListener('change', this.onValuePriceUpdate);
        priceTypeField.addEventListener('change', this.onValueTypeUpdate);
        var useDefaultEl = li.querySelector('.attribute-use-default-value');
        if (useDefaultEl) {
            if (li.valueObject.use_default_value) {
                useDefaultEl.checked = true;
                this.updateUseDefaultRow(useDefaultEl, li);
            }
            useDefaultEl.addEventListener('change', this.onValueDefaultUpdate);
        }
    }
updateValuePrice(event) {
        var li = event.target.closest('LI');
        li.valueObject.pricing_value = (event.target.value.trim() === '' ? null
            : event.target.value);
        this.updateSimpleForm();
        this.updateSaveInput();
    }
updateValueType(event) {
        var li = event.target.closest('LI');
        li.valueObject.is_percent = (event.target.value.trim() === '' ? null
            : event.target.value);
        this.updateSimpleForm();
        this.updateSaveInput();
    }
updateValueUseDefault(event) {
        var li = event.target.closest('LI');
        var useDefaultEl = event.target;
        li.valueObject.use_default_value = useDefaultEl.checked;
        this.updateUseDefaultRow(useDefaultEl, li);
    }
updateUseDefaultRow(useDefaultEl, li) {
        var priceField = li.querySelector('.attribute-price');
        var priceTypeField = li.querySelector('.attribute-price-type');
        if (useDefaultEl.checked) {
            priceField.disabled = true;
            priceTypeField.disabled = true;
        } else {
            priceField.disabled = false;
            priceTypeField.disabled = false;
        }
        this.updateSimpleForm();
        this.updateSaveInput();
    }
updateSaveInput() {
        var oldSaveAttributesValue = document.getElementById(this.idPrefix + 'save_attributes').value;
        var oldSaveLinksValue = document.getElementById(this.idPrefix + 'save_links').value;
        var newSaveAttributesValue = JSON.stringify(this.attributes);
        var newSaveLinksValue = JSON.stringify(Object.fromEntries(this.links));
        document.getElementById(this.idPrefix + 'save_attributes').value = newSaveAttributesValue;
        document.getElementById(this.idPrefix + 'save_links').value = newSaveLinksValue;
        if (oldSaveAttributesValue != newSaveAttributesValue || oldSaveLinksValue != newSaveLinksValue) {
            try {
                document.getElementById('configurable_save_attributes').setHasChanges();
            } catch (e) {}
        }
    }
initializeAdvicesForSimpleForm() {
        if (document.getElementById(this.idPrefix + 'simple_form').advicesInited) {
            return;
        }

        document.getElementById(this.idPrefix + 'simple_form').querySelectorAll('td.value').forEach(function (td) {
            var adviceContainer = document.createElement('div');
            td.appendChild(adviceContainer);
            td.querySelectorAll('input, select').forEach(function (element) {
                element.advaiceContainer = adviceContainer;
            });
        });
        document.getElementById(this.idPrefix + 'simple_form').advicesInited = true;
    }
quickCreateNewProduct() {
        this.initializeAdvicesForSimpleForm();
        document.getElementById(this.idPrefix + 'simple_form').classList.remove('ignore-validate');
        var validationElements = Array.from(document.getElementById(this.idPrefix + 'simple_form').querySelectorAll('input, select, textarea'));
        var validationResults = validationElements.map(function (elm) {
            return Validation.validate(elm, {
                useTitle: false,
                onElementValidate: function () {
                }
            });
        });
        var validationResult = validationResults.every(Boolean);
        document.getElementById(this.idPrefix + 'simple_form').classList.add('ignore-validate');

        if (!validationResult) {
            return;
        }

        const formElements = document.getElementById(this.idPrefix + 'simple_form').querySelectorAll('input, select, textarea');
        const formData = new FormData();

        formElements.forEach(element => {
            if (element.name && element.value) {
                formData.append(element.name, element.value);
            }
        });
        formData.append('form_key', FORM_KEY);

        document.getElementById('messages').innerHTML = '';

        mahoFetch(this.createQuickUrl, {
            method: 'POST',
            body: formData,
            loaderArea: document.getElementById(this.idPrefix + 'simple_form')
        })
        .then(response => {
            this.quickCreateNewProductComplete({responseText: JSON.stringify(response)});
        })
        .catch(error => {
            console.error('Quick create product error:', error);
        });
    }
quickCreateNewProductComplete(transport) {
        var result = JSON.parse(transport.responseText);

        if (result.error) {
            if (result.error.fields) {
                document.getElementById(this.idPrefix + 'simple_form').classList.remove('ignore-validate');
                Object.entries(result.error.fields).forEach(([key, value]) => {
                    document.getElementById('simple_product_' + key).value = value;
                    document.getElementById('simple_product_' + key + '_autogenerate').checked = false;
                    const autogenerateEl = document.getElementById('simple_product_' + key + '_autogenerate');
                    toggleValueElements(autogenerateEl, autogenerateEl.parentNode);
                    Validation.ajaxError(document.getElementById('simple_product_' + key), result.error.message);
                });
                document.getElementById(this.idPrefix + 'simple_form').classList.add('ignore-validate');
            } else {
                if (result.error.message) {
                    alert(result.error.message);
                } else {
                    alert(result.error);
                }
            }
            return;
        } else if (result.messages) {
            document.getElementById('messages').innerHTML = result.messages;
        }

        result.attributes.forEach(function (attribute) {
                var attr = this.getAttributeById(attribute.attribute_id);
                if (!this.getValueByIndex(attr, attribute.value_index)
                    && result.pricing
                    && result.pricing[attr.attribute_code]) {

                    attribute.is_percent = result.pricing[attr.attribute_code].is_percent;
                    attribute.pricing_value = (result.pricing[attr.attribute_code].value == null ? ''
                        : result.pricing[attr.attribute_code].value);
                }
            }.bind(this));

        this.attributes.forEach(function (attribute) {
            const simpleProductEl = document.getElementById('simple_product_' + attribute.attribute_code);
            if (simpleProductEl) {
                simpleProductEl.value = '';
            }
        }.bind(this));

        this.links.set(result.product_id, result.attributes);
        this.updateGrid();
        this.updateValues();
        this.grid.reload();
    }
checkCreationUniqueAttributes() {
        var attributes = [];
        this.attributes
            .forEach(function (attribute) {
                attributes.push({
                    attribute_id: attribute.attribute_id,
                    value_index: document.getElementById('simple_product_' + attribute.attribute_code).value
                });
            }.bind(this));

        return this.checkAttributes(attributes);
    }
getAttributeByCode(attributeCode) {
        var attribute = null;
        for (let item of this.attributes) {
            if (item.attribute_code == attributeCode) {
                attribute = item;
                break;
            }
        }
        return attribute;
    }
getAttributeById(attributeId) {
        var attribute = null;
        for (let item of this.attributes) {
            if (item.attribute_id == attributeId) {
                attribute = item;
                break;
            }
        }
        return attribute;
    }
getValueByIndex(attribute, valueIndex) {
        var result = null;
        for (let value of attribute.values) {
            if (value.value_index == valueIndex) {
                result = value;
                break;
            }
        }
        return result;
    }
showPricing(select, attributeCode) {
        var attribute = this.getAttributeByCode(attributeCode);
        if (!attribute) {
            return;
        }

        select = typeof select === 'string' ? document.getElementById(select) : select;
        if (select.value && !document.getElementById('simple_product_' + attributeCode + '_pricing_container')) {
            select.insertAdjacentHTML('afterend', '<div class="left"></div> <div id="simple_product_' + attributeCode + '_pricing_container" class="left"></div>');
            var newContainer = select.nextElementSibling;
            select.parentNode.removeChild(select);
            newContainer.appendChild(select);
            // Fix visualization bug
            document.getElementById(this.idPrefix + 'simple_form').querySelector('.form-list').style.width = '100%';
        }

        var container = document.getElementById('simple_product_' + attributeCode + '_pricing_container');

        if (select.value) {
            var value = this.getValueByIndex(attribute, select.value);
            if (!value) {
                if (!container.querySelector('.attribute-price')) {
                    if (value == null) {
                        value = {};
                    }
                    container.innerHTML = this.pricingValueTemplate(value);
                    var priceValueField = container.querySelector('.attribute-price');
                    var priceTypeField = container.querySelector('.attribute-price-type');

                    priceValueField.attributeCode = attributeCode;
                    priceValueField.priceField = priceValueField;
                    priceValueField.typeField = priceTypeField;

                    priceTypeField.attributeCode = attributeCode;
                    priceTypeField.priceField = priceValueField;
                    priceTypeField.typeField = priceTypeField;

                    priceValueField.addEventListener('change', this.updateSimplePricing.bind(this));
                    priceValueField.addEventListener('keyup', this.updateSimplePricing.bind(this));
                    priceTypeField.addEventListener('change', this.updateSimplePricing.bind(this));

                    document.getElementById('simple_product_' + attributeCode + '_pricing_value').value = null;
                    document.getElementById('simple_product_' + attributeCode + '_pricing_type').value = null;
                }
            } else if (!isNaN(parseFloat(value.pricing_value))) {
                container.innerHTML = this.pricingValueViewTemplate({
                    'value': (parseFloat(value.pricing_value) > 0 ? '+' : '')
                        + parseFloat(value.pricing_value)
                        + (parseInt(value.is_percent) > 0 ? '%' : '')
                });
                document.getElementById('simple_product_' + attributeCode + '_pricing_value').value = value.pricing_value;
                document.getElementById('simple_product_' + attributeCode + '_pricing_type').value = value.is_percent;
            } else {
                container.innerHTML = '';
                document.getElementById('simple_product_' + attributeCode + '_pricing_value').value = null;
                document.getElementById('simple_product_' + attributeCode + '_pricing_type').value = null;
            }
        } else if (container) {
            container.innerHTML = '';
            document.getElementById('simple_product_' + attributeCode + '_pricing_value').value = null;
            document.getElementById('simple_product_' + attributeCode + '_pricing_type').value = null;
        }
    }
updateSimplePricing(evt) {
        var element = evt.target;
        if (element.priceField.value.trim() !== '') {
            document.getElementById('simple_product_' + element.attributeCode + '_pricing_value').value = element.priceField.value;
            document.getElementById('simple_product_' + element.attributeCode + '_pricing_type').value = element.typeField.value;
        } else {
            document.getElementById('simple_product_' + element.attributeCode + '_pricing_value').value = null;
            document.getElementById('simple_product_' + element.attributeCode + '_pricing_type').value = null;
        }
    }
updateSimpleForm() {
        this.attributes.forEach(function (attribute) {
            if (document.getElementById('simple_product_' + attribute.attribute_code)) {
                this.showPricing(
                    document.getElementById('simple_product_' + attribute.attribute_code),
                    attribute.attribute_code);
            }
        }.bind(this));
    }
showNoticeMessage() {
        document.getElementById('assign_product_warrning').style.display = 'block';
    }
};

var onInitDisableFieldsList = [];

function toogleFieldEditMode(toogleIdentifier, fieldContainer) {
    if (document.getElementById(toogleIdentifier).checked) {
        enableFieldEditMode(fieldContainer);
    } else {
        disableFieldEditMode(fieldContainer);
    }
}

function disableFieldEditMode(fieldContainer) {
    if (document.getElementById(fieldContainer)) {
        document.getElementById(fieldContainer).disabled = true;
    }
    if (document.getElementById(fieldContainer + '_hidden')) {
        document.getElementById(fieldContainer + '_hidden').disabled = true;
    }
}

function enableFieldEditMode(fieldContainer) {
    if (document.getElementById(fieldContainer)) {
        document.getElementById(fieldContainer).disabled = false;
    }
    if (document.getElementById(fieldContainer + '_hidden')) {
        document.getElementById(fieldContainer + '_hidden').disabled = false;
    }
}

function initDisableFields(fieldContainer) {
    onInitDisableFieldsList.push(fieldContainer);
}

function onCompleteDisableInited() {
    onInitDisableFieldsList.forEach(function (item) {
        disableFieldEditMode(item);
    });
}

function onUrlkeyChanged(urlKey) {
    const urlKeyElement = typeof urlKey === 'string' ? document.getElementById(urlKey) : urlKey;
    const hidden = urlKeyElement.nextElementSibling.type === 'hidden' ? urlKeyElement.nextElementSibling : null;
    const chbx = urlKeyElement.parentNode.querySelector('input[type=checkbox]');
    if (chbx) {
        const oldValue = chbx.value;
        chbx.disabled = (oldValue === urlKeyElement.value);
        if (hidden) {
            hidden.disabled = chbx.disabled;
        }
    }
}

function onCustomUseParentChanged(element) {
    const useParent = (element.value == 1);
    let parent = element.parentNode;
    for (let i = 0; i < 2 && parent; i++) {
        parent = parent.parentNode;
    }
    if (parent) {
        parent.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (element.id !== el.id) {
                el.disabled = useParent;
            }
        });
        parent.querySelectorAll('img').forEach(function (el) {
            el.style.display = useParent ? 'none' : '';
        });
    }
}

window.addEventListener('load', onCompleteDisableInited);
