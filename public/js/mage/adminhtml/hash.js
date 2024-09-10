/**
 * Maho
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * @deprecated
 */
function encode_base64(what)
{
    return btoa(what);
}

/**
 * @deprecated
 */
function decode_base64(what)
{
    return atob(what);
}
