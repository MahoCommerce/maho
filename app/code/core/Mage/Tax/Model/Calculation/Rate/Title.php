<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

/**
 * @method Mage_Tax_Model_Resource_Calculation_Rate_Title _getResource()
 * @method Mage_Tax_Model_Resource_Calculation_Rate_Title getResource()
 * @method Mage_Tax_Model_Resource_Calculation_Rate_Title_Collection getCollection()
 */
class Mage_Tax_Model_Calculation_Rate_Title extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/calculation_rate_title');
    }

    /**
     * @param int $rateId
     * @return $this
     */
    public function deleteByRateId($rateId)
    {
        $this->getResource()->deleteByRateId($rateId);
        return $this;
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getTaxCalculationRateId(): ?int
    {
        $value = $this->getData('tax_calculation_rate_id');
        return $value === null ? null : (int) $value;
    }

    public function setTaxCalculationRateId(?int $value): static
    {
        return $this->setData('tax_calculation_rate_id', $value);
    }

    public function getValue(): ?string
    {
        $value = $this->getData('value');
        return $value === null ? null : (string) $value;
    }

    public function setValue(?string $value): static
    {
        return $this->setData('value', $value);
    }

}
