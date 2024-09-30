<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Remember Me block
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class Mage_Customer_Block_Form_Remember extends Mage_Core_Block_Template
{
    /**
     * Prevent rendering if Persistent disabled
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        return '';
    }

    /**
     * Is "Remember Me" checked
     *
     * @return bool
     */
    public function isRememberMeChecked()
    {
        return false;
    }
}
