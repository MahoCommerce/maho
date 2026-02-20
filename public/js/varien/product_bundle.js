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

Product.Bundle = class {
    constructor(config) {
        this.config = config;

        if (config.defaultValues) {
            for (const option in config.defaultValues) {
                if (this.config.options[option].isMulti) {
                    const selected = config.defaultValues[option].map(value => value);
                    this.config.selected[option] = selected;
                } else {
                    this.config.selected[option] = [String(config.defaultValues[option])];
                }
            }
        }
        this.reloadPrice();
    }

    changeSelection(selection) {
        const parts = selection.id.split('-');
        if (this.config.options[parts[2]].isMulti) {
            let selected = [];
            if (selection.tagName === 'SELECT') {
                selected = Array.from(selection.options)
                    .filter(option => option.selected && option.value !== '')
                    .map(option => option.value);
            } else if (selection.tagName === 'INPUT') {
                const selector = `${parts[0]}-${parts[1]}-${parts[2]}`;
                selected = Array.from(document.querySelectorAll(`.${selector}`))
                    .filter(item => item.checked && item.value !== '')
                    .map(item => item.value);
            }
            this.config.selected[parts[2]] = selected;
        } else {
            this.config.selected[parts[2]] = selection.value !== '' ? [selection.value] : [];
            this.populateQty(parts[2], selection.value);

            const tierPriceElement = document.getElementById(`bundle-option-${parts[2]}-tier-prices`);
            let tierPriceHtml = '';
            if (selection.value !== '' && Number(this.config.options[parts[2]].selections[selection.value].customQty) === 1) {
                tierPriceHtml = this.config.options[parts[2]].selections[selection.value].tierPriceHtml;
            }
            tierPriceElement.innerHTML = tierPriceHtml;
        }
        this.reloadPrice();
    }

    reloadPrice() {
        let calculatedPrice = 0;
        let dispositionPrice = 0;
        let includeTaxPrice = 0;

        Object.entries(this.config.selected).forEach(([option, values]) => {
            if (this.config.options[option]) {
                values.forEach(value => {
                    const [price, disposition, taxPrice] = this.selectionPrice(option, value);
                    calculatedPrice += Number(price);
                    dispositionPrice += Number(disposition);
                    includeTaxPrice += Number(taxPrice);
                });
            }
        });

        if (taxCalcMethod === CACL_TOTAL_BASE) {
            const calculatedPriceFormatted = calculatedPrice.toFixed(10);
            const includeTaxPriceFormatted = includeTaxPrice.toFixed(10);
            const tax = includeTaxPriceFormatted - calculatedPriceFormatted;
            calculatedPrice = includeTaxPrice - Math.round(tax * 100) / 100;
        }

        if (this.config.priceType === '0') {
            calculatedPrice = Math.round(calculatedPrice * 100) / 100;
            dispositionPrice = Math.round(dispositionPrice * 100) / 100;
            includeTaxPrice = Math.round(includeTaxPrice * 100) / 100;
        }

        const event = new CustomEvent('bundle:reload-price', {
            detail: {
                price: calculatedPrice,
                priceInclTax: includeTaxPrice,
                dispositionPrice: dispositionPrice,
                bundle: this
            },
            bubbles: true
        });
        document.dispatchEvent(event);

        if (!event.detail.noReloadPrice) {
            optionsPrice.specialTaxPrice = 'true';
            optionsPrice.changePrice('bundle', calculatedPrice);
            optionsPrice.changePrice('nontaxable', dispositionPrice);
            optionsPrice.changePrice('priceInclTax', includeTaxPrice);
            optionsPrice.reload();
        }

        return calculatedPrice;
    }

    selectionPrice(optionId, selectionId) {
        if (selectionId === '' || selectionId === 'none' || !this.config.options[optionId].selections[selectionId]) {
            return [0, 0, 0];
        }

        let qty = null;
        let tierPriceInclTax, tierPriceExclTax;
        const selection = this.config.options[optionId].selections[selectionId];

        if (Number(selection.customQty) === 1 && !this.config.options[optionId].isMulti) {
            const qtyInput = document.getElementById(`bundle-option-${optionId}-qty-input`);
            qty = qtyInput ? qtyInput.value : 1;
        } else {
            qty = selection.qty;
        }

        let price;
        if (this.config.priceType === '0') {
            price = selection.price;
            const { tierPrice } = selection;

            tierPrice.forEach(tier => {
                if (Number(tier.price_qty) <= qty && Number(tier.price) <= price) {
                    price = tier.price;
                    tierPriceInclTax = tier.priceInclTax;
                    tierPriceExclTax = tier.priceExclTax;
                }
            });
        } else {
            price = selection.priceType === '0' ?
                selection.priceValue :
                (this.config.basePrice * selection.priceValue) / 100;
        }

        const disposition = selection.plusDisposition + selection.minusDisposition;

        if (this.config.specialPrice) {
            const newPrice = (price * this.config.specialPrice) / 100;
            price = Math.min(newPrice, price);
        }

        let priceInclTax;
        if (tierPriceInclTax !== undefined && tierPriceExclTax !== undefined) {
            priceInclTax = tierPriceInclTax;
            price = tierPriceExclTax;
        } else if (selection.priceInclTax !== undefined) {
            priceInclTax = selection.priceInclTax;
            price = selection.priceExclTax !== undefined ? selection.priceExclTax : selection.price;
        } else {
            priceInclTax = price;
        }

        if (this.config.priceType === '1' || taxCalcMethod === CACL_TOTAL_BASE) {
            return [price * qty, disposition * qty, priceInclTax * qty];
        } else if (taxCalcMethod === CACL_UNIT_BASE) {
            price = Math.round(price * 100) / 100;
            disposition = Math.round(disposition * 100) / 100;
            priceInclTax = Math.round(priceInclTax * 100) / 100;
            return [price * qty, disposition * qty, priceInclTax * qty];
        } else {
            return [
                Math.round(price * qty * 100) / 100,
                Math.round(disposition * qty * 100) / 100,
                Math.round(priceInclTax * qty * 100) / 100
            ];
        }
    }

    populateQty(optionId, selectionId) {
        if (selectionId === '' || selectionId === 'none') {
            this.showQtyInput(optionId, '0', false);
            return;
        }

        const selection = this.config.options[optionId].selections[selectionId];
        this.showQtyInput(optionId, selection.qty, Number(selection.customQty) === 1);
    }

    showQtyInput(optionId, value, canEdit) {
        const elem = document.getElementById(`bundle-option-${optionId}-qty-input`);
        elem.value = value;
        elem.disabled = !canEdit;
        elem.classList.toggle('qty-disabled', !canEdit);
    }

    changeOptionQty(element, event) {
        const checkQty = !(event && (event.keyCode === 8 || event.keyCode === 46));

        if (checkQty && (Number(element.value) === 0 || isNaN(Number(element.value)))) {
            element.value = 1;
        }

        const [, , optionId] = element.id.split('-');

        if (!this.config.options[optionId].isMulti) {
            const selectionId = this.config.selected[optionId][0];
            this.config.options[optionId].selections[selectionId].qty = Number(element.value);
            this.reloadPrice();
        }
    }

    validationCallback(elmId, result) {
        const element = document.getElementById(elmId);
        if (!element) return;

        const container = element.closest('ul.options-list');
        if (container) {
            container.classList.remove('validation-passed', 'validation-failed');
            container.classList.add(result === 'failed' ? 'validation-failed' : 'validation-passed');
        }
    }
}
