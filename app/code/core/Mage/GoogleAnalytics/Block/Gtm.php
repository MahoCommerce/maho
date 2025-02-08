<?php

/**
 * Maho
 *
 * @package    Mage_GoogleAnalytics
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2023-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * GoogleTagManager Page Block
 *
 * @package    Mage_GoogleAnalytics
 */
class Mage_GoogleAnalytics_Block_Gtm extends Mage_Core_Block_Template
{
    /**
     * @return bool
     */
    protected function _isAvailable()
    {
        return Mage::helper('googleanalytics')->isGoogleTagManagerAvailable();
    }

    /**
     * Render GA tracking scripts
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->_isAvailable()) {
            return '';
        }
        return parent::_toHtml();
    }
}
