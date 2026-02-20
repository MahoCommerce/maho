<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Category_Dynamic_Rule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/category_dynamic_rule');
    }

    /**
     * Filter by category ID
     *
     * @param int $categoryId
     * @return $this
     */
    public function addCategoryFilter($categoryId)
    {
        $this->addFieldToFilter('category_id', $categoryId);
        return $this;
    }

    /**
     * Filter by active status
     *
     * @param bool $isActive
     * @return $this
     */
    public function addActiveFilter($isActive = true)
    {
        $this->addFieldToFilter('is_active', $isActive ? 1 : 0);
        return $this;
    }

    /**
     * Add category join
     *
     * @return $this
     */
    public function joinCategory()
    {
        if (!$this->getFlag('category_joined')) {
            $this->getSelect()->join(
                ['category' => $this->getTable('catalog/category')],
                'main_table.category_id = category.entity_id',
                ['category_name' => 'category.name'],
            );
            $this->setFlag('category_joined', true);
        }
        return $this;
    }
}
