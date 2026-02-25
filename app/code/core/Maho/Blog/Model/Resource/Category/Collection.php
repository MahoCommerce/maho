<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Resource_Category_Collection extends Mage_Eav_Model_Entity_Collection_Abstract
{
    public const ENTITY = 'blog_category';

    protected array $_staticAttributes = [
        'parent_id',
        'path',
        'level',
        'position',
        'name',
        'url_key',
        'is_active',
        'meta_title',
        'meta_keywords',
        'meta_description',
        'meta_robots',
    ];

    protected bool $_isFilteringStaticAttribute = false;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('blog/category');
        $this->_map['fields']['store'] = 'store_table.store_id';
    }

    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('entity_id', 'name');
    }

    /**
     * @param int|Mage_Core_Model_Store $store
     */
    public function addStoreFilter($store, bool $withAdmin = true): self
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $store = [$store->getId()];
        }

        if (!is_array($store)) {
            $store = [$store];
        }

        if ($withAdmin) {
            $store[] = Mage_Core_Model_App::ADMIN_STORE_ID;
        }

        $this->addFilter('store', ['in' => $store], 'public');

        return $this;
    }

    /**
     * Filter to exclude root category (level > 0)
     */
    public function addRootFilter(): self
    {
        $this->addFieldToFilter('level', ['gt' => 0]);
        return $this;
    }

    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    public function addParentFilter(int $parentId): self
    {
        $this->addFieldToFilter('parent_id', $parentId);
        return $this;
    }

    #[\Override]
    protected function _afterLoad(): self
    {
        parent::_afterLoad();

        foreach ($this->_items as $item) {
            $storesString = $item->getData('stores');
            if ($storesString) {
                $stores = explode(',', $storesString);
                $item->setStores($stores);
            } else {
                $item->setStores([]);
            }
        }

        return $this;
    }

    public function isStaticAttribute(string $attribute): bool
    {
        return in_array($attribute, $this->_staticAttributes, true);
    }

    #[\Override]
    public function addAttributeToFilter($attribute, $condition = null, $joinType = 'inner')
    {
        if ($this->isStaticAttribute($attribute) && !$this->_isFilteringStaticAttribute) {
            $this->_isFilteringStaticAttribute = true;
            $result = $this->addFieldToFilter($attribute, $condition);
            $this->_isFilteringStaticAttribute = false;
            return $result;
        }

        return parent::addAttributeToFilter($attribute, $condition, $joinType);
    }

    #[\Override]
    public function addAttributeToSelect($attribute, $joinType = false)
    {
        if ($attribute === '*' || (is_array($attribute) && in_array('*', $attribute))) {
            $this->_selectAttributes = [];
            foreach ($this->_staticAttributes as $staticAttr) {
                $this->_selectAttributes[] = $staticAttr;
            }
        }

        if (is_string($attribute) && $this->isStaticAttribute($attribute)) {
            $this->_selectAttributes[] = $attribute;
            return $this;
        }

        if (is_array($attribute)) {
            $eavAttrs = [];
            foreach ($attribute as $attr) {
                if ($this->isStaticAttribute($attr)) {
                    $this->_selectAttributes[] = $attr;
                } else {
                    $eavAttrs[] = $attr;
                }
            }

            if (!empty($eavAttrs)) {
                parent::addAttributeToSelect($eavAttrs, $joinType);
            }

            return $this;
        }

        return parent::addAttributeToSelect($attribute, $joinType);
    }

    #[\Override]
    protected function _initSelect(): self
    {
        parent::_initSelect();

        $connection = $this->getConnection();
        $this->getSelect()->joinLeft(
            ['store_table' => $this->getTable('blog/category_store')],
            'e.entity_id = store_table.category_id',
            ['stores' => $connection->getGroupConcatExpr('store_table.store_id')],
        )->group('e.entity_id');

        return $this;
    }
}
