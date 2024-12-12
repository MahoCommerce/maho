/**
 * Maho
 *
 * @category    Varien
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2017-2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class ProductConfigConfigurable
{
    constructor(config) {
        this.config = config;
        this.taxConfig = config.taxConfig;
        if (config.containerId) {
            this.settings = document.querySelectorAll(`#${config.containerId} .super-attribute-select`);
        } else {
            this.settings = document.querySelectorAll('.super-attribute-select');
        }
        this.state = {};
        this.priceTemplate = new Template(config.template);
        this.prices = config.prices;

        if (config.defaultValues) {
            this.values = config.defaultValues;
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams) {
            for (const [key, value] of urlParams) {
                if (!this.values) {
                    this.values = {};
                }
                this.values[key] = value;
            }
        }

        if (config.inputsInitialized) {
            this.values = {};
            this.settings.forEach(element => {
                if (element.value) {
                    const attributeId = element.id.replace(/[a-z]*/, '');
                    this.values[attributeId] = element.value;
                }
            });
        }

        this.settings.forEach(element => {
            element.addEventListener('change', this.configure.bind(this));
        });

        this.settings.forEach(element => {
            const attributeId = element.id.replace(/[a-z]*/, '');
            if (attributeId && config.attributes[attributeId]) {
                element.config = config.attributes[attributeId];
                element.attributeId = attributeId;
                this.state[attributeId] = false;
            }
        });

        const childSettings = [];
        for (let i = this.settings.length - 1; i >= 0; i--) {
            const prevSetting = this.settings[i - 1] || false;
            const nextSetting = this.settings[i + 1] || false;
            if (i === 0) {
                this.fillSelect(this.settings[i]);
            } else {
                this.settings[i].disabled = true;
            }
            childSettings.push(this.settings[i]);
            this.settings[i].childSettings = [...childSettings];
            this.settings[i].prevSetting = prevSetting;
            this.settings[i].nextSetting = nextSetting;
        }

        this.configureForValues();
        document.addEventListener("DOMContentLoaded", this.configureForValues.bind(this));
    }

    configureForValues() {
        if (this.values) {
            this.settings.forEach(element => {
                const attributeId = element.attributeId;
                element.value = this.values[attributeId] || '';
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
            this.state[element.config.id] = element.value;
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
        if(element.options[element.selectedIndex].config && !this.config.stablePrices){
            selectedPrice = parseFloat(element.options[element.selectedIndex].config.price);
        }

        for (let i=0; i<element.options.length; i++ ){
            if(element.options[i].config){
                element.options[i].text = this.getOptionLabel(element.options[i].config, element.options[i].config.price-selectedPrice);
            }
        }
    }

    resetChildren(element) {
        if (element.childSettings) {
            element.childSettings.forEach(child => {
                child.selectedIndex = 0;
                child.disabled = true;
                if (child.config) {
                    this.state[child.config.id] = false;
                }
            });
        }
    }

    fillSelect(element) {
        const attributeId = element.id.replace(/[a-z]*/, '');
        const options = this.getAttributeOptions(attributeId);
        this.clearSelect(element);
        element.options[0] = new Option('', '');
        element.options[0].innerHTML = this.config.chooseText;

        const prevConfig = element.prevSetting ? element.prevSetting.options[element.prevSetting.selectedIndex] : false;

        if (options) {
            let index = 1;
            options.forEach(option => {
                const allowedProducts = [];
                if (prevConfig) {
                    option.products.forEach(product => {
                        if (prevConfig.config.allowedProducts.includes(product)) {
                            allowedProducts.push(product);
                        }
                    });
                } else {
                    allowedProducts.push(...option.products);
                }

                if (allowedProducts.length > 0) {
                    option.allowedProducts = allowedProducts;
                    element.options[index] = new Option(this.getOptionLabel(option, option.price), option.id);
                    if (option.price !== undefined) {
                        element.options[index].setAttribute('price', option.price);
                    }
                    element.options[index].config = option;
                    index++;
                }
            });
        }
    }

    getOptionLabel(option, price) {
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

        if (this.taxConfig.showIncludeTax || this.taxConfig.showBothPrices) {
            price = incl;
        } else {
            price = excl;
        }

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
        let str = '';
        price = parseFloat(price);
        if (showSign) {
            if (price < 0) {
                str += '-';
                price = -price;
            } else {
                str += '+';
            }
        }

        const roundedPrice = (Math.round(price * 100) / 100).toString();
        if (this.prices && this.prices[roundedPrice]) {
            str += this.prices[roundedPrice];
        } else {
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
        return this.config.attributes[attributeId] ? this.config.attributes[attributeId].options : null;
    }

    reloadPrice() {
        if (this.config.disablePriceReload) {
            return;
        }
        let price = 0;
        let oldPrice = 0;
        this.settings.forEach(element => {
            const selected = element.options[element.selectedIndex];
            if (selected.config) {
                price += parseFloat(selected.config.price);
                oldPrice += parseFloat(selected.config.oldPrice);
            }
        });
        optionsPrice.changePrice('config', {'price': price, 'oldPrice': oldPrice});
        optionsPrice.reload();
    }

    reloadOldPrice() {
        if (this.config.disablePriceReload) {
            return;
        }
        const priceElement = document.getElementById(`old-price-${this.config.productId}`);
        if (priceElement) {
            let price = parseFloat(this.config.oldPrice);
            this.settings.forEach(element => {
                const selected = element.options[element.selectedIndex];
                if (selected.config) {
                    price += parseFloat(selected.config.price);
                }
            });
            if (price < 0) {
                price = 0;
            }
            price = this.formatPrice(price);
            priceElement.innerHTML = price;
        }
    }
}

var Product = Product ?? {};
Product.Config = ProductConfigConfigurable;
