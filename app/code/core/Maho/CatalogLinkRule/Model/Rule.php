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
    /**
     * Target product IDs matched by this rule, cached when the target conditions do not depend on
     * the source product (see targetConditionsUseSourceProduct()).
     *
     * Scoped to one instance whose target conditions don't change: the processor loads each rule
     * fresh per run and only reads it, so there is deliberately no invalidation hook. Code that
     * edits the conditions of a rule it has already scanned must use a fresh instance.
     *
     * @var int[]|null
     */
    protected ?array $_targetProductIds = null;

    /**
     * Sort order the cached scan above was collected under, so a later sort order change can't be
     * served from a differently-ordered cache.
     */
    protected ?string $_targetProductIdsSortOrder = null;

    protected ?bool $_targetConditionsUseSourceProduct = null;

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
     * Whether the target conditions reference the source product, i.e. whether the matched target
     * set can differ from one source product to the next.
     */
    public function targetConditionsUseSourceProduct(): bool
    {
        $this->_targetConditionsUseSourceProduct ??= $this->_hasSourceMatchCondition($this->getTargetConditions());

        return $this->_targetConditionsUseSourceProduct;
    }

    protected function _hasSourceMatchCondition(Mage_Rule_Model_Condition_Abstract $condition): bool
    {
        if ($condition instanceof Maho_CatalogLinkRule_Model_Rule_Target_SourceMatch) {
            return true;
        }

        // A plain target-attribute condition is source-dependent too when it uses one of the
        // "matches source" operators, which compare the target against the source product.
        if ($condition instanceof Maho_CatalogLinkRule_Model_Rule_Target_Product
            && $condition->isSourceMatchOperator()
        ) {
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
     * Deterministic target sort orders, mapped to the [attribute, direction] applied to the
     * collection. Any sort order not listed here (including 'random' and the empty default) is
     * shuffled in PHP instead — see _isRandomSort(). Single source of truth for both call sites.
     */
    protected const SORT_ORDERS = [
        'price_desc' => ['price', 'DESC'],
        'name_asc' => ['name', 'ASC'],
        'name_desc' => ['name', 'DESC'],
        'newest' => ['created_at', 'DESC'],
        'oldest' => ['created_at', 'ASC'],
        'price_asc' => ['price', 'ASC'],
    ];

    /**
     * Whether the configured sort order is random (shuffled in PHP) rather than a deterministic
     * SQL ordering. Kept as a single check so the cached and freshly-scanned paths never disagree.
     */
    protected function _isRandomSort(): bool
    {
        return !isset(self::SORT_ORDERS[(string) $this->getSortOrder()]);
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
            // The cache holds the IDs *in* the sort order they were collected under, so a changed
            // sort order has to rescan rather than reuse a differently-ordered array.
            if ($this->_targetProductIds === null || $this->_targetProductIdsSortOrder !== (string) $this->getSortOrder()) {
                $this->_targetProductIds = $this->_collectMatchingTargetProductIds();
                $this->_targetProductIdsSortOrder = (string) $this->getSortOrder();
            }
            $productIds = $this->_targetProductIds;
            if ($this->_isRandomSort()) {
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

        // Apply the configured deterministic sort; random/unknown orders stay unsorted here and are
        // shuffled in PHP below (cheaper than a SQL random ordering on large catalogs).
        if (isset(self::SORT_ORDERS[(string) $this->getSortOrder()])) {
            [$attribute, $direction] = self::SORT_ORDERS[(string) $this->getSortOrder()];
            $productCollection->addAttributeToSort($attribute, $direction);
        }

        $productIds = [];
        foreach ($productCollection as $product) {
            if ($targetConditions->validate($product)) {
                $productIds[] = (int) $product->getId();
            }
        }

        if ($this->_isRandomSort()) {
            shuffle($productIds);
        }

        return $productIds;
    }
}
