<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Quote_Address_Total_Nominal_Recurring_Initial extends Mage_Sales_Model_Quote_Address_Total_Nominal_RecurringAbstract
{
    /**
     * Custom row total/profile keys
     *
     * @var string
     */
    protected $_itemRowTotalKey = 'recurring_initial_fee';
    protected $_profileDataKey = 'init_amount';

    /**
     * Get initial fee label
     *
     * @return string
     */
    #[\Override]
    public function getLabel()
    {
        return Mage::helper('sales')->__('Initial Fee');
    }
}
