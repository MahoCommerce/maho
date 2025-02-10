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

    protected function _construct()
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

    /**
     * Get SQL for get record count.
     * Extra GROUP BY strip added.
     */
    #[\Override]
    public function getSelectCountSql(): Varien_Db_Select
    {
        $countSelect = parent::getSelectCountSql();
        $countSelect->reset(Zend_Db_Select::GROUP);
        return $countSelect;
    }

    /**
     * Join store relation table if there is store filter
     */
    #[\Override]
    protected function _renderFiltersBefore()
    {
        if ($this->getFilter('store')) {
            $this->getSelect()->join(
                ['store_table' => $this->getTable('cms/block_store')],
                'main_table.block_id = store_table.block_id',
                [],
            )->group('main_table.block_id');

            /*
             * Allow analytic functions usage because of one field grouping
             */
            $this->_useAnalyticFunction = true;
        }
        return parent::_renderFiltersBefore();
    }
}
