<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

/**
 * Fields:
 * - carrier: ups
 * - carrierTitle: United Parcel Service
 * - method: 2day
 * - methodTitle: UPS 2nd Day Priority
 * - price: $9.40 (cost+handling)
 * - cost: $8.00
 *
 * @package    Mage_Shipping
 */
class Mage_Shipping_Model_Rate_Result_Method extends Mage_Shipping_Model_Rate_Result_Abstract
{
    /**
     * Round shipping carrier's method price
     *
     * @param string|float|int $price
     * @return $this
     */
    public function setPrice($price)
    {
        $this->setData('price', Mage::app()->getStore()->roundPrice($price));
        return $this;
    }

    public function setCarrier(?string $value): static
    {
        return $this->setData('carrier', $value);
    }

    public function setCarrierTitle(?string $value): static
    {
        return $this->setData('carrier_title', $value);
    }

    public function setCost(?float $value): static
    {
        return $this->setData('cost', $value);
    }

    public function getMethod(): ?string
    {
        $value = $this->getData('method');
        return $value === null ? null : (string) $value;
    }

    public function setMethod(?string $value): static
    {
        return $this->setData('method', $value);
    }

    public function setMethodTitle(?string $value): static
    {
        return $this->setData('method_title', $value);
    }

    public function getPrice(): ?float
    {
        $value = $this->getData('price');
        return $value === null ? null : (float) $value;
    }

}
