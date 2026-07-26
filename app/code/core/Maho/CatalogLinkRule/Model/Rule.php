<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

/**
 * Catalog Link Rule Model
 *
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

    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if ($this->isObjectNew() && !$this->getCreatedAt()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);
        return $this;
    }

    #[\Override]
    protected function _afterSave()
    {
        // A deactivated rule should no longer contribute links: drop the ones it generated so
        // they don't linger (the processor only refreshes active rules). Manual links untouched.
        if (!$this->getIsActive()) {
            $this->deleteGeneratedLinks();
        }
        return parent::_afterSave();
    }

    #[\Override]
    protected function _afterDelete()
    {
        $this->deleteGeneratedLinks();
        return parent::_afterDelete();
    }

    /**
     * Remove the catalog links this rule generated (rule_id tag); manual links are untouched.
     */
    protected function deleteGeneratedLinks(): void
    {
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->delete(
            $resource->getTableName('catalog/product_link'),
            ['rule_id = ?' => (int) $this->getId()],
        );
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
     * Target product IDs matched by this rule, cached when the target conditions do not depend on
     * the source product (see targetConditionsUseSourceProduct()).
     *
     * @var int[]|null
     */
    protected ?array $_targetProductIds = null;

    protected ?bool $_targetConditionsUseSourceProduct = null;

    /**
     * Whether the target conditions reference the source product, i.e. whether the matched target
     * set can differ from one source product to the next.
     */
    public function targetConditionsUseSourceProduct(): bool
    {
        if ($this->_targetConditionsUseSourceProduct === null) {
            $this->_targetConditionsUseSourceProduct = $this->_hasSourceMatchCondition($this->getTargetConditions());
        }

        return $this->_targetConditionsUseSourceProduct;
    }

    protected function _hasSourceMatchCondition(Mage_Rule_Model_Condition_Abstract $condition): bool
    {
        if ($condition instanceof Maho_CatalogLinkRule_Model_Rule_Target_SourceMatch) {
            return true;
        }

        if ($condition instanceof Mage_Rule_Model_Condition_Combine) {
            foreach ($condition->getConditions() as $child) {
                if ($this->_hasSourceMatchCondition($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get matching source product IDs
     */
    public function getMatchingSourceProductIds(): array
    {
        $productCollection = Mage::getResourceModel('catalog/product_collection')
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
        // When no target condition looks at the source product, the matched set is identical for
        // every source product: scan the catalog once and reuse it. Only the order may differ, so
        // random sorting still shuffles a fresh copy per source product below.
        if (!$this->targetConditionsUseSourceProduct()) {
            if ($this->_targetProductIds === null) {
                $this->_targetProductIds = $this->_collectMatchingTargetProductIds();
            }
            $productIds = $this->_targetProductIds;
            if (!in_array($this->getSortOrder(), ['price_desc', 'name_asc', 'name_desc', 'newest', 'oldest', 'price_asc'], true)) {
                shuffle($productIds);
            }
            return $productIds;
        }

        return $this->_collectMatchingTargetProductIds($sourceProduct);
    }

    /**
     * Scan the enabled catalog and return the target product IDs matched by this rule, in the
     * configured sort order.
     *
     * @return int[]
     */
    protected function _collectMatchingTargetProductIds(?Mage_Catalog_Model_Product $sourceProduct = null): array
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
