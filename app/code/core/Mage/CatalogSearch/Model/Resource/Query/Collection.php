<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogSearch_Model_Resource_Query_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Store for filter
     *
     * @var int
     */
    protected $_storeId;

    /**
     * Init model for collection
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogsearch/query');
    }

    /**
     * Set Store ID for filter
     *
     * @param mixed $store
     * @return $this
     */
    public function setStoreId($store)
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $store = $store->getId();
        }
        $this->_storeId = $store;
        return $this;
    }

    /**
     * Retrieve Store ID Filter
     *
     * @return int|null
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }

    /**
     * Set search query text to filter
     *
     * @param string $query
     * @return $this
     */
    public function setQueryFilter($query)
    {
        $helper = Mage::getResourceHelper('core');

        $ifSynonymFor = $this->getConnection()
            ->getIfNullSql('synonym_for', 'query_text');
        $this->getSelect()->reset(Maho\Db\Select::FROM)->distinct(true)
            ->from(
                ['main_table' => $this->getTable('catalogsearch/search_query')],
                ['query'      => $ifSynonymFor, 'num_results'],
            )
            ->where(
                'num_results > 0 AND display_in_terms = 1 AND query_text LIKE ?',
                $helper->addLikeEscape($query, ['position' => 'start']),
            )
            ->order('popularity ' . Maho\Db\Select::SQL_DESC);
        if ($this->getStoreId()) {
            $this->getSelect()
                ->where('store_id = ?', (int) $this->getStoreId());
        }
        return $this;
    }

    /**
     * Set Popular Search Query Filter
     *
     * @param int|array $storeIds
     * @return $this
     */
    public function setPopularQueryFilter($storeIds = null)
    {
        $ifSynonymFor = new Maho\Db\Expr($this->getConnection()
            ->getCheckSql("synonym_for IS NOT NULL AND synonym_for != ''", 'synonym_for', 'query_text'));

        $this->getSelect()
            ->reset(Maho\Db\Select::FROM)
            ->reset(Maho\Db\Select::COLUMNS)
            ->distinct(true)
            ->from(
                ['main_table' => $this->getTable('catalogsearch/search_query')],
                ['name' => $ifSynonymFor, 'num_results', 'popularity', 'query_id'],
            );
        if ($storeIds) {
            $this->addStoreFilter($storeIds);
            $this->getSelect()->where('num_results > 0');
        } elseif ($storeIds === null) {
            $this->addStoreFilter(Mage::app()->getStore()->getId());
            $this->getSelect()->where('num_results > 0');
        }

        $this->getSelect()->order(['popularity desc','name']);

        return $this;
    }

    /**
     * Set Recent Queries Order
     *
     * @return $this
     */
    public function setRecentQueryFilter()
    {
        $this->setOrder('updated_at', 'desc');
        return $this;
    }

    /**
     * Filter collection by specified store ids
     *
     * @param array|int $storeIds
     * @return $this
     */
    public function addStoreFilter($storeIds)
    {
        if (!is_array($storeIds)) {
            $storeIds = [$storeIds];
        }
        $this->getSelect()->where('main_table.store_id IN (?)', $storeIds);
        return $this;
    }
}
