<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

declare(strict_types=1);

/**
 * @method Mage_CatalogSearch_Model_Resource_Query _getResource()
 * @method Mage_CatalogSearch_Model_Resource_Query getResource()
 * @method Mage_CatalogSearch_Model_Resource_Query_Collection getCollection()
 * @method Mage_CatalogSearch_Model_Resource_Query_Collection getResourceCollection()
 */
class Mage_CatalogSearch_Model_Query extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected $_eventPrefix = 'catalogsearch_query';

    /**
     * @var string
     */
    #[\Override]
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

    public function getDisplayInTerms(): ?bool
    {
        $value = $this->getData('display_in_terms');
        return $value === null ? null : (bool) $value;
    }

    public function setDisplayInTerms(?bool $value = true): static
    {
        return $this->setData('display_in_terms', $value);
    }

    public function getIsActive(): ?bool
    {
        $value = $this->getData('is_active');
        return $value === null ? null : (bool) $value;
    }

    public function setIsActive(?bool $value = true): static
    {
        return $this->setData('is_active', $value);
    }

    public function getIsProcessed(): ?bool
    {
        $value = $this->getData('is_processed');
        return $value === null ? null : (bool) $value;
    }

    public function setIsProcessed(?bool $value = true): static
    {
        return $this->setData('is_processed', $value);
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function getNumResults(): ?int
    {
        $value = $this->getData('num_results');
        return $value === null ? null : (int) $value;
    }

    public function setNumResults(?int $value): static
    {
        return $this->setData('num_results', $value);
    }

    public function getPopularity(): ?int
    {
        $value = $this->getData('popularity');
        return $value === null ? null : (int) $value;
    }

    public function setPopularity(?int $value): static
    {
        return $this->setData('popularity', $value);
    }

    public function getQueryText(): ?string
    {
        $value = $this->getData('query_text');
        return $value === null ? null : (string) $value;
    }

    public function setQueryText(?string $value): static
    {
        return $this->setData('query_text', $value);
    }

    public function setRatio(?float $value): static
    {
        return $this->setData('ratio', $value);
    }

    public function getRedirect(): ?string
    {
        $value = $this->getData('redirect');
        return $value === null ? null : (string) $value;
    }

    public function setRedirect(?string $value): static
    {
        return $this->setData('redirect', $value);
    }

    public function getSynonymFor(): ?string
    {
        $value = $this->getData('synonym_for');
        return $value === null ? null : (string) $value;
    }

    public function setSynonymFor(?string $value): static
    {
        return $this->setData('synonym_for', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

}
