<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Resource_Order_Creditmemo_Grid_Collection extends Mage_Sales_Model_Resource_Order_Creditmemo_Collection
{
    /**
     * @var string
     */
    protected $_eventPrefix    = 'sales_order_creditmemo_grid_collection';

    /**
     * @var string
     */
    protected $_eventObject    = 'order_creditmemo_grid_collection';

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setMainTable('sales/creditmemo_grid');
    }
}
