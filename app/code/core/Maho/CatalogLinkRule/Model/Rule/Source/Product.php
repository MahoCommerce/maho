<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Model_Rule_Source_Product extends Mage_CatalogRule_Model_Rule_Condition_Product
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('cataloglinkrule/rule_source_product');
    }

    /**
     * Add status and visibility to special attributes for source products
     */
    #[\Override]
    protected function _addSpecialAttributes(array &$attributes): void
    {
        parent::_addSpecialAttributes($attributes);
        $attributes['status'] = Mage::helper('cataloglinkrule')->__('Status');
        $attributes['visibility'] = Mage::helper('cataloglinkrule')->__('Visibility');
    }
}
