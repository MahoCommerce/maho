<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Resource_Order_Invoice_Collection extends Mage_Sales_Model_Resource_Order_Collection_Abstract
{
    /**
     * @var string
     */
    protected $_eventPrefix    = 'sales_order_invoice_collection';

    /**
     * @var string
     */
    protected $_eventObject    = 'order_invoice_collection';

    /**
     * Order field for setOrderFilter
     *
     * @var string
     */
    protected $_orderField     = 'order_id';

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_invoice');
    }

    /**
     * Used to emulate after load functionality for each item without loading them
     * @return $this
     */
    #[\Override]
    protected function _afterLoad()
    {
        $this->walk('afterLoad');
        return $this;
    }
}
