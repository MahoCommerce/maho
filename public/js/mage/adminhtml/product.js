/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const Product = {};

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
        this.container.ancestors().each(function (parentItem) {
            if (parentItem.tabObject) {
                imagesTab = parentItem.tabObject;
                throw $break;
            }
        }.bind(this));

        if (imagesTab && event.tab && event.tab.name && imagesTab.name == event.tab.name) {
            this.container.select('input[type="radio"]').each(function (radio) {
                radio.observe('change', this.onChangeRadio);
            }.bind(this));
            this.updateImages();
        }

    }
    fixParentTable() {
        this.container.ancestors().each(function (parentItem) {
            if (parentItem.tagName.toLowerCase() == 'td') {
                parentItem.style.width = '100%';
            }
            if (parentItem.tagName.toLowerCase() == 'table') {
                parentItem.style.width = '100%';
                throw $break;
            }
        });
    }
    getElement(name) {
        return document.getElementById(this.containerId + '_' + name);
    }
    showUploader() {
        this.getElement('add_images_button').style.display = 'none';
        this.getElement('uploader').style.display = '';
    }
    handleUploadComplete(files) {
        files.each(function (item) {
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
        this.getElement('save').value = Object.toJSON(this.images);
        $H(this.imageTypes).each(function (pair) {
            this.getFileElement('no_selection', 'cell-' + pair.key + ' input').checked = true;
        }.bind(this));
        this.images.each(function (row) {
            if (!$(this.prepareId(row.file))) {
                this.createImageRow(row);
            }
            this.updateVisualisation(row.file);
        }.bind(this));
        this.updateUseDefault(false);
    }
    onChangeRadio(evt) {
        var element = Event.element(evt);
        element.setHasChanges();
    }
    createImageRow(image) {
        var vars = Object.clone(image);
        vars.id = this.prepareId(image.file);
        var html = this.template.evaluate(vars);
        Element.insert(this.getElement('list'), {
            bottom: html
        });

        $(vars.id).select('input[type="radio"]').each(function (radio) {
            radio.observe('change', this.onChangeRadio);
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
        this.images.each(function (item) {
            if (parseInt(item.position) > maxPosition) {
                maxPosition = parseInt(item.position);
            }
        });
        return maxPosition + 1;
    }
    updateImage(file) {
        var index = this.getIndexByFile(file);

        var use_default_label = document.getElementById("use_default_label");
        if (use_default_label && use_default_label.checked) {
            this.images[index].label = null;
            this.images[index].label_use_default = true;
        } else {
            this.images[index].label = this.getFileElement(file, 'cell-label input').value;
            this.images[index].label_use_default = false;
        }

        var use_default_position = document.getElementById("use_default_position");
        if (use_default_position && use_default_position.checked) {
            this.images[index].position = null;
            this.images[index].position_use_default = true;
        } else {
            this.images[index].position = this.getFileElement(file, 'cell-position input').value;
            this.images[index].position_use_default = false;
        }

        this.images[index].removed = (this.getFileElement(file, 'cell-remove input').checked ? 1 : 0);
        this.images[index].disabled = (this.getFileElement(file, 'cell-disable input').checked ? 1 : 0);
        this.getElement('save').value = Object.toJSON(this.images);
        this.updateState(file);
        this.container.setHasChanges();
    }
    loadImage(file) {
        var image = this.getImageByFile(file);
        this.getFileElement(file, 'cell-image img').src = image.url;
        this.getFileElement(file, 'cell-image img').show();
        this.getFileElement(file, 'cell-image .place-holder').hide();
    }
    setProductImages(file) {
        $H(this.imageTypes)
            .each(
                function (pair) {
                    if (this.getFileElement(file,
                        'cell-' + pair.key + ' input').checked) {
                        this.imagesValues[pair.key] = (file == 'no_selection' ? null
                            : file);
                    }
                }.bind(this));

        this.getElement('save_image').value = Object.toJSON($H(this.imagesValues));
    }
    updateVisualisation(file) {
        var image = this.getImageByFile(file);

        var use_default_label = document.getElementById("use_default_label");
        if(use_default_label && use_default_label.checked) {
            this.getFileElement(file, 'cell-label input').value = image.label_default;
        } else {
            this.getFileElement(file, 'cell-label input').value = image.label;
        }

        var use_default_position = document.getElementById("use_default_position");
        if(use_default_position && use_default_position.checked) {
            this.getFileElement(file, 'cell-position input').value = image.position_default;
        } else {
            this.getFileElement(file, 'cell-position input').value = image.position;
        }

        this.getFileElement(file, 'cell-remove input').checked = (image.removed == 1);
        this.getFileElement(file, 'cell-disable input').checked = (image.disabled == 1);
        $H(this.imageTypes).each(function (pair) {
            if (this.imagesValues[pair.key] == file) {
                this.getFileElement(file, 'cell-' + pair.key + ' input').checked = true;
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
        this.images.each(function (item, i) {
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
            this.images.each(function (row) {
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

Product.AttributesBridge = {
    tabsObject: false,
    bindTabs2Attributes: {},
    bind: function (tabId, attributesObject) {
        this.bindTabs2Attributes[tabId] = attributesObject;
    },
    getAttributes: function (tabId) {
        return this.bindTabs2Attributes[tabId];
    },
    setTabsObject: function (tabs) {
        this.tabsObject = tabs;
    },
    getTabsObject: function () {
        return this.tabsObject;
    },
    addAttributeRow: function (data) {
        $H(data).each(function (item) {
            if (this.getTabsObject().activeTab.name != item.key) {
                this.getTabsObject().showTabContent($(item.key));
            }
            this.getAttributes(item.key).addRow(item.value);
        }.bind(this));
    }
};

Product.Attributes = class {
    constructor(containerId) {
        this.config = {};
        this.containerId = containerId;
    }
    setConfig(config) {
        this.config = config;
        Product.AttributesBridge.bind(this.getConfig().tab_id, this);
    }
    getConfig() {
        return this.config;
    }
    create() {
        var win = window.open(this.getConfig().url, 'new_attribute',
            'width=900,height=600,resizable=1,scrollbars=1');
        win.focus();
    }
    addRow(html) {
        var attributesContainer = $$('#group_fields' + this.getConfig().group_id + ' .form-list tbody')[0];
        Element.insert(attributesContainer, {
            bottom: html
        });

        var childs = attributesContainer.childElements();
        var element = childs[childs.size() - 1].select('input', 'select',
            'textarea')[0];
        if (element) {
            window.scrollTo(0, Position.cumulativeOffset(element)[1]
                + element.offsetHeight);
        }
    }
};

Product.Configurable = class {
    constructor(attributes, links, idPrefix, grid, readonly) {
        this.templatesSyntax = new RegExp('(^|.|\\r|\\n)(\'{{\\s*(\\w+)\\s*}}\')', "");
        this.attributes = attributes; // Attributes
        this.idPrefix = idPrefix; // Container id prefix
        this.links = $H(links); // Associated products
        this.newProducts = []; // For product that's created through Create
        // Empty and Copy from Configurable
        this.readonly = readonly;

        /* Generation templates */
        this.addAttributeTemplate = new Template(
            $(idPrefix + 'attribute_template').innerHTML.replace(/__id__/g,
            "'{{html_id}}'").replace(/ template no-display/g, ''),
            this.templatesSyntax);
        this.addValueTemplate = new Template(
            $(idPrefix + 'value_template').innerHTML.replace(/__id__/g,
            "'{{html_id}}'").replace(/ template no-display/g, ''),
            this.templatesSyntax);
        this.pricingValueTemplate = new Template($(idPrefix + 'simple_pricing').innerHTML, this.templatesSyntax);
        this.pricingValueViewTemplate = new Template($(idPrefix + 'simple_pricing_view').innerHTML, this.templatesSyntax);

        this.container = $(idPrefix + 'attributes');

        /* Listeners */
        this.onLabelUpdate = this.updateLabel.bindAsEventListener(this); // Update
        // attribute
        // label
        this.onValuePriceUpdate = this.updateValuePrice
            .bindAsEventListener(this); // Update pricing value
        this.onValueTypeUpdate = this.updateValueType.bindAsEventListener(this); // Update
        // pricing
        // type
        this.onValueDefaultUpdate = this.updateValueUseDefault
            .bindAsEventListener(this);

        /* Grid initialization and attributes initialization */
        this.createAttributes(); // Creation of default attributes

        this.grid = grid;
        this.grid.rowClickCallback = this.rowClick.bind(this);
        this.grid.initRowCallback = this.rowInit.bind(this);
        this.grid.checkboxCheckCallback = this.registerProduct.bind(this); // Associate/Unassociate
        // simple
        // product

        this.grid.rows.each(function (row) {
            this.rowInit(this.grid, row);
        }.bind(this));
    }
    createAttributes() {
        this.attributes.each(function (attribute, index) {
            var li = $(document.createElement('LI'));
            li.className = 'attribute';
            li.id = this.idPrefix + '_attribute_' + index;
            attribute.html_id = li.id;
            if (attribute && attribute.label && attribute.label.blank()) {
                attribute.label = '&nbsp;';
            }
            var label_readonly = '';
            var use_default_checked = '';
            if (attribute.use_default == '1' || attribute.id == null) {
                use_default_checked = ' checked="checked"';
                label_readonly = ' readonly="readonly"';
            }

            var template = this.addAttributeTemplate.evaluate(attribute);
            template = template.replace(new RegExp(' readonly="label"', 'ig'), label_readonly);
            template = template.replace(new RegExp(' checked="use_default"', 'ig'), use_default_checked);
            li.update(template);
            li.attributeObject = attribute;

            this.container.appendChild(li);
            li.attributeValues = li.down('.attribute-values');

            if (attribute.values) {
                attribute.values.each(function (value) {
                    this.createValueRow(li, value); // Add pricing values
                }.bind(this));
            }

            /* Observe label change */
            Event.observe(li.down('.attribute-label'), 'change', this.onLabelUpdate);
            Event.observe(li.down('.attribute-label'), 'keyup', this.onLabelUpdate);
            Event.observe(li.down('.attribute-use-default-label'), 'change', this.onLabelUpdate);
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
        var li = Event.findElement(event, 'LI');
        var labelEl = li.down('.attribute-label');
        var defEl = li.down('.attribute-use-default-label');

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
        this.container.childElements().each(function (row, index) {
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
            this.links.unset(element.value);
        }
        this.updateGrid();
        this.grid.rows.each(function (row) {
            this.revalidateRow(this.grid, row);
        }.bind(this));
        this.updateValues();
    }
updateProduct(productId, attributes) {
        var isAssociated = false;

        if (typeof this.links.get(productId) != 'undefined') {
            isAssociated = true;
            this.links.unset(productId);
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
            newObj[i] = Object.clone(attributes[i]);
        }
        return newObj;
    }
rowClick(grid, event) {
        var trElement = Event.findElement(event, 'tr');
        var isInput = Event.element(event).tagName.toUpperCase() == 'INPUT';

        if ($(Event.findElement(event, 'td')).down('a')) {
            return;
        }

        if (trElement) {
            var checkbox = $(trElement).down('input');
            if (checkbox && !checkbox.disabled) {
                var checked = isInput ? checkbox.checked : !checkbox.checked;
                grid.setCheckboxChecked(checkbox, checked);
            }
        }
    }
rowInit(grid, row) {
        var checkbox = $(row).down('.checkbox');
        var input = $(row).down('.value-json');
        if (checkbox && input) {
            checkbox.linkAttributes = input.value.evalJSON();
            if (!checkbox.checked) {
                if (!this.checkAttributes(checkbox.linkAttributes)) {
                    $(row).addClassName('invalid');
                    checkbox.disable();
                } else {
                    $(row).removeClassName('invalid');
                    checkbox.enable();
                }
            }
        }
    }
revalidateRow(grid, row) {
        var checkbox = $(row).down('.checkbox');
        if (checkbox) {
            if (!checkbox.checked) {
                if (!this.checkAttributes(checkbox.linkAttributes)) {
                    $(row).addClassName('invalid');
                    checkbox.disable();
                } else {
                    $(row).removeClassName('invalid');
                    checkbox.enable();
                }
            }
        }
    }
checkAttributes(attributes) {
        var result = true;
        this.links
            .each(function (pair) {
                var fail = false;
                for (var i = 0; i < pair.value.length && !fail; i++) {
                    for (var j = 0; j < attributes.length && !fail; j++) {
                        if (pair.value[i].attribute_id == attributes[j].attribute_id
                            && pair.value[i].value_index != attributes[j].value_index) {
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
            'products[]': this.links.keys().size() ? this.links.keys() : [0],
            'new_products[]': this.newProducts
        };
    }
updateValues() {
        var uniqueAttributeValues = $H({});
        /* Collect unique attributes */
        this.links.each(function (pair) {
            for (var i = 0, length = pair.value.length; i < length; i++) {
                var attribute = pair.value[i];
                if (uniqueAttributeValues.keys()
                    .indexOf(attribute.attribute_id) == -1) {
                    uniqueAttributeValues.set(attribute.attribute_id, $H({}));
                }
                uniqueAttributeValues.get(attribute.attribute_id).set(
                    attribute.value_index, attribute);
            }
        });
        /* Updating attributes value container */
        this.container
            .childElements()
            .each(
                function (row) {
                    var attribute = row.attributeObject;
                    // Instead of removing unused attribute values, only add new ones
                    // that are used by associated products but not yet displayed
                    if (uniqueAttributeValues.get(attribute.attribute_id)) {
                        uniqueAttributeValues.get(attribute.attribute_id).each(
                            function (pair) {
                                // Check if this value is already in the attribute values
                                var valueExists = false;
                                for (var i = 0; i < attribute.values.length; i++) {
                                    if (attribute.values[i] && attribute.values[i].value_index == pair.value.value_index) {
                                        valueExists = true;
                                        break;
                                    }
                                }
                                // Only add if it doesn't exist yet
                                if (!valueExists) {
                                    attribute.values.push(pair.value);
                                    this.createValueRow(row, pair.value);
                                }
                            }.bind(this));
                    }
                }.bind(this));
        this.updateSaveInput();
        this.updateSimpleForm();
    }
createValueRow(container, value) {
        var templateVariables = $H({});
        if (!this.valueAutoIndex) {
            this.valueAutoIndex = 1;
        }
        templateVariables.set('html_id', container.id + '_'
            + this.valueAutoIndex);
        templateVariables.update(value);
        var pricingValue = parseFloat(templateVariables.get('pricing_value'));
        if (!isNaN(pricingValue)) {
            templateVariables.set('pricing_value', pricingValue);
        } else {
            templateVariables.unset('pricing_value');
        }
        this.valueAutoIndex++;

        // var li = $(Builder.node('li', {className:'attribute-value'}));
        var li = $(document.createElement('LI'));
        li.className = 'attribute-value';
        li.id = templateVariables.get('html_id');
        li.update(this.addValueTemplate.evaluate(templateVariables));
        li.valueObject = value;
        if (typeof li.valueObject.is_percent == 'undefined') {
            li.valueObject.is_percent = 0;
        }

        if (typeof li.valueObject.pricing_value == 'undefined') {
            li.valueObject.pricing_value = '';
        }

        container.attributeValues.appendChild(li);

        var priceField = li.down('.attribute-price');
        var priceTypeField = li.down('.attribute-price-type');

        if (priceTypeField != undefined && priceTypeField.options != undefined) {
            if (parseInt(value.is_percent)) {
                priceTypeField.options[1].selected = !(priceTypeField.options[0].selected = false);
            } else {
                priceTypeField.options[1].selected = !(priceTypeField.options[0].selected = true);
            }
        }

        Event.observe(priceField, 'keyup', this.onValuePriceUpdate);
        Event.observe(priceField, 'change', this.onValuePriceUpdate);
        Event.observe(priceTypeField, 'change', this.onValueTypeUpdate);
        var useDefaultEl = li.down('.attribute-use-default-value');
        if (useDefaultEl) {
            if (li.valueObject.use_default_value) {
                useDefaultEl.checked = true;
                this.updateUseDefaultRow(useDefaultEl, li);
            }
            Event.observe(useDefaultEl, 'change', this.onValueDefaultUpdate);
        }
    }
updateValuePrice(event) {
        var li = Event.findElement(event, 'LI');
        li.valueObject.pricing_value = (Event.element(event).value.blank() ? null
            : Event.element(event).value);
        this.updateSimpleForm();
        this.updateSaveInput();
    }
updateValueType(event) {
        var li = Event.findElement(event, 'LI');
        li.valueObject.is_percent = (Event.element(event).value.blank() ? null
            : Event.element(event).value);
        this.updateSimpleForm();
        this.updateSaveInput();
    }
updateValueUseDefault(event) {
        var li = Event.findElement(event, 'LI');
        var useDefaultEl = Event.element(event);
        li.valueObject.use_default_value = useDefaultEl.checked;
        this.updateUseDefaultRow(useDefaultEl, li);
    }
updateUseDefaultRow(useDefaultEl, li) {
        var priceField = li.down('.attribute-price');
        var priceTypeField = li.down('.attribute-price-type');
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
        var oldSaveAttributesValue = $(this.idPrefix + 'save_attributes').value;
        var oldSaveLinksValue = $(this.idPrefix + 'save_links').value;
        var newSaveAttributesValue = Object.toJSON(this.attributes);
        var newSaveLinksValue = Object.toJSON(this.links);
        $(this.idPrefix + 'save_attributes').value = newSaveAttributesValue;
        $(this.idPrefix + 'save_links').value = newSaveLinksValue;
        if (oldSaveAttributesValue != newSaveAttributesValue || oldSaveLinksValue != newSaveLinksValue) {
            try {
                document.getElementById('configurable_save_attributes').setHasChanges();
            } catch (e) {}
        }
    }
initializeAdvicesForSimpleForm() {
        if ($(this.idPrefix + 'simple_form').advicesInited) {
            return;
        }

        $(this.idPrefix + 'simple_form').select('td.value').each(function (td) {
            var adviceContainer = $(document.createElement('div'));
            td.appendChild(adviceContainer);
            td.select('input', 'select').each(function (element) {
                element.advaiceContainer = adviceContainer;
            });
        });
        $(this.idPrefix + 'simple_form').advicesInited = true;
    }
quickCreateNewProduct() {
        this.initializeAdvicesForSimpleForm();
        $(this.idPrefix + 'simple_form').removeClassName('ignore-validate');
        var validationResult = $(this.idPrefix + 'simple_form').select('input', 'select', 'textarea').collect(function (elm) {
            return Validation.validate(elm, {
                useTitle: false,
                onElementValidate: function () {
                }
            });
        }).all();
        $(this.idPrefix + 'simple_form').addClassName('ignore-validate');

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
            .each(function (attribute) {
                attributes.push({
                    attribute_id: attribute.attribute_id,
                    value_index: $('simple_product_' + attribute.attribute_code).value
                });
            }.bind(this));

        return this.checkAttributes(attributes);
    }
getAttributeByCode(attributeCode) {
        var attribute = null;
        this.attributes.each(function (item) {
            if (item.attribute_code == attributeCode) {
                attribute = item;
                throw $break;
            }
        });
        return attribute;
    }
getAttributeById(attributeId) {
        var attribute = null;
        this.attributes.each(function (item) {
            if (item.attribute_id == attributeId) {
                attribute = item;
                throw $break;
            }
        });
        return attribute;
    }
getValueByIndex(attribute, valueIndex) {
        var result = null;
        attribute.values.each(function (value) {
            if (value.value_index == valueIndex) {
                result = value;
                throw $break;
            }
        });
        return result;
    }
showPricing(select, attributeCode) {
        var attribute = this.getAttributeByCode(attributeCode);
        if (!attribute) {
            return;
        }

        select = $(select);
        if (select.value && !$('simple_product_' + attributeCode + '_pricing_container')) {
            Element.insert(select, {
                after: '<div class="left"></div> <div id="simple_product_' + attributeCode + '_pricing_container" class="left"></div>'
            });
            var newContainer = select.next('div');
            select.parentNode.removeChild(select);
            newContainer.appendChild(select);
            // Fix visualization bug
            $(this.idPrefix + 'simple_form').down('.form-list').style.width = '100%';
        }

        var container = $('simple_product_' + attributeCode + '_pricing_container');

        if (select.value) {
            var value = this.getValueByIndex(attribute, select.value);
            if (!value) {
                if (!container.down('.attribute-price')) {
                    if (value == null) {
                        value = {};
                    }
                    container.update(this.pricingValueTemplate.evaluate(value));
                    var priceValueField = container.down('.attribute-price');
                    var priceTypeField = container.down('.attribute-price-type');

                    priceValueField.attributeCode = attributeCode;
                    priceValueField.priceField = priceValueField;
                    priceValueField.typeField = priceTypeField;

                    priceTypeField.attributeCode = attributeCode;
                    priceTypeField.priceField = priceValueField;
                    priceTypeField.typeField = priceTypeField;

                    Event.observe(priceValueField, 'change', this.updateSimplePricing.bindAsEventListener(this));
                    Event.observe(priceValueField, 'keyup', this.updateSimplePricing.bindAsEventListener(this));
                    Event.observe(priceTypeField, 'change', this.updateSimplePricing.bindAsEventListener(this));

                    $('simple_product_' + attributeCode + '_pricing_value').value = null;
                    $('simple_product_' + attributeCode + '_pricing_type').value = null;
                }
            } else if (!isNaN(parseFloat(value.pricing_value))) {
                container.update(this.pricingValueViewTemplate.evaluate({
                    'value': (parseFloat(value.pricing_value) > 0 ? '+' : '')
                        + parseFloat(value.pricing_value)
                        + (parseInt(value.is_percent) > 0 ? '%' : '')
                }));
                $('simple_product_' + attributeCode + '_pricing_value').value = value.pricing_value;
                $('simple_product_' + attributeCode + '_pricing_type').value = value.is_percent;
            } else {
                container.update('');
                $('simple_product_' + attributeCode + '_pricing_value').value = null;
                $('simple_product_' + attributeCode + '_pricing_type').value = null;
            }
        } else if (container) {
            container.update('');
            $('simple_product_' + attributeCode + '_pricing_value').value = null;
            $('simple_product_' + attributeCode + '_pricing_type').value = null;
        }
    }
updateSimplePricing(evt) {
        var element = Event.element(evt);
        if (!element.priceField.value.blank()) {
            $('simple_product_' + element.attributeCode + '_pricing_value').value = element.priceField.value;
            $('simple_product_' + element.attributeCode + '_pricing_type').value = element.typeField.value;
        } else {
            $('simple_product_' + element.attributeCode + '_pricing_value').value = null;
            $('simple_product_' + element.attributeCode + '_pricing_type').value = null;
        }
    }
updateSimpleForm() {
        this.attributes.each(function (attribute) {
            if ($('simple_product_' + attribute.attribute_code)) {
                this.showPricing(
                    $('simple_product_' + attribute.attribute_code),
                    attribute.attribute_code);
            }
        }.bind(this));
    }
showNoticeMessage() {
        $('assign_product_warrning').show();
    }
};

var onInitDisableFieldsList = [];

function toogleFieldEditMode(toogleIdentifier, fieldContainer) {
    if ($(toogleIdentifier).checked) {
        enableFieldEditMode(fieldContainer);
    } else {
        disableFieldEditMode(fieldContainer);
    }
}

function disableFieldEditMode(fieldContainer) {
    if ($(fieldContainer)) {
        $(fieldContainer).disabled = true;
    }
    if ($(fieldContainer + '_hidden')) {
        $(fieldContainer + '_hidden').disabled = true;
    }
}

function enableFieldEditMode(fieldContainer) {
    if ($(fieldContainer)) {
        $(fieldContainer).disabled = false;
    }
    if ($(fieldContainer + '_hidden')) {
        $(fieldContainer + '_hidden').disabled = false;
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
