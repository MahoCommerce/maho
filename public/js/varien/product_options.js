/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

var Product = Product ?? {};

Product.Options = class {
    constructor(config) {
        this.config = config;
        this.reloadPrice();
        document.addEventListener('DOMContentLoaded', () => this.reloadPrice());
    }

    reloadPrice() {
        const config = this.config;
        const skipIds = [];

        for (const element of document.querySelectorAll('.product-custom-option')) {
            let optionId = 0;
            const match = element.name.match(/[0-9]+/);
            if (match) {
                optionId = parseInt(match[0], 10);
            }

            if (!config[optionId]) {
                return;
            }

            const configOptions = config[optionId];
            let curConfig = {price: 0};

            if (element.type === 'checkbox' || element.type === 'radio') {
                if (element.checked) {
                    if (typeof configOptions[element.value] !== 'undefined') {
                        curConfig = configOptions[element.value];
                    }
                }
            } else if (element.classList.contains('datetime-picker') && !skipIds.includes(optionId)) {
                let dateSelected = true;
                document.querySelectorAll(`.product-custom-option[id^="options_${optionId}"]`)
                    .forEach(dt => {
                        if (dt.value === '') {
                            dateSelected = false;
                        }
                    });

                if (dateSelected) {
                    curConfig = configOptions;
                    skipIds[optionId] = optionId;
                }
            } else if (element.type === 'select-one' || element.type === 'select-multiple') {
                if ('options' in element) {
                    Array.from(element.options).forEach(selectOption => {
                        if ('selected' in selectOption && selectOption.selected) {
                            if (typeof configOptions[selectOption.value] !== 'undefined') {
                                curConfig = configOptions[selectOption.value];
                            }
                        }
                    });
                }
            } else {
                if (element.value.trim() !== '') {
                    curConfig = configOptions;
                }
            }

            if (element.type === 'select-multiple' && ('options' in element)) {
                Array.from(element.options).forEach(selectOption => {
                    if ('selected' in selectOption &&
                        typeof configOptions[selectOption.value] !== 'undefined') {
                        if (selectOption.selected) {
                            curConfig = configOptions[selectOption.value];
                        } else {
                            curConfig = {price: 0};
                        }
                        optionsPrice.addCustomPrices(`${optionId}-${selectOption.value}`, curConfig);
                        optionsPrice.reload();
                    }
                });
            } else {
                optionsPrice.addCustomPrices(element.id || optionId, curConfig);
                optionsPrice.reload();
            }
        }
    }
}

Product.OptionsDate = class {
    getDaysInMonth(month, year) {
        const curDate = new Date();
        if (!month) {
            month = curDate.getMonth();
        }
        if (2 == month && !year) { // leap year assumption for unknown year
            return 29;
        }
        if (!year) {
            year = curDate.getFullYear();
        }
        return 32 - new Date(year, month - 1, 32).getDate();
    }

    reloadMonth(event) {
        const selectEl = event.target;
        const idParts = selectEl.id.split('_');
        if (idParts.length != 3) {
            return false;
        }
        const optionIdPrefix = idParts[0] + '_' + idParts[1];
        const month = parseInt(document.getElementById(optionIdPrefix + '_month').value);
        const year = parseInt(document.getElementById(optionIdPrefix + '_year').value);
        const dayEl = document.getElementById(optionIdPrefix + '_day');
        const days = this.getDaysInMonth(month, year);

        // remove days
        for (let i = dayEl.options.length - 1; i >= 0; i--) {
            if (dayEl.options[i].value > days) {
                dayEl.remove(i);
            }
        }

        // add days
        const lastDay = parseInt(dayEl.options[dayEl.options.length-1].value);
        for (let i = lastDay + 1; i <= days; i++) {
            this.addOption(dayEl, i, i);
        }
    }

    addOption(select, text, value) {
        const option = document.createElement('OPTION');
        option.value = value;
        option.text = text;
        select.options.add(option);
    }
}

Product.OptionsPrice = class {
    constructor(config) {
        this.productId = config.productId;
        this.priceFormat = config.priceFormat;
        this.includeTax = config.includeTax;
        this.defaultTax = config.defaultTax;
        this.currentTax = config.currentTax;
        this.productPrice = config.productPrice;
        this.showIncludeTax = config.showIncludeTax;
        this.showBothPrices = config.showBothPrices;
        this.productOldPrice = config.productOldPrice;
        this.priceInclTax = config.priceInclTax;
        this.priceExclTax = config.priceExclTax;
        this.duplicateIdSuffix = config.idSuffix;
        this.specialTaxPrice = config.specialTaxPrice;
        this.tierPrices = config.tierPrices;
        this.tierPricesInclTax = config.tierPricesInclTax;

        this.oldPlusDisposition = config.oldPlusDisposition;
        this.plusDisposition = config.plusDisposition;
        this.plusDispositionTax = config.plusDispositionTax;

        this.oldMinusDisposition = config.oldMinusDisposition;
        this.minusDisposition = config.minusDisposition;

        this.exclDisposition = config.exclDisposition;

        this.optionPrices = {};
        this.customPrices = {};
        this.containers = {};

        this.displayZeroPrice = true;

        this.initPrices();
    }

    initPrices() {
        this.containers[0] = 'product-price-' + this.productId;
        this.containers[1] = 'bundle-price-' + this.productId;
        this.containers[2] = 'price-including-tax-' + this.productId;
        this.containers[3] = 'price-excluding-tax-' + this.productId;
        this.containers[4] = 'old-price-' + this.productId;
    }

    changePrice(key, price) {
        this.optionPrices[key] = price;
    }

    addCustomPrices(key, price) {
        this.customPrices[key] = price;
    }

    getOptionPrices() {
        let price = 0;
        let nonTaxable = 0;
        let oldPrice = 0;
        let priceInclTax = 0;
        const currentTax = this.currentTax;

        Object.entries(this.optionPrices).forEach(([key, value]) => {
            if (typeof value.price !== 'undefined' && typeof value.oldPrice !== 'undefined') {
                price += parseFloat(value.price);
                oldPrice += parseFloat(value.oldPrice);
            } else if (key === 'nontaxable') {
                nonTaxable = value;
            } else if (key === 'priceInclTax') {
                priceInclTax += value;
            } else if (key === 'optionsPriceInclTax') {
                priceInclTax += value * (100 + currentTax) / 100;
            } else {
                price += parseFloat(value);
                oldPrice += parseFloat(value);
            }
        });

        return [price, nonTaxable, oldPrice, priceInclTax];
    }

    reload() {
        let price;
        let formattedPrice;
        const optionPrices = this.getOptionPrices();
        const nonTaxable = optionPrices[1];
        const optionOldPrice = optionPrices[2];
        const priceInclTax = optionPrices[3];
        const baseOptionPrices = optionPrices[0];

        Object.entries(this.containers).forEach(([key, containerId]) => {
            let _productPrice;
            let _plusDisposition;
            let _minusDisposition;
            let _priceInclTax;
            let excl;
            let incl;
            let tax;

            const container = document.getElementById(containerId);
            if (!container) {
                containerId = `product-price-weee-${this.productId}`;
            }

            if (container) {
                if (containerId === `old-price-${this.productId}` && this.productOldPrice !== this.productPrice) {
                    _productPrice = this.productOldPrice;
                    _plusDisposition = this.oldPlusDisposition;
                    _minusDisposition = this.oldMinusDisposition;
                } else {
                    _productPrice = this.productPrice;
                    _plusDisposition = this.plusDisposition;
                    _minusDisposition = this.minusDisposition;
                }
                _priceInclTax = priceInclTax;

                if (containerId === `old-price-${this.productId}` && optionOldPrice !== undefined) {
                    price = optionOldPrice + parseFloat(_productPrice);
                } else if (this.specialTaxPrice === 'true' && this.priceInclTax !== undefined && this.priceExclTax !== undefined) {
                    price = baseOptionPrices + parseFloat(this.priceExclTax);
                    _priceInclTax += this.priceInclTax;
                } else {
                    price = baseOptionPrices + parseFloat(_productPrice);
                    _priceInclTax += parseFloat(_productPrice) * (100 + this.currentTax) / 100;
                }

                if (this.specialTaxPrice === 'true') {
                    excl = price;
                    incl = _priceInclTax;
                } else if (this.includeTax === 'true') {
                    // tax = tax included into product price by admin
                    tax = price / (100 + this.defaultTax) * this.defaultTax;
                    excl = price - tax;
                    incl = excl * (1 + (this.currentTax / 100));
                } else {
                    tax = price * (this.currentTax / 100);
                    excl = price;
                    incl = excl + tax;
                }

                let subPrice = 0;
                let subPriceincludeTax = 0;
                Object.values(this.customPrices).forEach(el => {
                    if (el.excludeTax && el.includeTax) {
                        subPrice += parseFloat(el.excludeTax);
                        subPriceincludeTax += parseFloat(el.includeTax);
                    } else {
                        subPrice += parseFloat(el.price);
                        subPriceincludeTax += parseFloat(el.price);
                    }
                });
                excl += subPrice;
                incl += subPriceincludeTax;

                if (typeof this.exclDisposition === 'undefined') {
                    excl += parseFloat(_plusDisposition);
                }

                incl += parseFloat(_plusDisposition) + parseFloat(this.plusDispositionTax);
                excl -= parseFloat(_minusDisposition);
                incl -= parseFloat(_minusDisposition);

                // adding nontaxable part of options
                excl += parseFloat(nonTaxable);
                incl += parseFloat(nonTaxable);

                if (containerId === `price-including-tax-${this.productId}`) {
                    price = incl;
                } else if (containerId === `price-excluding-tax-${this.productId}`) {
                    price = excl;
                } else if (containerId === `old-price-${this.productId}`) {
                    if (this.showIncludeTax || this.showBothPrices) {
                        price = incl;
                    } else {
                        price = excl;
                    }
                } else {
                    if (this.showIncludeTax) {
                        price = incl;
                    } else {
                        price = excl;
                    }
                }

                if (price < 0) price = 0;

                if (price > 0 || this.displayZeroPrice) {
                    formattedPrice = this.formatPrice(price);
                } else {
                    formattedPrice = '';
                }

                const priceElement = container.querySelector('.price');
                if (priceElement) {
                    priceElement.innerHTML = formattedPrice;
                    const duplicateContainer = document.getElementById(containerId + this.duplicateIdSuffix);
                    if (duplicateContainer) {
                        const duplicatePriceElement = duplicateContainer.querySelector('.price');
                        if (duplicatePriceElement) {
                            duplicatePriceElement.innerHTML = formattedPrice;
                        }
                    }
                } else {
                    container.innerHTML = formattedPrice;
                    const duplicateContainer = document.getElementById(containerId + this.duplicateIdSuffix);
                    if (duplicateContainer) {
                        duplicateContainer.innerHTML = formattedPrice;
                    }
                }
            }
        });

        if (typeof skipTierPricePercentUpdate === "undefined" && this.tierPrices) {
            for (let i = 0; i < this.tierPrices.length; i++) {
                document.querySelectorAll('.benefit').forEach(el => {
                    const parsePrice = (html) => {
                        const format = this.priceFormat;
                        const decimalSymbol = format.decimalSymbol === undefined ? "," : format.decimalSymbol;
                        const regexStr = `[^0-9-${decimalSymbol}]`;
                        // remove all characters except number and decimal symbol
                        html = html.replace(new RegExp(regexStr, 'g'), '');
                        html = html.replace(decimalSymbol, '.');
                        return parseFloat(html);
                    };

                    const updateTierPriceInfo = (priceEl, tierPriceDiff, tierPriceEl, benefitEl) => {
                        if (!tierPriceEl) {
                            // tierPrice is not shown, e.g., MAP, no need to update the tier price info
                            return;
                        }
                        const price = parsePrice(priceEl.innerHTML);
                        const tierPrice = price + tierPriceDiff;

                        tierPriceEl.innerHTML = this.formatPrice(tierPrice);

                        benefitEl.querySelectorAll(`.percent.tier-${i}`).forEach(percentEl => {
                            percentEl.innerHTML = Math.ceil(100 - ((100 / price) * tierPrice));
                        });
                    };

                    const tierPriceElArray = document.querySelectorAll(`.tier-price.tier-${i} .price`);
                    if (this.showBothPrices) {
                        const containerExclTax = document.getElementById(this.containers[3]);
                        const tierPriceExclTaxDiff = this.tierPrices[i];
                        const tierPriceExclTaxEl = tierPriceElArray[0];
                        updateTierPriceInfo(containerExclTax, tierPriceExclTaxDiff, tierPriceExclTaxEl, el);

                        const containerInclTax = document.getElementById(this.containers[2]);
                        const tierPriceInclTaxDiff = this.tierPricesInclTax[i];
                        const tierPriceInclTaxEl = tierPriceElArray[1];
                        updateTierPriceInfo(containerInclTax, tierPriceInclTaxDiff, tierPriceInclTaxEl, el);
                    } else if (this.showIncludeTax) {
                        const container = document.getElementById(this.containers[0]);
                        const tierPriceInclTaxDiff = this.tierPricesInclTax[i];
                        const tierPriceInclTaxEl = tierPriceElArray[0];
                        updateTierPriceInfo(container, tierPriceInclTaxDiff, tierPriceInclTaxEl, el);
                    } else {
                        const container = document.getElementById(this.containers[0]);
                        const tierPriceExclTaxDiff = this.tierPrices[i];
                        const tierPriceExclTaxEl = tierPriceElArray[0];
                        updateTierPriceInfo(container, tierPriceExclTaxDiff, tierPriceExclTaxEl, el);
                    }
                });
            }
        }
    }

    formatPrice(price) {
        return formatCurrency(price, this.priceFormat);
    }
}
