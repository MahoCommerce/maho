<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Resource_Post_Collection extends Mage_Eav_Model_Entity_Collection_Abstract
{
    public const ENTITY = 'blog_post';

    /**
     * Static attributes that are stored directly in the main entity table
     */
    protected array $_staticAttributes = [
        'title',
        'url_key',
        'is_active',
        'publish_date',
        'content',
        'meta_description',
        'meta_keywords',
        'meta_title',
        'meta_robots',
    ];

    /**
     * Flag to prevent infinite loops when filtering static attributes
     */
    protected bool $_isFilteringStaticAttribute = false;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('blog/post');
        $this->_map['fields']['store'] = 'store_table.store_id';
    }


    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('entity_id', 'title');
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

    #[\Override]
    protected function _afterLoad(): self
    {
        parent::_afterLoad();

        // Convert comma-separated stores string to array
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

    /**
     * Check if attribute is static (stored in main table)
     */
    public function isStaticAttribute(string $attribute): bool
    {
        return in_array($attribute, $this->_staticAttributes, true);
    }

    /**
     * Override addAttributeToFilter to handle static attributes without infinite loops
     * Only override when explicitly needed - let parent handle most cases
     */
    #[\Override]
    public function addAttributeToFilter($attribute, $condition = null, $joinType = 'inner')
    {
        // For static attributes, we need to ensure they're treated as fields in the main table
        // But we need to be careful not to create infinite loops
        if ($this->isStaticAttribute($attribute) && !$this->_isFilteringStaticAttribute) {
            $this->_isFilteringStaticAttribute = true;
            $result = $this->addFieldToFilter($attribute, $condition);
            $this->_isFilteringStaticAttribute = false;
            return $result;
        }

        // Fall back to parent EAV filtering
        return parent::addAttributeToFilter($attribute, $condition, $joinType);
    }

    /**
     * Override addAttributeToSelect to avoid unnecessary JOINs for static attributes
     */
    #[\Override]
    public function addAttributeToSelect($attribute, $joinType = false)
    {
        if ($attribute === '*' || (is_array($attribute) && in_array('*', $attribute))) {
            // Select all attributes
            $this->_selectAttributes = [];

            // Add all static attributes to selection (they're already in main table)
            foreach ($this->_staticAttributes as $staticAttr) {
                $this->_selectAttributes[] = $staticAttr;
            }
        }

        if (is_string($attribute) && $this->isStaticAttribute($attribute)) {
            // Static attributes are already selected from main table, just track them
            $this->_selectAttributes[] = $attribute;
            return $this;
        }

        if (is_array($attribute)) {
            $staticAttrs = [];
            $eavAttrs = [];

            foreach ($attribute as $attr) {
                if ($this->isStaticAttribute($attr)) {
                    $staticAttrs[] = $attr;
                    $this->_selectAttributes[] = $attr;
                } else {
                    $eavAttrs[] = $attr;
                }
            }

            // Only process EAV attributes through parent
            if (!empty($eavAttrs)) {
                parent::addAttributeToSelect($eavAttrs, $joinType);
            }

            return $this;
        }

        // Fall back to EAV selection for non-static attributes
        return parent::addAttributeToSelect($attribute, $joinType);
    }

    /**
     * Override _initSelect to ensure static attributes are always selected
     */
    #[\Override]
    protected function _initSelect(): self
    {
        parent::_initSelect();

        // Ensure static attributes are selected from main entity table
        $staticColumns = [];
        foreach ($this->_staticAttributes as $attr) {
            $staticColumns[] = $attr;
        }

        // Add store view information
        $connection = $this->getConnection();
        $this->getSelect()->joinLeft(
            ['store_table' => $this->getTable('blog/post_store')],
            'e.entity_id = store_table.post_id',
            ['stores' => $connection->getGroupConcatExpr('store_table.store_id')],
        )->group('e.entity_id');

        return $this;
    }
}
