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
        $value = $this->getData('carrier');
        return $value === null ? null : (string) $value;
    }

    public function getCarrierTitle(): ?string
    {
        $value = $this->getData('carrier_title');
        return $value === null ? null : (string) $value;
    }

    public function getMethodTitle(): ?string
    {
        $value = $this->getData('method_title');
        return $value === null ? null : (string) $value;
    }

    public function getMethodDescription(): ?string
    {
        $value = $this->getData('method_description');
        return $value === null ? null : (string) $value;
    }

    public function getMethodLogo(): ?string
    {
        $value = $this->getData('method_logo');
        return $value === null ? null : (string) $value;
    }

    public function setMethodLogo(?string $value): static
    {
        return $this->setData('method_logo', $value);
    }
}
