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
 * Hosted Pro iframe block
 *
 * @category   Mage
 * @package    Mage_Paypal
 */
class Mage_Paypal_Block_Hosted_Pro_Iframe extends Mage_Paypal_Block_Iframe
{
    /**
     * Set payment method code
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_paymentMethodCode = Mage_Paypal_Model_Config::METHOD_HOSTEDPRO;
    }

    /**
     * Get iframe action URL
     * @return string
     */
    #[\Override]
    public function getFrameActionUrl()
    {
        return $this->_getOrder()
            ->getPayment()
            ->getAdditionalInformation('secure_form_url');
    }
}
