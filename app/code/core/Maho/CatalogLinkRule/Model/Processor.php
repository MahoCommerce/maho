<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

/**
 * Catalog Link Rule Processor
 *
 * @package    Maho_CatalogLinkRule
 */
class Maho_CatalogLinkRule_Model_Processor
{
    public const BATCH_SIZE = 100;

    public const MODE_REPLACE = 'replace';
    public const MODE_MERGE   = 'merge';

    public const CONFIG_MERGE_MODE = 'catalog/linkrule/merge_mode';

    /**
     * Resolve the configured merge mode (how rule results combine with existing links)
     */
    public function getMergeMode(): string
    {
        return Mage::getStoreConfig(self::CONFIG_MERGE_MODE) === self::MODE_MERGE
            ? self::MODE_MERGE
            : self::MODE_REPLACE;
    }

    /**
     * Process all active rules (called by cron)
     */
    #[Maho\Config\CronJob('cataloglinkrule_apply_all', configPath: 'catalog/linkrule/schedule')]
    public function processRules(): void
    {
        $resource = Mage::getSingleton('core/resource');

        // Get all active rules, ordered by priority
        $rules = Mage::getResourceModel('cataloglinkrule/rule_collection')
            ->addFieldToFilter('is_active', 1)
            ->addDateFilter()
            ->setOrder('priority', 'ASC')
            ->setOrder('rule_id', 'ASC');

        // Group by link_type_id
        $rulesByType = [];
        foreach ($rules as $rule) {
            $rulesByType[$rule->getLinkTypeId()][] = $rule;
        }

        // Process each link type separately
        foreach ($rulesByType as $linkTypeId => $typeRules) {
            $this->processLinkType((int) $linkTypeId, $typeRules);
        }
    }

    /**
     * Process all rules for a specific link type
     */
    protected function processLinkType(int $linkTypeId, array $rules): void
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $linkTable = $resource->getTableName('catalog/product_link');
        $linkAttrTable = $resource->getTableName('catalog/product_link_attribute_int');

        $mergeMode = $this->getMergeMode();

        // Step 1: Build product → rule map (highest priority wins)
        $productRuleMap = [];
        foreach ($rules as $rule) {
            $sourceProductIds = $rule->getMatchingSourceProductIds();
            foreach ($sourceProductIds as $productId) {
                // Only assign if not already assigned (first = highest priority)
                if (!isset($productRuleMap[$productId])) {
                    $productRuleMap[$productId] = $rule;
                }
            }
        }

        // Merge mode recomputes its own output every run. Drop rule-generated links of this type
        // for products no longer covered by any rule so they don't accumulate; manual links
        // (rule_id IS NULL) are left intact. Matched products keep their current rule links until
        // their batch transaction rewrites them atomically below, so a mid-run failure never
        // strands a matched product with only its manual links. Replace mode skips this on purpose:
        // it only rewrites currently-matched products, preserving the legacy default behaviour.
        if ($mergeMode === self::MODE_MERGE) {
            $this->purgeOrphanRuleLinks($adapter, $linkTable, $linkTypeId, array_keys($productRuleMap));
        }

        if (empty($productRuleMap)) {
            return; // No products to process
        }

        // Step 2: Get position attribute ID for this link type
        $positionAttrId = $this->getPositionAttributeId($linkTypeId);

        // Step 3: Process in batches with transactions
        $productIds = array_keys($productRuleMap);
        $batches = array_chunk($productIds, self::BATCH_SIZE);

        foreach ($batches as $batchProductIds) {
            $adapter->beginTransaction();
            try {
                // Replace mode wipes all existing links for these products; merge drops only this
                // batch's prior rule output (atomic with the re-insert) and keeps manual links.
                $existingLinks = [];
                if ($mergeMode === self::MODE_REPLACE) {
                    $adapter->delete($linkTable, [
                        'product_id IN (?)' => $batchProductIds,
                        'link_type_id = ?' => $linkTypeId,
                    ]);
                } else {
                    $adapter->delete($linkTable, [
                        'product_id IN (?)' => $batchProductIds,
                        'link_type_id = ?' => $linkTypeId,
                        'rule_id IS NOT NULL',
                    ]);
                    $existingLinks = $this->getExistingLinks(
                        $adapter,
                        $linkTable,
                        $linkAttrTable,
                        $batchProductIds,
                        $linkTypeId,
                        $positionAttrId,
                    );
                }

                // Process each product in batch
                foreach ($batchProductIds as $productId) {
                    $existing = $existingLinks[$productId] ?? ['targets' => [], 'max_position' => 0];
                    $rule = $productRuleMap[$productId];
                    $this->applyRuleToProduct(
                        $productId,
                        $rule,
                        $linkTypeId,
                        $positionAttrId,
                        $adapter,
                        $linkTable,
                        $linkAttrTable,
                        $existing['targets'],
                        $existing['max_position'],
                    );
                }

                $adapter->commit();
            } catch (Exception $e) {
                $adapter->rollBack();
                Mage::logException($e);
                throw $e;
            }
        }
    }

    /**
     * Remove rule-generated links of this type for products no longer covered by any rule.
     * Manual links (rule_id IS NULL) are never touched. Each delete is a standalone atomic
     * statement, so a failure here cannot strand a matched product mid-recompute.
     *
     * @param Maho\Db\Adapter\AdapterInterface $adapter
     * @param int[] $coveredProductIds
     */
    protected function purgeOrphanRuleLinks($adapter, string $linkTable, int $linkTypeId, array $coveredProductIds): void
    {
        $withRuleLinks = $adapter->fetchCol(
            $adapter->select()
                ->distinct()
                ->from($linkTable, ['product_id'])
                ->where('link_type_id = ?', $linkTypeId)
                ->where('rule_id IS NOT NULL'),
        );

        $covered = array_map('intval', $coveredProductIds);
        $orphans = array_diff(array_map('intval', $withRuleLinks), $covered);

        foreach (array_chunk($orphans, self::BATCH_SIZE) as $orphanBatch) {
            $adapter->delete($linkTable, [
                'product_id IN (?)' => $orphanBatch,
                'link_type_id = ?' => $linkTypeId,
                'rule_id IS NOT NULL',
            ]);
        }
    }

    /**
     * Load existing links for a batch of products, keyed by product ID.
     * Returns ['targets' => int[], 'max_position' => int] per product.
     *
     * @param Maho\Db\Adapter\AdapterInterface $adapter
     */
    protected function getExistingLinks(
        $adapter,
        string $linkTable,
        string $linkAttrTable,
        array $productIds,
        int $linkTypeId,
        int $positionAttrId,
    ): array {
        $select = $adapter->select()
            ->from(['l' => $linkTable], ['product_id', 'linked_product_id'])
            ->joinLeft(
                ['p' => $linkAttrTable],
                'p.link_id = l.link_id AND p.product_link_attribute_id = ' . $positionAttrId,
                ['position' => 'value'],
            )
            ->where('l.product_id IN (?)', $productIds)
            ->where('l.link_type_id = ?', $linkTypeId)
            ->where('l.rule_id IS NULL'); // manual links only

        $existing = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $productId = (int) $row['product_id'];
            if (!isset($existing[$productId])) {
                $existing[$productId] = ['targets' => [], 'max_position' => 0];
            }
            $existing[$productId]['targets'][] = (int) $row['linked_product_id'];
            $position = (int) $row['position'];
            if ($position > $existing[$productId]['max_position']) {
                $existing[$productId]['max_position'] = $position;
            }
        }

        return $existing;
    }

    /**
     * Apply a rule to a single product
     *
     * @param Maho\Db\Adapter\AdapterInterface $adapter
     */
    protected function applyRuleToProduct(
        int $productId,
        Maho_CatalogLinkRule_Model_Rule $rule,
        int $linkTypeId,
        int $positionAttrId,
        $adapter,
        string $linkTable,
        string $linkAttrTable,
        array $existingTargetIds = [],
        int $startPosition = 0,
    ): void {
        // Load the source product with all attributes
        $sourceProduct = Mage::getModel('catalog/product')->load($productId);

        // Get matching target products, passing the source product for matching conditions
        $targetProductIds = $rule->getMatchingTargetProductIds($sourceProduct);
        $maxLinks = $rule->getMaxLinks();
        $ruleId = $rule->getId() !== null ? (int) $rule->getId() : null;

        // Existing links keep their positions and count toward max_links (merge mode);
        // rule results are appended after them, skipping duplicate source→target pairs.
        $linked = [];
        foreach ($existingTargetIds as $existingTargetId) {
            $linked[(int) $existingTargetId] = true;
        }

        // Append after both the highest existing position and the existing count, so rule links
        // never share a position with an existing link that carried no position row (value 0).
        $position = max($startPosition, count($linked)) + 1;
        $linkCount = count($linked);

        foreach ($targetProductIds as $targetId) {
            $targetId = (int) $targetId;

            if ($targetId === $productId) {
                continue; // Don't link to self
            }

            if (isset($linked[$targetId])) {
                continue; // No duplicate source→target pairs
            }

            // Check max links limit (combined set in merge mode)
            if ($maxLinks && $linkCount >= $maxLinks) {
                break;
            }

            // Insert link, tagged with the rule that generated it
            $adapter->insert($linkTable, [
                'product_id' => $productId,
                'linked_product_id' => $targetId,
                'link_type_id' => $linkTypeId,
                'rule_id' => $ruleId,
            ]);

            $linkId = $adapter->lastInsertId();

            // Insert position attribute
            $adapter->insert($linkAttrTable, [
                'product_link_attribute_id' => $positionAttrId,
                'link_id' => $linkId,
                'value' => $position++,
            ]);

            $linked[$targetId] = true;
            $linkCount++;
        }
    }

    /**
     * Get position attribute ID for link type
     */
    protected function getPositionAttributeId(int $linkTypeId): int
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $table = $resource->getTableName('catalog/product_link_attribute');

        return (int) $adapter->fetchOne(
            $adapter->select()
                ->from($table, 'product_link_attribute_id')
                ->where('link_type_id = ?', $linkTypeId)
                ->where('product_link_attribute_code = ?', 'position'),
        );
    }
}
