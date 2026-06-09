<?php

/**
 * Maho
 *
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
