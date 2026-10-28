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
     * the source product (see targetConditionsUseSourceProduct()). The key is the sort order and the
     * websites that the scan used, so a scan is reused only for the same order and websites.
     *
     * Scoped to one instance whose target conditions don't change: the processor loads each rule
     * fresh per run and only reads it, so there is deliberately no invalidation hook. Code that
     * edits the conditions of a rule it has already scanned must use a fresh instance.
     *
     * @var array<string, int[]>
     */
    protected array $_targetProductIds = [];

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
    public function save(): static
    {
        parent::save();
        // _afterSave() compares with the original link type, so a second save must see this one
        $this->setOrigData('link_type_id', $this->getData('link_type_id'));
        return $this;
    }

    #[\Override]
    protected function _afterSave()
    {
        // A deactivated rule should no longer contribute links: drop the ones it generated so
        // they don't linger (the processor only refreshes active rules). Manual links untouched.
        if (!$this->getIsActive()) {
            $this->deleteGeneratedLinks();
        } elseif ($this->getOrigData('link_type_id') !== null
            && (int) $this->getOrigData('link_type_id') !== (int) $this->getLinkTypeId()
        ) {
            // The processor never runs this rule for its former link type again
            $this->deleteGeneratedLinks((int) $this->getOrigData('link_type_id'));
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
     * Remove the catalog links this rule generated (rule_id tag), of one link type or of all of
     * them; manual links are untouched.
     */
    protected function deleteGeneratedLinks(?int $linkTypeId = null): void
    {
        $where = ['rule_id = ?' => (int) $this->getId()];
        if ($linkTypeId !== null) {
            $where['link_type_id = ?'] = $linkTypeId;
        }

        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->delete($resource->getTableName('catalog/product_link'), $where);
    }

    public function hasConditionsSerialized(): bool
    {
        return $this->hasData('source_conditions_serialized');
    }

    #[\Override]
    public function getConditionsSerialized(): string
    {
        return (string) $this->getData('source_conditions_serialized');
    }

    #[\Override]
    public function setConditionsSerialized(?string $value): static
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

    #[\Override]
    public function getActionsSerialized(): string
    {
        return (string) $this->getData('target_conditions_serialized');
    }

    #[\Override]
    public function setActionsSerialized(?string $value): static
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
     * Get matching target product IDs with sorting for a specific source product.
     *
     * A target must be in one of $websiteIds. Null applies no website filter. The storefront shows
     * a linked product only on a website of that product, so the processor passes the websites of
     * the source product.
     *
     * @param int[]|null $websiteIds
     * @return int[]
     */
    public function getMatchingTargetProductIds(?Mage_Catalog_Model_Product $sourceProduct = null, ?array $websiteIds = null): array
    {
        if ($websiteIds === []) {
            return [];
        }

        // When no target condition looks at the source product, the matched set is identical for
        // every source product on the same websites: scan the catalog once and reuse it. Only the
        // order may differ, so random sorting still shuffles a fresh copy per source product below.
        if (!$this->targetConditionsUseSourceProduct()) {
            $cacheKey = $this->_getTargetCacheKey($websiteIds);
            $this->_targetProductIds[$cacheKey] ??= $this->_collectMatchingTargetProductIds(null, $websiteIds);
            $productIds = $this->_targetProductIds[$cacheKey];
            if ($this->_isRandomSort()) {
                shuffle($productIds);
            }
            return $productIds;
        }

        return $this->_collectMatchingTargetProductIds($sourceProduct, $websiteIds);
    }

    /**
     * @param int[]|null $websiteIds
     */
    protected function _getTargetCacheKey(?array $websiteIds): string
    {
        if ($websiteIds !== null) {
            $websiteIds = array_map(intval(...), $websiteIds);
            sort($websiteIds);
        }

        return $this->getSortOrder() . '|' . ($websiteIds === null ? '*' : implode(',', $websiteIds));
    }

    /**
     * Scan the catalog and return the target product IDs matched by this rule, in the configured
     * sort order. The scan skips the products that the storefront blocks never show, so they do not
     * count toward the maximum links. The stock is a target condition that the merchant chooses.
     *
     * @param int[]|null $websiteIds
     * @return int[]
     */
    protected function _collectMatchingTargetProductIds(?Mage_Catalog_Model_Product $sourceProduct = null, ?array $websiteIds = null): array
    {
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(['name', 'price', 'created_at'])
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds()]);

        if ($websiteIds !== null) {
            $this->_addWebsiteFilter($productCollection, $websiteIds);
        }

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

    /**
     * Keep the products that are in one of $websiteIds. EXISTS instead of a join: a join needs
     * DISTINCT for a product in two websites, and PostgreSQL rejects DISTINCT with the attribute sort.
     *
     * @param int[] $websiteIds
     */
    protected function _addWebsiteFilter(Mage_Catalog_Model_Resource_Product_Collection $productCollection, array $websiteIds): void
    {
        $inWebsite = $productCollection->getConnection()->select()
            ->from(['pw' => $productCollection->getTable('catalog/product_website')], [new Maho\Db\Expr('1')])
            ->where('pw.product_id = e.entity_id')
            ->where('pw.website_id IN (?)', array_map(intval(...), $websiteIds));

        $productCollection->getSelect()->where('EXISTS (' . $inWebsite . ')');
    }
}
