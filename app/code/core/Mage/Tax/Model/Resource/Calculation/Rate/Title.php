<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

class Mage_Tax_Model_Resource_Calculation_Rate_Title extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/tax_calculation_rate_title', 'tax_calculation_rate_title_id');
    }

    /**
     * Delete title by rate identifier
     *
     * @param int $rateId
     * @return $this
     */
    public function deleteByRateId($rateId)
    {
        $conn = $this->_getWriteAdapter();
        $where = $conn->quoteInto('tax_calculation_rate_id = ?', (int) $rateId);
        $conn->delete($this->getMainTable(), $where);

        return $this;
    }
}
