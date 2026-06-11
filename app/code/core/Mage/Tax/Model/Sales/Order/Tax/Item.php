<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

/**
 * @method Mage_Tax_Model_Resource_Sales_Order_Tax_Item _getResource()
 * @method Mage_Tax_Model_Resource_Sales_Order_Tax_Item getResource()
 * @method Mage_Tax_Model_Resource_Sales_Order_Tax_Item_Collection getCollection()
 */

class Mage_Tax_Model_Sales_Order_Tax_Item extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/sales_order_tax_item');
    }
}
