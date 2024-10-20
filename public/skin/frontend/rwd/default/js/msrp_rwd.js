/**
 * Maho
 *
 * @category   design
 * @package    rwd_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
Catalog.Map.showHelp = Catalog.Map.showHelp.wrap(function (parent, event) {
    var helpBox = document.getElementById('map-popup');
    var bodyNode = document.getElementsByTagName('body')[0];
    parent(event);
    
    if (helpBox && this != Catalog.Map && Catalog.Map.active != this.link) {
        helpBox.classList.remove('map-popup-right');
        helpBox.classList.remove('map-popup-left');
        if (Element.getWidth(bodyNode) < event.pageX + (Element.getWidth(helpBox) / 2)) {
            helpBox.classList.add('map-popup-left');
        } else if (event.pageX - (Element.getWidth(helpBox) / 2) < 0) {
            helpBox.classList.add('map-popup-right');
        }
    }
});
