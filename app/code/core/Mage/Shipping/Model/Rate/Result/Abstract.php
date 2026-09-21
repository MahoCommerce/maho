<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

/**
 * Class Mage_Shipping_Model_Rate_Result_Abstract
 *
 * @package    Mage_Shipping
 */

class Mage_Shipping_Model_Rate_Result_Abstract extends \Maho\DataObject
{
    public function getCarrier(): ?string
    {
        return $this->getData('carrier');
    }

    public function getCarrierTitle(): ?string
    {
        return $this->getData('carrier_title');
    }

    public function getMethodTitle(): ?string
    {
        return $this->getData('method_title');
    }

    public function getMethodDescription(): ?string
    {
        return $this->getData('method_description');
    }

    public function getMethodLogo(): ?string
    {
        return $this->getData('method_logo');
    }

    public function setMethodLogo(?string $value): static
    {
        return $this->setData('method_logo', $value);
    }
}
