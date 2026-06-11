<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
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
