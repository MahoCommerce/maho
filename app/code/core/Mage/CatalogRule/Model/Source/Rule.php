<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Option source listing catalog price rules, used by the On Sale widget rule_id parameter.
 */
class Mage_CatalogRule_Model_Source_Rule
{
    public function toOptionArray(): array
    {
        $options = [
            ['value' => '', 'label' => Mage::helper('catalogrule')->__('-- All Active Rules --')],
        ];

        /** @var Mage_CatalogRule_Model_Resource_Rule_Collection $collection */
        $collection = Mage::getResourceModel('catalogrule/rule_collection');
        $collection->addFieldToSelect(['rule_id', 'name'])
            ->setOrder('name', Maho\Db\Select::SQL_ASC);

        foreach ($collection as $rule) {
            $options[] = [
                'value' => $rule->getId(),
                'label' => $rule->getName(),
            ];
        }

        return $options;
    }
}
