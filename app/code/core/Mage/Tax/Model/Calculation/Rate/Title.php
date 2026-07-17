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
 *
 * @method int getTaxCalculationRateId()
 * @method $this setTaxCalculationRateId(int $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getValue()
 * @method $this setValue(string $value)
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
}
