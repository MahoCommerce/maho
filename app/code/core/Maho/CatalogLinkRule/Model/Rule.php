<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Catalog Link Rule Model
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 */
class Maho_CatalogLinkRule_Model_Rule extends Mage_Rule_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('cataloglinkrule/rule');
    }

    public function hasConditionsSerialized(): bool
    {
        return $this->hasData('source_conditions_serialized');
    }

    public function getConditionsSerialized(): string
    {
        return (string) $this->getData('source_conditions_serialized');
    }

    public function setConditionsSerialized(string $value): self
    {
        return $this->setData('source_conditions_serialized', $value);
    }

    public function unsConditionsSerialized(): self
    {
        return $this->unsetData('source_conditions_serialized');
    }

    public function hasActionsSerialized(): bool
    {
        return $this->hasData('target_conditions_serialized');
    }

    public function getActionsSerialized(): string
    {
        return (string) $this->getData('target_conditions_serialized');
    }

    public function setActionsSerialized(string $value): self
    {
        return $this->setData('target_conditions_serialized', $value);
    }

    public function unsActionsSerialized(): self
    {
        return $this->unsetData('target_conditions_serialized');
    }

    #[\Override]
    public function getConditionsInstance()
    {
        return Mage::getModel('cataloglinkrule/rule_source_combine');
    }

    #[\Override]
    public function getActionsInstance()
    {
        return Mage::getModel('cataloglinkrule/rule_target_combine');
    }

    public function getSourceConditions()
    {
        if (empty($this->_conditions)) {
            $this->_resetConditions();
        }

        // Load rule conditions if it is applicable
        if ($this->hasConditionsSerialized()) {
            $conditions = $this->getConditionsSerialized();
            if (!empty($conditions)) {
                $conditions = Mage::helper('core/unserializeArray')->unserialize($conditions);
                if (is_array($conditions) && !empty($conditions)) {
                    $this->_conditions->loadArray($conditions);
                }
            }
            $this->unsConditionsSerialized();
        }

        return $this->_conditions;
    }

    public function getTargetConditions()
    {
        if (!$this->_actions) {
            $this->_resetActions();
        }

        // Load rule actions if it is applicable
        if ($this->hasActionsSerialized()) {
            $actions = $this->getActionsSerialized();
            if (!empty($actions)) {
                $actions = Mage::helper('core/unserializeArray')->unserialize($actions);
                if (is_array($actions) && !empty($actions)) {
                    $this->_actions->loadArray($actions);
                }
            }
            $this->unsActionsSerialized();
        }

        return $this->_actions;
    }

    #[\Override]
    protected function _beforeSave()
    {
        // Serialize source conditions
        if ($this->getSourceConditions()) {
            $this->setConditionsSerialized(serialize($this->getSourceConditions()->asArray()));
            $this->unsetData('_conditions');
        }

        // Serialize target conditions
        if ($this->getTargetConditions()) {
            $this->setActionsSerialized(serialize($this->getTargetConditions()->asArray()));
            $this->unsetData('_actions');
        }

        // Skip parent's _beforeSave and call grandparent instead
        return Mage_Core_Model_Abstract::_beforeSave();
    }

    /**
     * Get matching source product IDs
     */
    public function getMatchingSourceProductIds(): array
    {
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        $sourceConditions = $this->getSourceConditions();
        $sourceConditions->collectValidatedAttributes($productCollection);

        $productIds = [];
        foreach ($productCollection as $product) {
            if ($sourceConditions->validate($product)) {
                $productIds[] = (int) $product->getId();
            }
        }

        return $productIds;
    }

    /**
     * Get matching target product IDs with sorting for a specific source product
     */
    public function getMatchingTargetProductIds(?Mage_Catalog_Model_Product $sourceProduct = null): array
    {
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(['name', 'price', 'created_at'])
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        $targetConditions = $this->getTargetConditions();
        $targetConditions->collectValidatedAttributes($productCollection);

        // Set source product for source-matching conditions
        if ($sourceProduct) {
            $this->setSourceProduct($sourceProduct);
        }

        // Apply sorting
        switch ($this->getSortOrder()) {
            case 'price_desc':
                $productCollection->addAttributeToSort('price', 'DESC');
                break;
            case 'name_asc':
                $productCollection->addAttributeToSort('name', 'ASC');
                break;
            case 'name_desc':
                $productCollection->addAttributeToSort('name', 'DESC');
                break;
            case 'newest':
                $productCollection->addAttributeToSort('created_at', 'DESC');
                break;
            case 'oldest':
                $productCollection->addAttributeToSort('created_at', 'ASC');
                break;
            case 'random':
                // For better performance on large catalogs, shuffle in PHP
                $productIds = [];
                foreach ($productCollection as $product) {
                    if ($targetConditions->validate($product)) {
                        $productIds[] = (int) $product->getId();
                    }
                }
                shuffle($productIds);
                return $productIds;
            case 'price_asc':
            default:
                // Default: price ascending
                $productCollection->addAttributeToSort('price', 'ASC');
                break;
        }

        $productIds = [];
        foreach ($productCollection as $product) {
            if ($targetConditions->validate($product)) {
                $productIds[] = (int) $product->getId();
            }
        }

        return $productIds;
    }
}
