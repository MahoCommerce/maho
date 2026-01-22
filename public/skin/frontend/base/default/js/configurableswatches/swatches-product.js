/**
 * Maho
 *
 * @package     base_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

var windowLoaded = false;
window.addEventListener('load', function() {
    windowLoaded = true;
});


var Product = Product ?? {};

Product.Config = class extends Product.Config {
    constructor (config) {
        super(config);
        this.loadOptions();
    }

    fillSelect (element) {
        return;
    }

    resetChildren (element) {
        return;
    }

    configureForValues () {
        return;
    }

    handleSelectChange (element) {
        this.configureElement(element);
        this.configureObservers.forEach(function(funct) {
            funct(element);
        });
    }

    /**
     * Load ALL the options into the selects
     * Uses global var spConfig declared in template/configurableswatches/catalog/product/view/type/configurable.phtml
     */
    loadOptions () {
        this.settings.forEach(function(element){
            element.disabled = false;
            element.options[0] = new Option(this.config.chooseText, '');
            var attributeId = element.id.replace(/[a-z]*/, '');
            var options = this.getAttributeOptions(attributeId);
            if(options) {
                var index = 1;
                for(var i=0;i<options.length;i++){
                    options[i].allowedProducts = structuredClone(options[i].products);
                    element.options[index] = new Option(this.getOptionLabel(options[i], options[i].price), options[i].id);
                    if (typeof options[i].price != 'undefined') {
                        element.options[index].setAttribute('price', options[i].price);
                    }
                    element.options[index].setAttribute('data-label', options[i].label.toLowerCase());
                    element.options[index].config = options[i];
                    index++;
                }
            }
            this.reloadOptionLabels(element);
        }.bind(this));
    }
}

Product.ConfigurableSwatches = class {
    constructor(productConfig, config) {
        this.productConfig = false;
        this.configurableAttributes = {};

        // Options
        this._O = {
            selectFirstOption: false // select the first option of the first configurable attribute
        };

        // Flags
        this._F = {
            currentAction: false,
            firstOptionSelected: false,
            nativeSelectChange: true
        };

        // Namespaces
        this._N = {
            resetTimeout: false
        };

        // Elements
        this._E = {
            cartBtn: {
                btn: false,
                txt: ['Add to Cart'],
                onclick: function() { return false; }
            },
            availability: false,
            optionOver: false,
            optionOut: false,
            _last: {
                optionOver: false
            },
            activeConfigurableOptions: [],
            allConfigurableOptions: []
        };

        if (config && typeof(config) == 'object') {
            this.setConfig(config);
        }
        this.productConfig = productConfig;
        this.configurableAttributes = Object.values(productConfig.config.attributes);

        this.run();
    }

    /**
     * Sets the stage for configurable swatches, including attaching all the data and events needed in the process to all attributes and options
     */
    run() {
        // Set some dom dependent flags
        this._F.hasPresetValues = (typeof spConfig != "undefined" && typeof spConfig.values != "undefined");

        this.setStockData();

        // Set and store additional data on attributes and options and attach events to them
        this.configurableAttributes.forEach((attr, i) => {
            this.setAttrData(attr, i);
            attr.options.forEach((opt, j) => {
                this.setOptData(opt, attr, j);
                this._E.allConfigurableOptions.push(opt);
                this.attachOptEvents(opt);
            });
        });

        this.productConfig.configureSubscribe(this.onSelectChange.bind(this));

        if (this._F.hasPresetValues) {
            // store values
            this.values = spConfig.values;
            // find the options
            this.configurableAttributes.forEach((attr) => {
                var optId = this.values[attr.id];
                var foundOption = attr.options.find((opt) => opt.id === optId);
                if (foundOption) {
                    this.selectOption(foundOption);
                }
            });
            this._F.presetValuesSelected = true;
        } else if (this._O.selectFirstOption) {
            this.selectFirstOption();
        }
        return this;
    }

    /**
     * Enables/Disables the add to cart button to prevent the user from selecting an out of stock item.
     * This also makes the necessary visual cues to show in stock/out of stock.
     */
    setStockData() {
        var cartBtn = document.querySelectorAll('.add-to-cart button.button');
        this._E.cartBtn = {
            btn: cartBtn,
            txt: cartBtn.length ? cartBtn[0].getAttribute('title') : '',
            onclick: cartBtn.length ? cartBtn[0].getAttribute('onclick') : ''
        };
        this._E.availability = document.querySelectorAll('p.availability');
        // Set cart button event
        cartBtn.forEach((btn) => {
            btn.addEventListener('mouseenter', () => {
                clearTimeout(this._N.resetTimeout);
                this.resetAvailableOptions();
            });
        });
    }

    /**
     * Sets the necessary flags on the attribute and stores the DOM elements related to the attribute
     *
     * @var attr - an object with options
     * @var i - index of attr in `configurableAttributes`
     */
    setAttrData(attr, i) {
        var optionSelect = document.getElementById('attribute' + attr.id);
        // Flags
        attr._f = {};
        // FIXME for Custom Option Support
        attr._f.isCustomOption = false;
        attr._f.isSwatch = optionSelect.classList.contains('swatch-select');
        // Elements
        attr._e = {
            optionSelect: optionSelect,
            attrLabel: this._u_getAttrLabelElement(attr.code),
            selectedOption: false,
            _last: {
                selectedOption: false
            }
        }
        optionSelect.attr = attr;
        if (attr._f.isSwatch) {
            attr._e.ul = document.getElementById('configurable_swatch_' + attr.code);
        }
        return attr;
    }

    /**
     * Set necessary flags and related DOM elements at an option level
     *
     * @var opt - object being looped through
     * @var attr - the object from which the `opt` came from
     * @var j - index of `opt` in `attr`
     */
    setOptData(opt, attr, j) {
        opt.attr = attr;
        opt._f = {
            isSwatch: attr._f.isSwatch,
            enabled: true,
            active: false
        };
        opt._e = {
            option: this._u_getOptionElement(opt, attr, j)
        };
        opt._e.option.opt = opt;
        if (attr._f.isSwatch) {
            opt._e.a = document.getElementById('swatch'+opt.id);
            opt._e.li = document.getElementById('option'+opt.id);
            opt._e.ul = attr._e.ul;
        }
        return opt;
    }

    attachOptEvents(opt) {
        const attr = opt.attr;

        // Swatch Events
        if (opt._f.isSwatch) {
            opt._e.a.addEventListener('click', (event) => {
                event.stopPropagation();
                this._F.currentAction = "click";
                // set new last option
                attr._e._last.selectedOption = attr._e.selectedOption;
                // Store selected option
                attr._e.selectedOption = opt;

                // Run the event
                this.onOptionClick( attr );
            });

            opt._e.a.addEventListener('mouseenter', () => {
                this._F.currentAction = "over-swatch";
                // set active over option to this option
                this._E.optionOver = opt;
                this.onOptionOver();
                // set the new last option
                this._E._last.optionOver = this._E.optionOver;
            });

            opt._e.a.addEventListener('mouseleave', () => {
                this._F.currentAction = "out-swatch";
                this._E.optionOut = opt;
                this.onOptionOut();
            });
        }
    }

    selectFirstOption() {
        if (this.configurableAttributes.length) {
            var attr = this.configurableAttributes[0];
            if (attr.options.length) {
                var opt = attr.options[0];
                this.selectOption(opt);
            };
        };
    }

    /**
     * Initialize the selecting of an option: set necessary flags,
     * store active options, and remove last active options
     * Send to onOptionClick method
     */
    selectOption(opt) {
        const attr = opt.attr;

        this._F.currentAction = "click";
        // set new last option
        attr._e._last.selectedOption = attr._e.selectedOption;
        // Store selected option
        attr._e.selectedOption = opt;

        // Run the event
        this.onOptionClick( attr );
    }

    onSelectChange(select) {
        var attr = select.attr;

        if (this._F.nativeSelectChange) {
            this._F.currentAction = 'change';
            var option = select.options[select.selectedIndex];

            if (option.opt) {
                const previousOption = attr._e.selectedOption;
                attr._e.selectedOption = option.opt;

                if (previousOption) {
                    previousOption._f.active = false;
                }
                option.opt._f.active = true;

                const pos = this._E.activeConfigurableOptions.indexOf(previousOption);
                if (pos !== -1) this._E.activeConfigurableOptions.splice(pos, 1);

                this._E.activeConfigurableOptions.push(option.opt);
            } else {
                const previousOption = attr._e.selectedOption;
                this._E.activeConfigurableOptions = this._E.activeConfigurableOptions.filter(opt => opt !== previousOption);
                if (previousOption) {
                    previousOption._f.active = false;
                }
            }

            this.setAvailableOptions();
            this.checkStockStatus();
        }
    }

    /**
     * Run everything that needs to happen (visually and functionally) when an option is clicked
     */
    onOptionClick(attr) {
        var opt = attr._e.selectedOption;
        if (opt) {
            if (opt !== attr._e._last.selectedOption) {
                if (attr._e.attrLabel !== false) {
                    attr._e.attrLabel.innerHTML = this.getOptionLabel(opt);
                }

                if (opt._f.isSwatch) {
                    opt._e.ul.querySelectorAll('li').forEach(li => li.classList.remove('selected'));
                    opt._e.li.classList.add('selected');
                    var inputBox = attr._e.optionSelect.parentElement;
                    if (inputBox.classList.contains('validation-error')) {
                        inputBox.classList.remove('validation-error');
                        inputBox.querySelector('.validation-advice').remove();
                    }
                };

                if (attr._e._last.selectedOption) attr._e._last.selectedOption._f.active = false;
                opt._f.active = true;

                var pos = this._E.activeConfigurableOptions.indexOf(attr._e._last.selectedOption);
                if (pos !== -1) this._E.activeConfigurableOptions.splice(pos, 1);

                this._E.activeConfigurableOptions.push(opt);

                this.setAvailableOptions();
                if (opt._f.isSwatch && !attr._f.isCustomOption && this._F.firstOptionSelected) {
                    this.previewAvailableOptions();
                };
            };
        } else {
            var pos = this._E.activeConfigurableOptions.indexOf(attr._e._last.selectedOption);
            if (pos !== -1) this._E.activeConfigurableOptions.splice(pos, 1);
            if (attr._e._last.selectedOption) attr._e._last.selectedOption._f.active = false;
            this.setAvailableOptions();
        }
        this.checkStockStatus();

        this._E.activeConfigurableOptions.forEach(selectedOpt => {
            var oldDisabledValue = selectedOpt._e.option.disabled;
            selectedOpt._e.option.disabled = false;
            selectedOpt._e.option.selected = true;
            selectedOpt._e.option.disabled = oldDisabledValue;
        });

        if ((this._O.selectFirstOption && !this._F.firstOptionSelected) ||
            (this._F.hasPresetValues && !this._F.presetValuesSelected) ||
            (!windowLoaded)) {
            window.addEventListener('load', () => {
                setTimeout(() => {
                    this.updateSelect(attr);
                    this._F.firstOptionSelected = true;
                }, 200);
            });
        } else {
            this.updateSelect(attr);
            this._F.firstOptionSelected = true;
        }
    }

    /**
     * Visual cues if you were to click on the option/swatch you're hovering over
     * - Show enabled/disabled state of other options/swatches
     * - Preview label of hovered swatch
     * - Preview the stock status
     */
    onOptionOver() {
        // Since browsers like Safari on iOS will emulate a hover event, use custom event detection to determine
        // whether if input is touch. If event *is* touch, then don't run this code so that the onOptionClick
        // method will be triggered.
        if (PointerManager.getPointer() == PointerManager.TOUCH_POINTER_TYPE) {
            return;
        }

        var opt = this._E.optionOver;
        var attr = opt.attr;
        var lastOpt = this._E._last.optionOver;

        // clear mouseout timeout
        clearTimeout(this._N.resetTimeout);

        // Remove last hover class
        if (lastOpt && lastOpt._f.isSwatch) {
            lastOpt._e.li.classList.remove('hover');
        }
        // Set new hover class
        if (opt._f.isSwatch) {
            opt._e.li.classList.add('hover');
        }

        // Change label
        attr._e.attrLabel.textContent = this.getOptionLabel(opt);

        // run setAvailable before previewAvailable and reset last label if
        // 1) the timeout has not been run (which means lastOpt != false) and
        // 2) the last hover swatch's attribute is different than this hover swatch's
        this.setAvailableOptions();
        if(lastOpt && lastOpt.attr.id != opt.attr.id) {
            // reset last hover swatch's attribute
            lastOpt.attr._e.attrLabel.textContent = lastOpt.attr._e.selectedOption ? this.getOptionLabel(lastOpt.attr._e.selectedOption) : '';
        }

        // Preview available
        if (!attr._f.isCustomOption) {
            this.previewAvailableOptions();

            // Set Stock Status
            // start with all active options, minus the one from the attribute currently being hovered
            var stockCheckOptions = this._E.activeConfigurableOptions;
            if (!opt._f.active) {
                // Remove the attribute's selected option (if applicable)
                stockCheckOptions = stockCheckOptions.filter(option => option !== attr._e.selectedOption);
                // Add the currently hovered option
                stockCheckOptions.push(opt);
            };
            this.checkStockStatus(stockCheckOptions);
        }
    }

    /**
     * Reset all visual cues from onOptionOver
     */
    onOptionOut() {
        // Since browsers like Safari on iOS will emulate a hover event, use custom event detection to determine
        // whether if input is touch. If event *is* touch, then don't run this code so that the onOptionClick
        // method will be triggered.
        if (PointerManager.getPointer() === PointerManager.TOUCH_POINTER_TYPE) return;

        const opt = this._E.optionOver;
        this._N.resetTimeout = setTimeout(() => {
            this.resetAvailableOptions();
        }, 300);

        if (opt && opt._f.isSwatch) {
            opt._e.li.classList.remove('hover');
        }
    }

    /**
     * Loop through each option across all attributes to set them as available or not
     * and set necessary flags as such
     */
    setAvailableOptions() {
        const args = arguments;
        const loopThroughOptions = args.length ? args[0] : this._E.allConfigurableOptions;
        loopThroughOptions.forEach(loopingOption => {
            const productArrays = [loopingOption.products];
            if (loopingOption.attr._e.selectedOption) {
                this._E.activeConfigurableOptions.filter(opt => opt !== loopingOption.attr._e.selectedOption).forEach(selectedOpt => {
                    productArrays.push(selectedOpt.products);
                });
            } else {
                this._E.activeConfigurableOptions.forEach(selectedOpt => {
                    productArrays.push(selectedOpt.products);
                });
            }
            const result = this._u_intersectAll(productArrays);
            this.setOptionStatus(loopingOption, result.length);
        });
    }

    /**
     * Loop though each option across all attributes to preview their availability if the
     * option being hovered were to be selected
     */
    previewAvailableOptions() {
        const opt = this._E.optionOver;
        if (!opt) return;

        const attr = opt.attr;

        this._E.allConfigurableOptions.forEach(loopingOption => {
            const productArrays = [loopingOption.products, opt.products];

            if (attr.id === loopingOption.attr.id) return;

            if (!loopingOption.attr._e.selectedOption) {
                this._E.activeConfigurableOptions.forEach(selectedOpt => {
                    if (selectedOpt.attr.id !== opt.attr.id) {
                        productArrays.push(selectedOpt.products);
                    }
                });
            }

            const result = this._u_intersectAll(productArrays);
            this.setOptionStatus(loopingOption, result.length);
        });
    }

    /**
     * Reset all the options and their availability, the attribute labels, and the stock status
     */
    resetAvailableOptions() {
        const opt = this._E.optionOver;

        if (opt) {
            const attr = opt.attr;

            // Reset last label
            attr._e.attrLabel.innerHTML = attr._e.selectedOption ? this.getOptionLabel(attr._e.selectedOption) : '';

            // Reset current action
            this._F.currentAction = false;

            // process
            if (!attr._f.isCustomOption) {
                // Reset the availability of all options
                this.setAvailableOptions();
                // Set stock status
                this.checkStockStatus();
            }

            // reset the last optionOver
            this._E._last.optionOver = false;
        };
    }

    /**
     * Run a check though all the selected options and set the stock status if any are disabled
     */
    checkStockStatus() {
        var checkOptions = arguments.length ? arguments[0] : this._E.activeConfigurableOptions;
        var inStock = !checkOptions.some(selectedOpt => !selectedOpt._f.enabled);
        this.setStockStatus(inStock);
    }

    /**
     * Do all the visual changes and enable/disable add to cart button depending on the stock status
     *
     * @var inStock - boolean
     */
    setStockStatus(inStock) {
        if (inStock) {
            this._E.availability.forEach(function(el) {
                el.classList.add('in-stock');
                el.classList.remove('out-of-stock');
                let spanEl = el.querySelector('span');
                if (spanEl) spanEl.textContent = Translator.translate('In Stock');
            });

            this._E.cartBtn.btn.forEach((el, index) => {
                el.disabled = false;
                el.classList.remove('out-of-stock');
                el.setAttribute('onclick', this._E.cartBtn.onclick);
                el.title = Translator.translate(this._E.cartBtn.txt);
                el.textContent = Translator.translate(this._E.cartBtn.txt);
            });
        } else {
            this._E.availability.forEach(function(el) {
                el.classList.add('out-of-stock');
                el.classList.remove('in-stock');
                el.textContent = Translator.translate('Out of Stock');
            });

            this._E.cartBtn.btn.forEach((el) => {
                el.classList.add('out-of-stock');
                el.disabled = true;
                el.removeAttribute('onclick');
                el.addEventListener('click', function(event) {
                    event.preventDefault();
                    return false;
                });
                el.setAttribute('title', Translator.translate('Out of Stock'));
                el.textContent = Translator.translate('Out of Stock');
            });
        }
    }

    /**
     * Enable/disable a specific option
     */
    setOptionStatus(opt, enabled) {
        const attr = opt.attr;
        const enabledBool = enabled > 0;

        // Set enabled flag on option
        opt._f.enabled = enabledBool;
        if (opt._f.isSwatch) {
            opt._e.li.classList.toggle('not-available', !enabledBool);
        } else if (this._F.currentAction === "click" || this._F.currentAction === "change") {
            // Set disabled and selected if action is permanent, ONLY for non-swatch selects
            opt._e.option.disabled = !enabledBool;
        }
        return enabledBool;
    }

    /**
     * Make sure all events related to the select being updated are fired appropriately
     */
    updateSelect(attr) {
        // fire select change event
        // this will trigger the validation of the select
        // only fire if this attribute has had a selected option at one time
        if (attr._e.selectedOption !== false && attr._e.optionSelect) {
            this._F.nativeSelectChange = false;
            ConfigurableMediaImages.updateImage(attr._e.optionSelect);
            this.productConfig.handleSelectChange(attr._e.optionSelect);
            this._F.nativeSelectChange = true;
        }
    }

    /**
     * Return text that should be displayed in attribute label for a certain option
     *
     * @param {object} option
     * return {string}
     */
    getOptionLabel(option) {
        return this.productConfig.getOptionLabel(option, option.price);
    }

    _u_getAttrLabelElement(attrCode) {
        let spanLabel = document.querySelector('#select_label_' + attrCode);
        if (spanLabel) {
            return spanLabel;
        } else {
            let label = document.querySelector('#' + attrCode + '_label');
            if (label) {
                return label.insertAdjacentHTML('beforeend', ' <span id="select_label_' + attrCode + '" class="select-label"></span>').querySelector('span.select-label');
            }
        }
        return false;
    }

    _u_getOptionElement(opt, attr, idx) {
        var indexedOption = attr._e.optionSelect.options[idx+1];
        if (indexedOption && indexedOption.value == opt.id) {
            return indexedOption;
        };
        var optionElement = false;
        var optionsLen = attr._e.optionSelect.options.length;
        var option;
        for (var i=0; i<optionsLen; i++) {
            option = attr._e.optionSelect.options[i];
            if (option.value == opt.id) {
                optionElement = option;
                throw $break;
            };
        }
        return optionElement;
    }

    /**
     * Returns the intersection of all arrays in the given list.
     *
     * @param {array} lists - A list of arrays.
     * @returns {array} The intersection of all arrays.
     */
    _u_intersectAll(lists) {
        if (lists.length === 0) return [];
        if (lists.length === 1) return lists[0];
        return lists.reduce((a, b) => a.filter(c => b.includes(c)));
    }
}
