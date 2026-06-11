<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

class Mage_Tax_Model_Resource_Report_Tax_Updatedat extends Mage_Tax_Model_Resource_Report_Tax_Createdat
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/tax_order_aggregated_updated', 'id');
    }

    /**
     * Aggregate Tax data by order updated at
     *
     * @param mixed $from
     * @param mixed $to
     * @return $this
     */
    #[\Override]
    public function aggregate($from = null, $to = null)
    {
        return $this->_aggregateByOrder('updated_at', $from, $to);
    }
}
