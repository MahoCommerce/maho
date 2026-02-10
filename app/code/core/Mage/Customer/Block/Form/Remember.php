<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Customer_Block_Form_Remember extends Mage_Core_Block_Template
{
    /**
     * Prevent rendering if Remember me is disabled
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (Mage::getStoreConfigFlag('web/cookie/remember_enabled')) {
            return parent::_toHtml();
        }
        return '';
    }

    /**
     * Is "Remember Me" checked
     *
     * @return bool
     */
    public function isRememberMeChecked()
    {
        return Mage::getStoreConfigFlag('web/cookie/remember_default');
    }
}
