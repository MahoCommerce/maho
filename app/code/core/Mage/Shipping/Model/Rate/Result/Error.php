<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

class Mage_Shipping_Model_Rate_Result_Error extends Mage_Shipping_Model_Rate_Result_Abstract
{
    public function getErrorMessage(): string
    {
        if (!$this->getData('error_message')) {
            $this->setData('error_message', Mage::helper('shipping')->__('This shipping method is currently unavailable.'));
        }
        return $this->getData('error_message');
    }

    public function setCarrier(?string $value): static
    {
        return $this->setData('carrier', $value);
    }

    public function setCarrierTitle(?string $value): static
    {
        return $this->setData('carrier_title', $value);
    }

    public function setErrorMessage(?string $value): static
    {
        return $this->setData('error_message', $value);
    }

}
