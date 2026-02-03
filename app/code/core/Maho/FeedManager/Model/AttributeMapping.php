<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Attribute Mapping model
 *
 * Error Handling Pattern:
 * - Getter methods (getConditionsArray, getTransformersArray): Return empty array if JSON invalid, never throw
 * - Setter methods (setConditionsArray, setTransformersArray): Encode to JSON, return self for chaining
 *
 * @method int getMappingId()
 * @method int getFeedId()
 * @method $this setFeedId(int $feedId)
 * @method string getPlatformAttribute()
 * @method $this setPlatformAttribute(string $attribute)
 * @method string getSourceType()
 * @method $this setSourceType(string $type)
 * @method string getSourceValue()
 * @method $this setSourceValue(string $value)
 * @method string|null getConditions()
 * @method $this setConditions(string|null $conditions)
 * @method string|null getTransformers()
 * @method $this setTransformers(string|null $transformers)
 * @method int getSortOrder()
 * @method $this setSortOrder(int $order)
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
        return Mage::helper('core')->jsonDecode($conditions) ?: [];
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
        return Mage::helper('core')->jsonDecode($transformers) ?: [];
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
}
