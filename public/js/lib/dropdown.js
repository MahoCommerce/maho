/**
 * Maho
 *
 * @category    Mage
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
dropdown = function() {
    var ele = document.getElementById("nav").getElementsByTagName("LI");
    for (var i=0; i<ele.length; i++) {
        ele[i].onmouseover=function() {
            this.className+=" over";
        }
        ele[i].onmouseout=function() {
            this.className=this.className.replace(new RegExp(" over\\b"), "");
        }
    }
}
if (window.attachEvent) window.attachEvent("onload", dropdown);
