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

use Maho\ApiPlatform\Trait\AdminLocaleTrait;
use Maho\ApiPlatform\Trait\CacheTrait;

trait ConditionMetadataTrait
{
    use AdminLocaleTrait;
    use CacheTrait;

    private int $conditionMetadataLifetime = 900;

    private ?\Mage_Rule_Model_Condition_Metadata $conditionMetadataModel = null;

    protected function conditionMetadataModel(): \Mage_Rule_Model_Condition_Metadata
    {
        return $this->conditionMetadataModel ??= new \Mage_Rule_Model_Condition_Metadata(
            \Mage::getModel('catalogrule/rule'),
            ['conditions' => CatalogPriceRule::ROOT_CONDITIONS],
            'catalogrule',
        );
    }

    /**
     * Return the metadata document of $locale: version, locale, roots and types.
     *
     * @return array<string, mixed>
     */
    protected function conditionMetadata(string $locale): array
    {
        return $this->remember(
            'API_CATALOG_PRICE_RULE_CONDITION_METADATA_' . $locale,
            [\Mage_Core_Model_Config::CACHE_TAG, \Mage_Eav_Model_Entity_Attribute::CACHE_TAG],
            $this->conditionMetadataLifetime,
            fn(): array => $this->buildConditionMetadata($locale),
            fn(array $document): array => $document,
            fn(array $document): array => $document,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConditionMetadata(string $locale): array
    {
        $document = \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, fn(): array => $this->conditionMetadataModel()->build());

        return ['version' => hash('sha256', (string) json_encode($document)), 'locale' => $locale] + $document;
    }
}
