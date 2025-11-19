<?php

/**
 * Maho
 *
 * @package    Mage_PaypalUk
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_PaypalUk_Model_Api_Express_Nvp extends Mage_PaypalUk_Model_Api_Nvp
{
    /**
     * Set specific data when negative line item case
     */
    #[\Override]
    protected function _setSpecificForNegativeLineItems()
    {
        $paypalNvp = new Mage_Paypal_Model_Api_Nvp();
        $this->_setExpressCheckoutResponse = $paypalNvp->_setExpressCheckoutResponse;
        $index = array_search('PPREF', $this->_doExpressCheckoutPaymentResponse);
        if ($index !== false) {
            unset($this->_doExpressCheckoutPaymentResponse[$index]);
        }
        $this->_doExpressCheckoutPaymentResponse[] = 'PAYMENTINFO_0_TRANSACTIONID';
        $this->_requiredResponseParams[self::DO_EXPRESS_CHECKOUT_PAYMENT][] = 'PAYMENTINFO_0_TRANSACTIONID';
    }
}
