<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Resource_Product_Index_Compared_Collection extends Mage_Reports_Model_Resource_Product_Index_Collection_Abstract
{
    /**
     * Retrieve Product Index table name
     *
     * @return string
     */
    #[\Override]
    protected function _getTableName()
    {
        return $this->getTable('reports/compared_product_index');
    }
}
