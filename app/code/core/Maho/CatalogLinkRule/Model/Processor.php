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
 * Catalog Link Rule Processor
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 */
class Maho_CatalogLinkRule_Model_Processor
{
    public const BATCH_SIZE = 100;

    /**
     * Process all active rules (called by cron)
     */
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
                // Delete existing links for this batch
                $adapter->delete($linkTable, [
                    'product_id IN (?)' => $batchProductIds,
                    'link_type_id = ?' => $linkTypeId,
                ]);

                // Process each product in batch
                foreach ($batchProductIds as $productId) {
                    $rule = $productRuleMap[$productId];
                    $this->applyRuleToProduct(
                        $productId,
                        $rule,
                        $linkTypeId,
                        $positionAttrId,
                        $adapter,
                        $linkTable,
                        $linkAttrTable,
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
    ): void {
        // Load the source product with all attributes
        $sourceProduct = Mage::getModel('catalog/product')->load($productId);

        // Get matching target products, passing the source product for matching conditions
        $targetProductIds = $rule->getMatchingTargetProductIds($sourceProduct);
        $maxLinks = $rule->getMaxLinks();

        $position = 1;
        $linkCount = 0;

        foreach ($targetProductIds as $targetId) {
            if ($targetId == $productId) {
                continue; // Don't link to self
            }

            // Check max links limit
            if ($maxLinks && $linkCount >= $maxLinks) {
                break;
            }

            // Insert link
            $adapter->insert($linkTable, [
                'product_id' => $productId,
                'linked_product_id' => $targetId,
                'link_type_id' => $linkTypeId,
            ]);

            $linkId = $adapter->lastInsertId();

            // Insert position attribute
            $adapter->insert($linkAttrTable, [
                'product_link_attribute_id' => $positionAttrId,
                'link_id' => $linkId,
                'value' => $position++,
            ]);

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
