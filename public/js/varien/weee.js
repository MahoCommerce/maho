/**
 * Maho
 *
 * @category    Varien
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2023 The OpenMage Contributors (https://openmage.org)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

function taxToggle(details, switcher, expandedClassName)
{
    var detailsElement = document.getElementById(details);
    var switcherElement = document.getElementById(switcher);

    if (detailsElement.style.display == 'none') {
        detailsElement.style.display = 'block';
        switcherElement.classList.add(expandedClassName);
    } else {
        detailsElement.style.display = 'none';
        switcherElement.classList.remove(expandedClassName);
    }
}
