<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Resource_Category_Dynamic_Rule extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('catalog/category_dynamic_rule', 'rule_id');
    }

    /**
     * Get rules for category
     *
     * @param int $categoryId
     * @return array
     */
    public function getRulesForCategory($categoryId)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('category_id = ?', $categoryId)
            ->where('is_active = ?', 1);

        return $adapter->fetchAll($select);
    }

    /**
     * Get all active dynamic categories
     *
     * @return array
     */
    public function getActiveDynamicCategories()
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['category_id'])
            ->where('is_active = ?', 1)
            ->group('category_id');

        return $adapter->fetchCol($select);
    }

    /**
     * Delete rules for category
     *
     * @param int $categoryId
     * @return $this
     */
    public function deleteRulesForCategory($categoryId)
    {
        $adapter = $this->_getWriteAdapter();
        $adapter->delete(
            $this->getMainTable(),
            ['category_id = ?' => $categoryId],
        );
        return $this;
    }

    /**
     * Parent product IDs of the given products, keyed by child ID.
     *
     * A child can belong to more than one composite product, so every parent is returned; keying on
     * child_id alone (fetchPairs) would silently keep just one of them.
     *
     * @param int[]|string[] $childIds
     * @return array<int, int[]> child ID => parent IDs, children without a parent are absent
     */
    public function getParentIdsByChildIds(array $childIds): array
    {
        $adapter = $this->_getReadAdapter();
        $parents = [];

        foreach (array_chunk(array_values($childIds), 1000) as $chunk) {
            $select = $adapter->select()
                ->from($this->getTable('catalog/product_relation'), ['child_id', 'parent_id'])
                ->where('child_id IN (?)', $chunk);

            foreach ($adapter->fetchAll($select) as $row) {
                $parents[(int) $row['child_id']][] = (int) $row['parent_id'];
            }
        }

        return $parents;
    }

    /**
     * Which of the given products are composite (configurable, grouped, bundle, ...).
     *
     * @param int[]|string[] $productIds
     * @return int[]
     */
    public function getCompositeProductIds(array $productIds): array
    {
        $compositeTypes = Mage::getSingleton('catalog/product_type')->getCompositeTypes();
        if (empty($compositeTypes) || empty($productIds)) {
            return [];
        }

        $adapter = $this->_getReadAdapter();
        $composites = [];

        foreach (array_chunk(array_values($productIds), 1000) as $chunk) {
            $select = $adapter->select()
                ->from($this->getTable('catalog/product'), ['entity_id'])
                ->where('entity_id IN (?)', $chunk)
                ->where('type_id IN (?)', $compositeTypes);

            foreach ($adapter->fetchCol($select) as $productId) {
                $composites[] = (int) $productId;
            }
        }

        return $composites;
    }

    /**
     * Save rule for category
     *
     * @param Mage_Catalog_Model_Category_Dynamic_Rule $rule
     * @return $this
     */
    #[\Override]
    public function save(Mage_Core_Model_Abstract $rule)
    {
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        $rule->setUpdatedAt($now);
        if (!$rule->getCreatedAt()) {
            $rule->setCreatedAt($now);
        }
        return parent::save($rule);
    }
}
