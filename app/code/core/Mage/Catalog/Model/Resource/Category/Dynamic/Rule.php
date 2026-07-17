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
