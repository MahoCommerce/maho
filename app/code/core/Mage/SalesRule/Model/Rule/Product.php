<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

class Mage_SalesRule_Model_Rule_Product extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('salesrule/rule_product');
    }
}
