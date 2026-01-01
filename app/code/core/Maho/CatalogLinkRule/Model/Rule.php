<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Catalog Link Rule Model
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 *
 * @property Maho_CatalogLinkRule_Model_Rule_Source_Combine $_conditions
 * @property Maho_CatalogLinkRule_Model_Rule_Target_Combine $_actions
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
    public function getConditionsInstance(): Mage_Rule_Model_Condition_Combine
    {
        return Mage::getModel('cataloglinkrule/rule_source_combine');
    }

    #[\Override]
    public function getActionsInstance()
    {
        return Mage::getModel('cataloglinkrule/rule_target_combine');
    }

    public function getSourceConditions(): Mage_Rule_Model_Condition_Combine
    {
        return $this->getConditions();
    }

    public function getTargetConditions(): Mage_Rule_Model_Condition_Combine
    {
        $actions = $this->getActions();
        if (!$actions instanceof Mage_Rule_Model_Condition_Combine) {
            throw new Mage_Core_Exception('Target conditions must be a Mage_Rule_Model_Condition_Combine');
        }
        return $actions;
    }

    /**
     * Get matching source product IDs
     */
    public function getMatchingSourceProductIds(): array
    {
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        /** @var Maho_CatalogLinkRule_Model_Rule_Source_Combine $sourceConditions */
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

        /** @var Maho_CatalogLinkRule_Model_Rule_Target_Combine $targetConditions */
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
            case 'price_asc':
                $productCollection->addAttributeToSort('price', 'ASC');
                break;
            case 'random':
            default:
                // Default: random order (for better performance on large catalogs, shuffle in PHP)
                $productIds = [];
                foreach ($productCollection as $product) {
                    if ($targetConditions->validate($product)) {
                        $productIds[] = (int) $product->getId();
                    }
                }
                shuffle($productIds);
                return $productIds;
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
