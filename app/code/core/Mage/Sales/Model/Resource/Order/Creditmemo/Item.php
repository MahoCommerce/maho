<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Resource_Order_Creditmemo_Item extends Mage_Sales_Model_Resource_Order_Abstract
{
    /** @var string */
    protected $_eventPrefix    = 'sales_order_creditmemo_item_resource';

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/creditmemo_item', 'entity_id');
    }
}
