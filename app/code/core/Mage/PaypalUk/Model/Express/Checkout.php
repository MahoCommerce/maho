<?php

/**
 * Maho
 *
 * @package    Mage_PaypalUk
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Wrapper that performs Paypal Express and Checkout communication
 * Use current Paypal Express method instance
 *
 * @package    Mage_PaypalUk
 */
class Mage_PaypalUk_Model_Express_Checkout extends Mage_Paypal_Model_Express_Checkout
{
    /**
     * Api Model Type
     *
     * @var string
     */
    protected $_apiType = 'paypaluk/api_express_nvp';

    /**
     * Payment method tpye
     * @var string
     */
    protected $_methodType = Mage_Paypal_Model_Config::METHOD_WPP_PE_EXPRESS;

    /**
     * Set shipping method to quote, if needed
     * @param string $methodCode
     */
    #[\Override]
    public function updateShippingMethod($methodCode)
    {
        parent::updateShippingMethod($methodCode);
        $this->_quote->save();
    }
}
