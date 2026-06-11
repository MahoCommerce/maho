<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Resource_Report_Order_Updatedat_Collection extends Mage_Sales_Model_Resource_Report_Order_Collection
{
    /**
     * Aggregated Data Table
     *
     * @var string
     */
    protected $_aggregationTable = 'sales/order_aggregated_updated';
}
