<?php

/**
 * Maho
 *
 * @package    Mage_Shipping
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this setCarrier(string $value)
 * @method $this setCarrierTitle(string $value)
 * @method $this setErrorMessage(string $value)
 */
class Mage_Shipping_Model_Rate_Result_Error extends Mage_Shipping_Model_Rate_Result_Abstract
{
    public function getErrorMessage(): string
    {
        if (!$this->getData('error_message')) {
            $this->setData('error_message', Mage::helper('shipping')->__('This shipping method is currently unavailable.'));
        }
        return $this->getData('error_message');
    }
}
