/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

var Product = Product ?? {};

Product.Downloadable = class {
    constructor(config) {
        this.config = config;
        this.reloadPrice();
        document.addEventListener('DOMContentLoaded', this.reloadPrice.bind(this));
    }

    reloadPrice() {
        let price = 0;
        for (const el of document.querySelectorAll('.product-downloadable-link')) {
            if (this.config[el.value] && el.checked) {
                price += parseFloat(this.config[el.value]);
            }
        }
        try {
            // Assuming optionsPrice is a global object
            const _displayZeroPrice = optionsPrice.displayZeroPrice;
            optionsPrice.displayZeroPrice = false;
            optionsPrice.changePrice('downloadable', price);
            optionsPrice.reload();
            optionsPrice.displayZeroPrice = _displayZeroPrice;
        } catch (error) {
        }
    }
}
