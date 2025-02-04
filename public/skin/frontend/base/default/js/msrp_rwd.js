/**
 * Maho
 *
 * @category   design
 * @package    rwd_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const originalShowHelp = Catalog.Map.showHelp;
Catalog.Map.showHelp = function(event) {
    const helpBox = document.getElementById('map-popup');
    const bodyNode = document.getElementsByTagName('body')[0];

    // Call the original function
    originalShowHelp.call(this, event);

    if (helpBox && this != Catalog.Map && Catalog.Map.active != this.link) {
        helpBox.classList.remove('map-popup-right');
        helpBox.classList.remove('map-popup-left');

        // Replace Element.getWidth with standard JavaScript
        const helpBoxWidth = helpBox.offsetWidth;
        const bodyWidth = bodyNode.offsetWidth;

        if (bodyWidth < event.pageX + (helpBoxWidth / 2)) {
            helpBox.classList.add('map-popup-left');
        } else if (event.pageX - (helpBoxWidth / 2) < 0) {
            helpBox.classList.add('map-popup-right');
        }
    }
};