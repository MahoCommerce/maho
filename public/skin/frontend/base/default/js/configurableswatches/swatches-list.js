/**
 * Maho
 *
 * @package     base_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

var ConfigurableSwatchesList = {
    swatchesByProduct: {},

    init: function() {
        var that = this;
        document.querySelectorAll('.configurable-swatch-list li').forEach(function(element) {
            that.initSwatch(element);
            var swatch = element;
            if (swatch.classList.contains('filter-match')) {
                that.handleSwatchSelect(swatch);
            }
        });
    },

    initSwatch: function(swatch)
    {
        var that = this;
        var $swatch = swatch;
        var productId;
        $swatch.addEventListener('mouseenter', function() {
            /**
             *
             * - Preview the stock status
             **/
            var swatchUl = $swatch.parentNode;
            var xElements = swatchUl.querySelectorAll('.x');
            xElements.forEach(function(element) {
                element.style.display = 'block';
                element.closest('li').classList.add('not-available');
            });
        });
        if (productId = $swatch.dataset.productId) {
            if (typeof this.swatchesByProduct[productId] == 'undefined') {
                this.swatchesByProduct[productId] = [];
            }
            this.swatchesByProduct[productId].push($swatch);

            var anchorElement = $swatch.querySelector('a');
            anchorElement.addEventListener('click', function(e) {
                e.preventDefault();
                that.handleSwatchSelect($swatch);
            });
        }
    },

    handleSwatchSelect: function(swatch) {
        var productId = swatch.dataset.productId;
        var label;
        if (label = swatch.dataset.optionLabel) {
            ConfigurableMediaImages.swapListImageByOption(productId, label);
        }

        Array.from(this.swatchesByProduct[productId]).forEach(function(productSwatch) {
            productSwatch.classList.remove('selected');
        });

        swatch.classList.add('selected');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    ConfigurableSwatchesList.init();
});
