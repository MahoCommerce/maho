<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Model_System_Config_Source_MergeMode
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Maho_CatalogLinkRule_Model_Processor::MODE_REPLACE,
                'label' => Mage::helper('cataloglinkrule')->__('Replace (delete existing links, insert rule results)'),
            ],
            [
                'value' => Maho_CatalogLinkRule_Model_Processor::MODE_MERGE,
                'label' => Mage::helper('cataloglinkrule')->__('Merge (keep existing links, append rule results)'),
            ],
        ];
    }
}
