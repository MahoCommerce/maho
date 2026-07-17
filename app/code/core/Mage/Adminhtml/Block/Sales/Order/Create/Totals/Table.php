<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Sales_Order_Create_Totals_Table extends Mage_Adminhtml_Block_Template
{
    protected $_websiteCollection = null;

    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_create_totals_table');
    }
}
