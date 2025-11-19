<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Block_Payflow_Advanced_Iframe extends Mage_Paypal_Block_Payflow_Link_Iframe
{
    /**
     * Set payment method code
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_paymentMethodCode = Mage_Paypal_Model_Config::METHOD_PAYFLOWADVANCED;
    }

    /**
     * Get frame action URL
     * @return string
     */
    #[\Override]
    public function getFrameActionUrl()
    {
        return $this->getTransactionUrl() . '?SECURETOKEN=' . $this->getSecureToken() . '&SECURETOKENID='
            . $this->getSecureTokenId() . '&MODE=' . ($this->isTestMode() ? 'TEST' : 'LIVE');
    }

    /**
     * Check sandbox mode
     *
     * @return bool
     */
    #[\Override]
    public function isTestMode()
    {
        $mode = Mage::helper('payment')
            ->getMethodInstance(Mage_Paypal_Model_Config::METHOD_PAYFLOWADVANCED)
            ->getConfigData('sandbox_flag');
        return (bool) $mode;
    }
}
