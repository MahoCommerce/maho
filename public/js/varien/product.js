/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2017-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

var Product = Product ?? {};

Product.Config = class {
    constructor(config) {
        this.config = config;
        this.taxConfig = this.config.taxConfig;
        this.state = new Map();
        this.configureObservers = [];
        this.priceTemplate = new Template(this.config.template);
        this.prices = config.prices;

        if (this.config.containerId) {
            this.settings = document.querySelectorAll(`#${config.containerId} .super-attribute-select`);
        } else {
            this.settings = document.querySelectorAll('.super-attribute-select');
        }

        // Set default values
        if (this.config.defaultValues) {
            this.values = config.defaultValues;
        }

        // Overwrite defaults by url
        for (const [ key, value ] of new URLSearchParams(window.location.hash.slice(1))) {
            this.values ??= {};
            this.values[key] = value;
        }

        // Overwrite defaults by inputs values if needed
        if (this.config.inputsInitialized) {
            this.values = {};
            for (const element of this.settings) {
                if (element.value) {
                    const attributeId = element.id.replace(/[a-z]*/, '');
                    this.values[attributeId] = element.value;
                }
            }
        }

        // Add change event listeners to all settings
        for (const element of this.settings) {
            element.addEventListener('change', this.configure.bind(this));
        }

        // Fill state
        for (const element of this.settings) {
            const attributeId = element.id.replace(/[a-z]*/, '');
            if (attributeId && this.config.attributes[attributeId]) {
                element.config = this.config.attributes[attributeId];
                element.attributeId = attributeId;
                this.state.set(attributeId, false);
            }
        }

        // Init settings dropdown
        const childSettings = [];
        for (const [ i, element ] of Object.entries(this.settings).reverse()) {
            if (element === this.settings[0]) {
                this.fillSelect(element);
            }

            element.childSettings = [...childSettings];
            element.prevSetting = this.settings[+i - 1] || false;
            element.nextSetting = this.settings[+i + 1] || false;
            element.disabled = i > 0;

            childSettings.push(element);
        }

        this.configureForValues();
        document.addEventListener('DOMContentLoaded', this.configureForValues.bind(this));
    }

    configureSubscribe(fn) {
        this.configureObservers.push(fn);
    }

    configureForValues() {
        for (const element of this.settings) {
            if (this.values?.[element.attributeId]) {
                element.value = this.values[element.attributeId];
                this.configureElement(element);
            }
        }
    }

    configure(event) {
        const element = event.target;
        this.configureElement(element);
        for (const fn of this.configureObservers) {
            fn(element);
        }
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
        let selectedPrice = 0;
        if (element.options[element.selectedIndex]?.config && !this.config.stablePrices) {
            selectedPrice = parseFloat(element.options[element.selectedIndex].config.price);
        }

        for (const option of element.options) {
            if (option.config) {
                option.text = this.getOptionLabel(
                    option.config,
                    option.config.price - selectedPrice
                );
            }
        }
    }

    resetChildren(element) {
        if (element.childSettings) {
            for (const child of element.childSettings) {
                child.selectedIndex = 0;
                child.disabled = true;
                if (element.config) {
                    this.state.set(element.config.id, false);
                }
            }
        }
    }

    fillSelect(element) {
        const attributeId = element.id.replace(/[a-z]*/, '');
        const options = this.getAttributeOptions(attributeId);
        this.clearSelect(element);

        // Add default empty option
        element.options.add(new Option(this.config.chooseText, ''));

        let prevConfig;
        if (element.prevSetting) {
            prevConfig = element.prevSetting.options[element.prevSetting.selectedIndex];
        }

        for (const option of options ?? []) {
            const allowedProducts = option.products.filter((productId) => {
                return !prevConfig || prevConfig.config.allowedProducts?.includes(productId);
            });

            if (allowedProducts.length > 0) {
                option.allowedProducts = allowedProducts;

                const opt = new Option(this.getOptionLabel(option, option.price), option.id);
                opt.config = option;
                element.options.add(opt);
            }
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
            str += this.priceTemplate.evaluate({ price: price.toFixed(2) });
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
        if (this.config.disablePriceReload) {
            return;
        }

        let price = 0, oldPrice = 0;
        for (const [ i, element ] of Object.entries(this.settings).reverse()) {
            const selected = element.options[element.selectedIndex];
            if (selected?.config) {
                price += parseFloat(selected.config.price);
                oldPrice += parseFloat(selected.config.oldPrice);
            }
        }

        // Assuming optionsPrice is a global object
        optionsPrice.changePrice('config', { price, oldPrice });
        optionsPrice.reload();

        return price;
    }

    reloadOldPrice() {
        if (this.config.disablePriceReload) {
            return;
        }

        const oldPriceElement = document.getElementById(`old-price-${this.config.productId}`);
        if (!oldPriceElement) {
            return;
        }

        let price = parseFloat(this.config.oldPrice);
        for (let i = this.settings.length - 1; i >= 0; i--) {
            const selected = this.settings[i].options[this.settings[i].selectedIndex];
            if (selected?.config) {
                const parsedOldPrice = parseFloat(selected.config.oldPrice);
                price += isNaN(parsedOldPrice) ? 0 : parsedOldPrice;
            }
        }

        price = Math.max(0, price);
        oldPriceElement.textContent = this.formatPrice(price);
    }
}
