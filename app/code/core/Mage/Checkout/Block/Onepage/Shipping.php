<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Block_Onepage_Shipping extends Mage_Checkout_Block_Onepage_Abstract
{
    /**
     * Sales Quote Shipping Address instance
     *
     * @var Mage_Sales_Model_Quote_Address|null
     */
    protected $_address = null;

    /**
     * Initialize shipping address step
     */
    #[\Override]
    protected function _construct()
    {
        $this->getCheckout()->setStepData('shipping', [
            'label'     => Mage::helper('checkout')->__('Shipping Information'),
            'is_show'   => $this->isShow(),
        ]);

        parent::_construct();
    }

    /**
     * Return checkout method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->getQuote()->getCheckoutMethod();
    }

    /**
     * Return Sales Quote Address model (shipping address)
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getAddress()
    {
        if (is_null($this->_address)) {
            $this->_address = $this->getQuote()->getShippingAddress();
        }

        return $this->_address;
    }

    /**
     * Retrieve is allow and show block
     *
     * @return bool
     */
    #[\Override]
    public function isShow()
    {
        return !$this->getQuote()->isVirtual();
    }
}
