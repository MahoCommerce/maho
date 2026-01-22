/**
 * Maho
 *
 * @package     base_default
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class ConfigurableSwatchPrices {
    constructor(config) {
        this.swatchesPrices = [];
        this.generalConfig = config.generalConfig;
        this.products = config.products;
        this.addObservers();
    }

    addObservers() {
        document.addEventListener('click', (event) => {
            const swatchLink = event.target.closest('.swatch-link');
            if (swatchLink) {
                this.onSwatchClick(event);
            }
        });
    }

    onSwatchClick(e) {
        const element = e.target;
        const swatchElement = element.closest('[data-product-id]');
        const productId = parseInt(swatchElement.getAttribute('data-product-id'), 10);
        const swatchLabel = swatchElement.getAttribute('data-option-label');
        const optionsPrice = this.optionsPrice(productId);
        const swatchTarget = this.getSwatchPriceInfo(productId, swatchLabel);

        if (swatchTarget) {
            optionsPrice.changePrice('config', { price: swatchTarget.price, oldPrice: swatchTarget.oldPrice });
            optionsPrice.reload();
        }
    }

    getSwatchPriceInfo(productId, swatchLabel) {
        const productInfo = this.products[productId];
        return productInfo && productInfo.swatchPrices[swatchLabel] ? productInfo.swatchPrices[swatchLabel] : 0;
    }

    optionsPrice(productId) {
        if (this.swatchesPrices[productId]) {
            return this.swatchesPrices[productId];
        }
        this.swatchesPrices[productId] = new Product.OptionsPrice(this.getProductConfig(productId));
        return this.swatchesPrices[productId];
    }

    getProductConfig(productId) {
        return {
            ...this.generalConfig,
            ...this.products[productId]
        };
    }
}
