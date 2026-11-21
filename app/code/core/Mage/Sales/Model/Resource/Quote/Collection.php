<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Resource_Quote_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote');
    }

    #[\Override]
    protected function _afterLoad()
    {
        foreach ($this->_items as $quote) {
            if ($quote instanceof Mage_Sales_Model_Quote) {
                $quote->rememberLoadedCheckoutFields();
            }
        }
        return parent::_afterLoad();
    }

    #[\Override]
    public function fetchItem()
    {
        $quote = parent::fetchItem();
        if ($quote instanceof Mage_Sales_Model_Quote) {
            $quote->rememberLoadedCheckoutFields();
        }
        return $quote;
    }
}
