<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Resource_Post_Collection extends Mage_Eav_Model_Entity_Collection_Abstract
{
    public const ENTITY = 'blog_post';

    protected function _construct(): void
    {
        $this->_init('blog/post');
        $this->_map['fields']['store'] = 'store_table.store_id';
    }

    #[\Override]
    protected function _initSelect(): self
    {
        parent::_initSelect();

        // Add store view information
        $this->getSelect()->joinLeft(
            ['store_table' => $this->getTable('blog/post_store')],
            'e.entity_id = store_table.post_id',
            ['GROUP_CONCAT(store_table.store_id SEPARATOR ",") as stores'],
        )->group('e.entity_id');

        return $this;
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
            $stores = explode(',', $item->getStores());
            $item->setStores($stores);
        }

        return $this;
    }
}
