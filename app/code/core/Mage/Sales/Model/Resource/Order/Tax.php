<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Resource_Order_Tax extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_tax', 'tax_id');
    }
}
