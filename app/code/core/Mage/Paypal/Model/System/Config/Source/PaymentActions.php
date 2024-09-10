<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Source model for available payment actions
 *
 * @category   Mage
 * @package    Mage_Paypal
 */
class Mage_Paypal_Model_System_Config_Source_PaymentActions
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $configModel = Mage::getModel('paypal/config');
        return $configModel->getPaymentActions();
    }
}
