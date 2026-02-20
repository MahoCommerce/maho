<?php

/**
 * Maho
 *
 * @package    Mage_Page
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this setWelcome(string $value)
 */
class Mage_Page_Block_Html_Welcome extends Mage_Core_Block_Template
{
    /**
     * Get customer session
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Get block message
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (empty($this->_data['welcome'])) {
            if (Mage::isInstalled() && $this->_getSession()->isLoggedIn()) {
                $this->_data['welcome'] = $this->__('Welcome, %s!', $this->escapeHtml($this->_getSession()->getCustomer()->getName()));
            } else {
                $this->_data['welcome'] = $this->escapeHtmlAsObject((string) Mage::getStoreConfig('design/header/welcome'));
            }
        }

        return $this->_data['welcome'];
    }

    /**
     * Get tags array for saving cache
     *
     * @return array
     */
    #[\Override]
    public function getCacheTags()
    {
        if ($this->_getSession()->isLoggedIn()) {
            $this->addModelTags($this->_getSession()->getCustomer());
        }

        return parent::getCacheTags();
    }
}
