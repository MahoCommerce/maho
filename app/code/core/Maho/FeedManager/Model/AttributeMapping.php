<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Attribute Mapping model
 *
 * Error Handling Pattern:
 * - Getter methods (getConditionsArray, getTransformersArray): Return empty array if JSON invalid, never throw
 * - Setter methods (setConditionsArray, setTransformersArray): Encode to JSON, return self for chaining
 *
 * @method Maho_FeedManager_Model_Resource_AttributeMapping getResource()
 * @method Maho_FeedManager_Model_Resource_AttributeMapping _getResource()
 */
class Maho_FeedManager_Model_AttributeMapping extends Mage_Core_Model_Abstract
{
    public const SOURCE_TYPE_ATTRIBUTE = 'attribute';
    public const SOURCE_TYPE_STATIC = 'static';
    public const SOURCE_TYPE_RULE = 'rule';
    public const SOURCE_TYPE_COMBINED = 'combined';
    public const SOURCE_TYPE_TAXONOMY = 'taxonomy';

    protected $_eventPrefix = 'feedmanager_attribute_mapping';
    protected $_eventObject = 'attribute_mapping';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/attributeMapping');
    }

    /**
     * Get conditions as array
     */
    public function getConditionsArray(): array
    {
        $conditions = $this->getConditions();
        if (empty($conditions)) {
            return [];
        }
        return Mage::helper('core/string')->unserialize($conditions) ?: [];
    }

    /**
     * Set conditions from array
     */
    public function setConditionsArray(array $conditions): self
    {
        $this->setConditions(Mage::helper('core')->jsonEncode($conditions));
        return $this;
    }

    /**
     * Get transformers as array
     */
    public function getTransformersArray(): array
    {
        $transformers = $this->getTransformers();
        if (empty($transformers)) {
            return [];
        }
        return Mage::helper('core/string')->unserialize($transformers) ?: [];
    }

    /**
     * Set transformers from array
     */
    public function setTransformersArray(array $transformers): self
    {
        $this->setTransformers(Mage::helper('core')->jsonEncode($transformers));
        return $this;
    }

    /**
     * Get source type options
     */
    public static function getSourceTypeOptions(): array
    {
        $helper = Mage::helper('feedmanager');
        return [
            self::SOURCE_TYPE_ATTRIBUTE => $helper->__('Product Attribute'),
            self::SOURCE_TYPE_STATIC => $helper->__('Static Value'),
            self::SOURCE_TYPE_RULE => $helper->__('Conditional Rule'),
            self::SOURCE_TYPE_COMBINED => $helper->__('Combined Template'),
            self::SOURCE_TYPE_TAXONOMY => $helper->__('Category Taxonomy'),
        ];
    }

    public function getMappingId(): ?int
    {
        $value = $this->getData('mapping_id');
        return $value === null ? null : (int) $value;
    }

    public function getFeedId(): ?int
    {
        $value = $this->getData('feed_id');
        return $value === null ? null : (int) $value;
    }

    public function setFeedId(?int $value): static
    {
        return $this->setData('feed_id', $value);
    }

    public function getPlatformAttribute(): ?string
    {
        $value = $this->getData('platform_attribute');
        return $value === null ? null : (string) $value;
    }

    public function setPlatformAttribute(?string $value): static
    {
        return $this->setData('platform_attribute', $value);
    }

    public function getSourceType(): ?string
    {
        $value = $this->getData('source_type');
        return $value === null ? null : (string) $value;
    }

    public function setSourceType(?string $value): static
    {
        return $this->setData('source_type', $value);
    }

    public function getSourceValue(): ?string
    {
        $value = $this->getData('source_value');
        return $value === null ? null : (string) $value;
    }

    public function setSourceValue(?string $value): static
    {
        return $this->setData('source_value', $value);
    }

    public function getConditions(): ?string
    {
        $value = $this->getData('conditions');
        return $value === null ? null : (string) $value;
    }

    public function setConditions(?string $value): static
    {
        return $this->setData('conditions', $value);
    }

    public function getTransformers(): ?string
    {
        $value = $this->getData('transformers');
        return $value === null ? null : (string) $value;
    }

    public function setTransformers(?string $value): static
    {
        return $this->setData('transformers', $value);
    }

    public function getSortOrder(): ?int
    {
        $value = $this->getData('sort_order');
        return $value === null ? null : (int) $value;
    }

    public function setSortOrder(?int $value): static
    {
        return $this->setData('sort_order', $value);
    }
}
