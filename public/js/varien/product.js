/**
 * Maho
 *
 * @category    Varien
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2017-2023 The OpenMage Contributors (https://openmage.org)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

var Product = Product ?? {};

/**************************** CONFIGURABLE PRODUCT **************************/
class ProductConfig
{
    constructor(config) {
        this.config = config;
        this.taxConfig = this.config.taxConfig;
        this.settings = Array.from(document.querySelectorAll('.super-attribute-select'));
        this.state = new Map();
        this.priceTemplate = new Template(this.config.template);
        this.prices = config.prices;

        // Add change event listeners to all settings
        this.settings.forEach(element => {
            element.addEventListener('change', () => this.configure());
        });

        // Fill state
        this.settings.forEach(element => {
            const attributeId = element.id.replace(/[a-z]*/, '');
            if (attributeId && this.config.attributes[attributeId]) {
                element.config = this.config.attributes[attributeId];
                element.attributeId = attributeId;
                this.state.set(attributeId, false);
            }
        });

        // Init settings dropdown
        const childSettings = [];
        for (let i = this.settings.length - 1; i >= 0; i--) {
            const prevSetting = this.settings[i - 1] || false;
            const nextSetting = this.settings[i + 1] || false;

            if (i === 0) {
                this.fillSelect(this.settings[i]);
            } else {
                this.settings[i].disabled = true;
            }

            this.settings[i].childSettings = [...childSettings];
            this.settings[i].prevSetting = prevSetting;
            this.settings[i].nextSetting = nextSetting;
            childSettings.push(this.settings[i]);
        }

        // Set default values
        if (config.defaultValues) {
            this.values = config.defaultValues;
        }

        // Parse URL parameters
        const separatorIndex = window.location.href.indexOf('#');
        if (separatorIndex !== -1) {
            const paramsStr = window.location.href.substr(separatorIndex + 1);
            const urlValues = new URLSearchParams(paramsStr);
            if (!this.values) {
                this.values = {};
            }
            for (const [key, value] of urlValues) {
                this.values[key] = value;
            }
        }

        this.configureForValues();
        document.addEventListener('DOMContentLoaded', () => this.configureForValues());
    }

    configureForValues() {
        if (this.values) {
            this.settings.forEach(element => {
                const attributeId = element.attributeId;
                element.value = (typeof(this.values[attributeId]) === 'undefined') ? '' : this.values[attributeId];
                this.configureElement(element);
            });
        }
    }

    configure(event) {
        const element = event.target;
        this.configureElement(element);
    }

    configureElement(element) {
        this.reloadOptionLabels(element);
        if (element.value) {
            this.state.set(element.config.id, element.value);
            if (element.nextSetting) {
                element.nextSetting.disabled = false;
                this.fillSelect(element.nextSetting);
                this.resetChildren(element.nextSetting);
            }
        } else {
            this.resetChildren(element);
        }
        this.reloadPrice();
    }

    reloadOptionLabels(element) {
        let selectedPrice;
        if (element.options[element.selectedIndex]?.config) {
            selectedPrice = parseFloat(element.options[element.selectedIndex].config.price);
        } else {
            selectedPrice = 0;
        }

        Array.from(element.options).forEach(option => {
            if (option.config) {
                option.text = this.getOptionLabel(
                    option.config,
                    option.config.price - selectedPrice
                );
            }
        });
    }

    resetChildren(element) {
        if (element.childSettings) {
            element.childSettings.forEach(child => {
                child.selectedIndex = 0;
                child.disabled = true;
                if (element.config) {
                    this.state.set(element.config.id, false);
                }
            });
        }
    }

    fillSelect(element) {
        const attributeId = element.id.replace(/[a-z]*/, '');
        const options = this.getAttributeOptions(attributeId);
        this.clearSelect(element);

        // Add default empty option
        element.options[0] = new Option('', '');
        element.options[0].innerHTML = this.config.chooseText;

        let prevConfig = false;
        if (element.prevSetting) {
            prevConfig = element.prevSetting.options[element.prevSetting.selectedIndex];
        }

        if (options) {
            let index = 1;
            options.forEach(option => {
                let allowedProducts = [];

                if (prevConfig) {
                    allowedProducts = option.products.filter(productId =>
                        prevConfig.config.allowedProducts &&
                        prevConfig.config.allowedProducts.includes(productId)
                    );
                } else {
                    allowedProducts = [...option.products]; // Create a copy of the array
                }

                if (allowedProducts.length > 0) {
                    option.allowedProducts = allowedProducts;
                    element.options[index] = new Option(
                        this.getOptionLabel(option, option.price),
                        option.id
                    );
                    element.options[index].config = option;
                    index++;
                }
            });
        }
    }

    getOptionLabel(option, price) {
        price = parseFloat(price);
        let tax, excl, incl;

        if (this.taxConfig.includeTax) {
            tax = price / (100 + this.taxConfig.defaultTax) * this.taxConfig.defaultTax;
            excl = price - tax;
            incl = excl * (1 + (this.taxConfig.currentTax / 100));
        } else {
            tax = price * (this.taxConfig.currentTax / 100);
            excl = price;
            incl = excl + tax;
        }

        // Determine final price based on tax configuration
        price = this.taxConfig.showIncludeTax || this.taxConfig.showBothPrices
            ? incl
            : excl;

        let str = option.label;
        if (price) {
            if (this.taxConfig.showBothPrices) {
                str += ` ${this.formatPrice(excl, true)} (${this.formatPrice(price, true)} ${this.taxConfig.inclTaxTitle})`;
            } else {
                str += ` ${this.formatPrice(price, true)}`;
            }
        }
        return str;
    }

    formatPrice(price, showSign) {
        price = parseFloat(price);
        let str = '';

        if (showSign) {
            str += price < 0 ? '-' : '+';
            price = Math.abs(price);
        }

        const roundedPrice = (Math.round(price * 100) / 100).toString();

        if (this.prices && this.prices[roundedPrice]) {
            str += this.prices[roundedPrice];
        } else {
            // Keep original priceTemplate evaluation
            str += this.priceTemplate.evaluate({price: price.toFixed(2)});
        }

        return str;
    }

    clearSelect(element) {
        while (element.options.length > 0) {
            element.remove(0);
        }
    }

    getAttributeOptions(attributeId) {
        return this.config.attributes[attributeId]?.options;
    }

    reloadPrice() {
        let price = 0;
        let oldPrice = 0;

        for (let i = this.settings.length - 1; i >= 0; i--) {
            const selected = this.settings[i].options[this.settings[i].selectedIndex];
            if (selected?.config) {
                price += parseFloat(selected.config.price);
                oldPrice += parseFloat(selected.config.oldPrice);
            }
        }

        // Assuming optionsPrice is a global object
        optionsPrice.changePrice('config', {
            price: price,
            oldPrice: oldPrice
        });
        optionsPrice.reload();

        return price;
    }

    reloadOldPrice() {
        const oldPriceElement = document.getElementById(`old-price-${this.config.productId}`);
        if (!oldPriceElement) return;

        let price = parseFloat(this.config.oldPrice);

        for (let i = this.settings.length - 1; i >= 0; i--) {
            const selected = this.settings[i].options[this.settings[i].selectedIndex];
            if (selected?.config) {
                const parsedOldPrice = parseFloat(selected.config.oldPrice);
                price += isNaN(parsedOldPrice) ? 0 : parsedOldPrice;
            }
        }

        price = Math.max(0, price);
        const formattedPrice = this.formatPrice(price);
        oldPriceElement.innerHTML = formattedPrice;
    }
}

/**************************** SUPER PRODUCTS ********************************/

class ProductSuperConfigurable
{
    constructor(container, observeCss, updateUrl, updatePriceUrl, priceContainerId) {
        this.container = document.getElementById(container);
        this.observeCss = observeCss;
        this.updateUrl = updateUrl;
        this.updatePriceUrl = updatePriceUrl;
        this.priceContainerId = priceContainerId;
        this.registerObservers();
    }

    registerObservers() {
        const elements = this.container.getElementsByClassName(this.observeCss);
        Array.from(elements).forEach(element => {
            element.addEventListener('change', (event) => this.update(event));
        });
        return this;
    }

    update(event) {
        const elements = this.container.getElementsByClassName(this.observeCss);
        const parameters = new FormData();
        Array.from(elements).forEach(element => {
            parameters.append(element.name, element.value);
        });

        // Update main container
        fetch(`${this.updateUrl}?ajax=1`, {
            method: 'POST',
            body: parameters
        })
            .then(response => response.text())
            .then(html => {
                this.container.innerHTML = html;
                this.registerObservers();
            });

        // Update price container if it exists
        const priceContainer = document.getElementById(this.priceContainerId);
        if (priceContainer) {
            fetch(`${this.updatePriceUrl}?ajax=1`, {
                method: 'POST',
                body: parameters
            })
                .then(response => response.text())
                .then(html => {
                    priceContainer.innerHTML = html;
                });
        }
    }
}

Product.Super = {};
Product.Super.Configurable = ProductSuperConfigurable;
