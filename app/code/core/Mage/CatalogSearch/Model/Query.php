<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

/**
 * @method Mage_CatalogSearch_Model_Resource_Query _getResource()
 * @method Mage_CatalogSearch_Model_Resource_Query getResource()
 * @method Mage_CatalogSearch_Model_Resource_Query_Collection getCollection()
 * @method Mage_CatalogSearch_Model_Resource_Query_Collection getResourceCollection()
 *
 * @method int getDisplayInTerms()
 * @method $this setDisplayInTerms(int $value)
 * @method int getIsActive()
 * @method $this setIsActive(int $value)
 * @method int getIsProcessed()
 * @method $this setIsProcessed(int $value)
 * @method string getName()
 * @method int getNumResults()
 * @method $this setNumResults(int $value)
 * @method int getPopularity()
 * @method $this setPopularity(int $value)
 * @method string getQueryText()
 * @method $this setQueryText(string $value)
 * @method $this setRatio(float $value)
 * @method string getRedirect()
 * @method $this setRedirect(string $value)
 * @method string getSynonymFor()
 * @method $this setSynonymFor(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */
class Mage_CatalogSearch_Model_Query extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'catalogsearch_query';

    /**
     * @var string
     */
    protected $_eventObject = 'catalogsearch_query';

    public const CACHE_TAG                     = 'SEARCH_QUERY';
    public const XML_PATH_MIN_QUERY_LENGTH     = 'catalog/search/min_query_length';
    public const XML_PATH_MAX_QUERY_LENGTH     = 'catalog/search/max_query_length';
    public const XML_PATH_MAX_QUERY_WORDS      = 'catalog/search/max_query_words';
    public const XML_PATH_AJAX_SUGGESTION_COUNT = 'catalog/search/show_autocomplete_results_count';
    public const XML_PATH_LOG_RATE_LIMIT       = 'catalog/search/log_rate_limit';
    public const XML_PATH_LOG_CLEAN_ENABLED    = 'catalog/search/log_clean_enabled';
    public const XML_PATH_LOG_CLEAN_AFTER_DAYS = 'catalog/search/log_clean_after_days';

    /**
     * Window of the persistence rate limiter, in seconds.
     */
    public const LOG_RATE_LIMIT_WINDOW = 3600;

    /**
     * Init resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogsearch/query');
    }

    /**
     * Retrieve search collection
     *
     * @return Mage_CatalogSearch_Model_Resource_Search_Collection
     */
    public function getSearchCollection()
    {
        return Mage::getResourceModel('catalogsearch/search_collection');
    }

    /**
     * Retrieve collection of search results
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    public function getResultCollection()
    {
        $collection = $this->getData('result_collection');
        if (is_null($collection)) {
            $collection = $this->getSearchCollection();

            $text = $this->getSynonymFor();
            if (!$text) {
                $text = $this->getQueryText();
            }

            $collection->addSearchFilter($text)
                ->addStoreFilter()
                ->addPriceData()
                ->addTaxPercents();
            $this->setData('result_collection', $collection);
        }
        return $collection;
    }

    /**
     * Retrieve collection of suggest queries
     *
     * @return Mage_CatalogSearch_Model_Resource_Query_Collection
     */
    public function getSuggestCollection()
    {
        $collection = $this->getData('suggest_collection');
        if (is_null($collection)) {
            $collection = Mage::getResourceModel('catalogsearch/query_collection')
                ->setStoreId($this->getStoreId())
                ->setQueryFilter($this->getQueryText());
            $this->setData('suggest_collection', $collection);
        }
        return $collection;
    }

    /**
     * Load Query object by query string
     *
     * @param string $text
     * @return $this
     */
    public function loadByQuery($text)
    {
        $this->_getResource()->loadByQuery($this, $text);
        $this->_afterLoad();
        $this->setOrigData();
        return $this;
    }

    /**
     * Load Query object only by query text (skip 'synonym For')
     *
     * @param string $text
     * @return $this
     */
    public function loadByQueryText($text)
    {
        $this->_getResource()->loadByQueryText($this, $text);
        $this->_afterLoad();
        $this->setOrigData();
        return $this;
    }

    /**
     * Set Store Id
     *
     * @param int $storeId
     */
    public function setStoreId($storeId)
    {
        $this->setData('store_id', $storeId);
    }

    /**
     * Retrieve store Id
     *
     * @return int
     */
    public function getStoreId()
    {
        if (!$storeId = $this->getData('store_id')) {
            $storeId = Mage::app()->getStore()->getId();
        }
        return $storeId;
    }

    /**
     * Retrieve minimum query length
     *
     * @return int
     */
    public function getMinQueryLength()
    {
        return Mage::getStoreConfig(self::XML_PATH_MIN_QUERY_LENGTH, $this->getStoreId());
    }

    /**
     * Retrieve maximum query length
     *
     * @return int
     */
    public function getMaxQueryLength()
    {
        return Mage::getStoreConfig(self::XML_PATH_MAX_QUERY_LENGTH, $this->getStoreId());
    }

    /**
     * Retrieve maximum query words for like search
     *
     * @return int
     */
    public function getMaxQueryWords()
    {
        return Mage::getStoreConfig(self::XML_PATH_MAX_QUERY_WORDS, $this->getStoreId());
    }

    /**
     * Cron job: delete stale search terms that found nothing.
     */
    #[Maho\Config\CronJob('catalogsearch_query_clean', schedule: '0 3 * * *')]
    public function cleanOldQueries(): void
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_LOG_CLEAN_ENABLED)) {
            return;
        }

        $days = (int) Mage::getStoreConfig(self::XML_PATH_LOG_CLEAN_AFTER_DAYS);
        if ($days <= 0) {
            return;
        }

        $this->_getResource()->cleanOldQueries($days);
    }
}
