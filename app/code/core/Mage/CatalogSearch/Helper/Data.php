<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogSearch_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Query variable name
     */
    public const QUERY_VAR_NAME = 'q';

    /**
     * Maximum query length
     */
    public const MAX_QUERY_LEN  = 200;

    protected $_moduleName = 'Mage_CatalogSearch';

    /**
     * Query object
     *
     * @var Mage_CatalogSearch_Model_Query
     */
    protected $_query;

    /**
     * Query string
     *
     * @var string
     */
    protected $_queryText;

    /**
     * Note messages
     *
     * @var array
     */
    protected $_messages = [];

    /**
     * Is a maximum length cut
     *
     * @var bool
     */
    protected $_isMaxLength = false;

    /**
     * Search engine model
     *
     * @var Mage_CatalogSearch_Model_Resource_Fulltext_Engine
     */
    protected $_engine;

    /**
     * Retrieve search query parameter name
     *
     * @return string
     */
    public function getQueryParamName()
    {
        return self::QUERY_VAR_NAME;
    }

    /**
     * Retrieve query model object
     *
     * @return Mage_CatalogSearch_Model_Query
     */
    public function getQuery()
    {
        if (!$this->_query) {
            $this->_query = Mage::getModel('catalogsearch/query')
                ->loadByQuery($this->getQueryText());
            if (!$this->_query->getId()) {
                $this->_query->setQueryText($this->getQueryText());
            }
        }
        return $this->_query;
    }

    /**
     * Is a minimum query length
     *
     * @return bool
     */
    public function isMinQueryLength()
    {
        $minQueryLength = $this->getMinQueryLength();
        $thisQueryLength = Mage::helper('core/string')->strlen($this->getQueryText());
        return !$thisQueryLength || $minQueryLength !== '' && $thisQueryLength < $minQueryLength;
    }

    /**
     * Retrieve search query text
     *
     * @return string
     */
    public function getQueryText()
    {
        if (!isset($this->_queryText)) {
            $this->_queryText = $this->_getRequest()->getParam($this->getQueryParamName());
            if ($this->_queryText === null) {
                $this->_queryText = '';
            } else {
                /** @var Mage_Core_Helper_String $stringHelper */
                $stringHelper = Mage::helper('core/string');
                $this->_queryText = is_array($this->_queryText) ? ''
                    : $stringHelper->cleanString(trim($this->_queryText));

                $maxQueryLength = $this->getMaxQueryLength();
                if ($maxQueryLength !== '' && $stringHelper->strlen($this->_queryText) > $maxQueryLength) {
                    $this->_queryText = $stringHelper->substr($this->_queryText, 0, $maxQueryLength);
                    $this->_isMaxLength = true;
                }
            }
        }
        return $this->_queryText;
    }

    /**
     * Retrieve HTML escaped search query
     *
     * @return string
     */
    public function getEscapedQueryText()
    {
        return $this->escapeHtml($this->getQueryText());
    }

    /**
     * Retrieve suggest collection for query
     *
     * @return Mage_CatalogSearch_Model_Resource_Query_Collection
     */
    public function getSuggestCollection()
    {
        return $this->getQuery()->getSuggestCollection();
    }

    /**
     * Retrieve result page url and set "secure" param to avoid confirm
     * message when we submit form from secure page to unsecure
     *
     * @param   string $query
     * @return  string
     */
    public function getResultUrl($query = null)
    {
        return $this->_getUrl('catalogsearch/result', [
            '_query' => [self::QUERY_VAR_NAME => $query],
            '_secure' => $this->_getApp()->getFrontController()->getRequest()->isSecure(),
        ]);
    }

    /**
     * Retrieve suggest url
     *
     * @return string
     */
    public function getSuggestUrl()
    {
        return $this->_getUrl('catalogsearch/ajax/suggest', [
            '_secure' => Mage::app()->isCurrentlySecure(),
        ]);
    }

    /**
     * Get App
     *
     * @return Mage_Core_Model_App
     */
    protected function _getApp()
    {
        return Mage::app();
    }

    /**
     * Retrieve advanced search URL
     *
     * @return string
     */
    public function getAdvancedSearchUrl()
    {
        return $this->_getUrl('catalogsearch/advanced');
    }

    /**
     * Retrieve minimum query length
     *
     * @param mixed $store
     * @return int|string
     */
    public function getMinQueryLength($store = null)
    {
        return Mage::getStoreConfig(Mage_CatalogSearch_Model_Query::XML_PATH_MIN_QUERY_LENGTH, $store);
    }

    /**
     * Retrieve maximum query length
     *
     * @param mixed $store
     * @return int|string
     */
    public function getMaxQueryLength($store = null)
    {
        return Mage::getStoreConfig(Mage_CatalogSearch_Model_Query::XML_PATH_MAX_QUERY_LENGTH, $store);
    }

    /**
     * Retrieve maximum query words count for like search
     *
     * @param mixed $store
     * @return int
     */
    public function getMaxQueryWords($store = null)
    {
        return Mage::getStoreConfig(Mage_CatalogSearch_Model_Query::XML_PATH_MAX_QUERY_WORDS, $store);
    }

    /**
     * Add Note message
     *
     * @param string $message
     * @return $this
     */
    public function addNoteMessage($message)
    {
        $this->_messages[] = $message;
        return $this;
    }

    /**
     * Set Note messages
     *
     * @return $this
     */
    public function setNoteMessages(array $messages)
    {
        $this->_messages = $messages;
        return $this;
    }

    /**
     * Retrieve Current Note messages
     *
     * @return array
     */
    public function getNoteMessages()
    {
        return $this->_messages;
    }

    /**
     * Check query of a warnings
     *
     * @param mixed $store
     */
    public function checkNotes($store = null)
    {
        if ($this->_isMaxLength) {
            $this->addNoteMessage($this->__('Maximum Search query length is %s. Your query was cut.', $this->getMaxQueryLength()));
        }

        /** @var Mage_Core_Helper_String $stringHelper */
        $stringHelper = Mage::helper('core/string');

        $searchType = Mage::getStoreConfig(Mage_CatalogSearch_Model_Fulltext::XML_PATH_CATALOG_SEARCH_TYPE);
        if ($searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE
            || $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_LIKE
        ) {
            $wordsFull = $stringHelper->splitWords($this->getQueryText(), true);
            $wordsLike = $stringHelper->splitWords($this->getQueryText(), true, $this->getMaxQueryWords());
            if (count($wordsFull) > count($wordsLike)) {
                $wordsCut = array_map([$this, 'escapeHtml'], array_diff($wordsFull, $wordsLike));
                $this->addNoteMessage(
                    $this->__('Maximum words count is %1$s. In your search query was cut next part: %2$s.', $this->getMaxQueryWords(), implode(' ', $wordsCut)),
                );
            }
        }
    }

    /**
     * Join index array to string by separator
     * Support 2 level array gluing
     *
     * @param array $index
     * @param string $separator
     * @return string
     */
    public function prepareIndexdata($index, $separator = ' ')
    {
        $_index = [];
        foreach ($index as $value) {
            if (!is_array($value)) {
                $_index[] = $value;
            } else {
                $_index = array_merge($_index, $value);
            }
        }
        return implode($separator, $_index);
    }

    /**
     * Get current search engine resource model
     *
     * @return object
     */
    public function getEngine()
    {
        if (!$this->_engine) {
            $engine = Mage::getStoreConfig('catalog/search/engine');

            /**
             * This needed if there already was saved in configuration some none-default engine
             * and module of that engine was disabled after that.
             * Problem is in this engine in database configuration still set.
             */
            if ($engine && Mage::getConfig()->getResourceModelClassName($engine)) {
                $model = Mage::getResourceSingleton($engine);
                if ($model && $model->test()) {
                    $this->_engine = $model;
                }
            }
            if (!$this->_engine) {
                $this->_engine = Mage::getResourceSingleton('catalogsearch/fulltext_engine');
            }
        }

        return $this->_engine;
    }

    public function isAdvancedSearchEnabled(): bool
    {
        return Mage::getStoreConfigFlag('catalog/advanced_search/enabled');
    }
}
