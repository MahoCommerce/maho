<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Paypal_Block_Express_Review_Details extends Mage_Checkout_Block_Cart_Totals
{
    protected $_address;

    /**
     * Return review shipping address
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getAddress()
    {
        if (empty($this->_address)) {
            $this->_address = $this->getQuote()->getShippingAddress();
        }
        return $this->_address;
    }

    /**
     * Return review quote totals
     *
     * @return array
     */
    #[\Override]
    public function getTotals()
    {
        return $this->getQuote()->getTotals();
    }
}
