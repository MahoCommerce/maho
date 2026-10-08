<?php

/**
 * Give the condition metadata of catalog price rules to the provider and the processor of the catalog price rule API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

namespace Mage\CatalogRule\Api;

trait ConditionMetadataTrait
{
    use \Maho\ApiPlatform\Trait\ConditionMetadataTrait;

    private ?\Mage_Rule_Model_Condition_Metadata $conditionMetadataModel = null;

    #[\Override]
    protected function conditionMetadataModel(): \Mage_Rule_Model_Condition_Metadata
    {
        return $this->conditionMetadataModel ??= new \Mage_Rule_Model_Condition_Metadata(
            \Mage::getModel('catalogrule/rule'),
            ['conditions' => CatalogPriceRule::ROOT_CONDITIONS],
            'catalogrule',
        );
    }

    #[\Override]
    protected function conditionMetadataCacheKey(): string
    {
        return 'API_CATALOG_PRICE_RULE_CONDITION_METADATA';
    }
}
