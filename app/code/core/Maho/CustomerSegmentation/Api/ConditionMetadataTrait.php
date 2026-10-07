<?php

/**
 * Give the condition metadata of customer segments to the providers and the processor of the segment API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use Maho\ApiPlatform\Trait\AdminLocaleTrait;
use Maho\ApiPlatform\Trait\CacheTrait;

trait ConditionMetadataTrait
{
    use AdminLocaleTrait;
    use CacheTrait;

    /**
     * Some value options come from the database without a cache tag, so the document expires after this time.
     */
    private int $conditionMetadataLifetime = 900;

    private ?\Mage_Rule_Model_Condition_Metadata $conditionMetadataModel = null;

    protected function conditionMetadataModel(): \Mage_Rule_Model_Condition_Metadata
    {
        return $this->conditionMetadataModel ??= new \Mage_Rule_Model_Condition_Metadata(
            \Mage::getModel('customersegmentation/segment'),
            ['conditions' => CustomerSegment::ROOT_CONDITIONS],
            'customersegmentation',
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
            'API_CUSTOMER_SEGMENT_CONDITION_METADATA_' . $locale,
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
