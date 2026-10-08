<?php

/**
 * Give an API class the condition metadata document of a rule type, with labels in the admin locale of the token.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

trait ConditionMetadataTrait
{
    use AdminLocaleTrait;
    use CacheTrait;

    /**
     * Some value options come from the database without a cache tag, so the document expires after this time.
     */
    private int $conditionMetadataLifetime = 900;

    abstract protected function conditionMetadataModel(): \Mage_Rule_Model_Condition_Metadata;

    /**
     * Return the cache key of the document. The locale is added to it.
     */
    abstract protected function conditionMetadataCacheKey(): string;

    /**
     * Return the parts that the rule type adds to the document, such as the options of the rule fields.
     *
     * @return array<string, mixed>
     */
    protected function extraConditionMetadata(): array
    {
        return [];
    }

    /**
     * Return the metadata document of $locale without the scope part: version, locale, roots, types and the extra parts.
     *
     * @return array<string, mixed>
     */
    protected function conditionMetadata(string $locale): array
    {
        return $this->remember(
            $this->conditionMetadataCacheKey() . '_' . $locale,
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
        $document = \Mage_Rule_Model_Condition_Metadata::runInLocale(
            $locale,
            fn(): array => $this->conditionMetadataModel()->build() + $this->extraConditionMetadata(),
        );

        return ['version' => hash('sha256', (string) json_encode($document)), 'locale' => $locale] + $document;
    }
}
