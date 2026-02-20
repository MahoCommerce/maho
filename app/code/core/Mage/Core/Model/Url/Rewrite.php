<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Core_Model_Resource_Url_Rewrite _getResource()
 * @method Mage_Core_Model_Resource_Url_Rewrite getResource()
 * @method Mage_Core_Model_Resource_Url_Rewrite_Collection getResourceCollection()
 *
 * @method $this setStoreId(int $value)
 * @method int getCategoryId()
 * @method $this setCategoryId(int $value)
 * @method int getProductId()
 * @method $this setProductId(int $value)
 * @method string getIdPath()
 * @method $this setIdPath(string $value)
 * @method string getRequestPath()
 * @method $this setRequestPath(string $value)
 * @method string getTargetPath()
 * @method $this setTargetPath(string $value)
 * @method int getIsSystem()
 * @method $this setIsSystem(int $value)
 * @method string getOptions()
 * @method $this setOptions(string $value)
 * @method string getDescription()
 * @method $this setDescription(string $value)
 * @method string|array getTags()
 * @method $this setTags(string|array $value)
 * @method bool hasCategoryId()
 */
class Mage_Core_Model_Url_Rewrite extends Mage_Core_Model_Abstract implements Mage_Core_Model_Url_Rewrite_Interface
{
    public const TYPE_CATEGORY = 1;
    public const TYPE_PRODUCT  = 2;
    public const TYPE_CUSTOM   = 3;
    public const REWRITE_REQUEST_PATH_ALIAS = 'rewrite_request_path';

    /**
     * Cache tag for clear cache in after save and after delete
     *
     * @var string|bool|array
     */
    protected $_cacheTag = false;

    #[\Override]
    protected function _construct()
    {
        $this->_init('core/url_rewrite');
    }

    /**
     * Clean cache for front-end menu
     *
     * @return  Mage_Core_Model_Url_Rewrite
     */
    #[\Override]
    protected function _afterSave()
    {
        if ($this->hasCategoryId()) {
            $this->_cacheTag = [Mage_Catalog_Model_Category::CACHE_TAG, Mage_Core_Model_Store_Group::CACHE_TAG];
        }

        parent::_afterSave();

        return $this;
    }

    /**
     * Load rewrite information for request
     * If $path is array - we must load possible records and choose one matching earlier record in array
     *
     * @param   mixed $path
     * @return  Mage_Core_Model_Url_Rewrite
     */
    #[\Override]
    public function loadByRequestPath($path)
    {
        $this->setId(null);
        $this->_getResource()->loadByRequestPath($this, $path);
        $this->_afterLoad();
        $this->setOrigData();
        $this->_hasDataChanges = false;
        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function loadByIdPath($path)
    {
        $this->setId(null)->load($path, 'id_path');
        return $this;
    }

    /**
     * @param string|array $tags
     * @return $this
     */
    public function loadByTags($tags)
    {
        $this->setId(null);

        $loadTags = is_array($tags) ? $tags : explode(',', $tags);

        $search = $this->getResourceCollection();
        foreach ($loadTags as $k => $t) {
            if (!is_numeric($k)) {
                $t = $k . '=' . $t;
            }
            $search->addTagsFilter($t);
        }
        if (!is_null($this->getStoreId())) {
            $search->addStoreFilter($this->getStoreId());
        }

        $search->setPageSize(1)->load();

        if ($search->getSize() > 0) {
            /** @var Mage_Core_Model_Url_Rewrite $rewrite */
            foreach ($search as $rewrite) {
                $this->setData($rewrite->getData());
            }
        }

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasOption($key)
    {
        $optArr = explode(',', (string) $this->getOptions());

        return in_array($key, $optArr);
    }

    /**
     * @param string|array $tags
     * @return $this
     */
    public function addTag($tags)
    {
        $curTags = $this->getTags();

        $addTags = is_array($tags) ? $tags : explode(',', $tags);

        foreach ($addTags as $k => $t) {
            if (!is_numeric($k)) {
                $t = $k . '=' . $t;
            }
            if (!in_array($t, $curTags)) {
                $curTags[] = $t;
            }
        }

        $this->setTags($curTags);

        return $this;
    }

    /**
     * @param string|array $tags
     * @return $this
     */
    public function removeTag($tags)
    {
        $curTags = $this->getTags();

        $removeTags = is_array($tags) ? $tags : explode(',', $tags);

        foreach ($removeTags as $k => $t) {
            if (!is_numeric($k)) {
                $t = $k . '=' . $t;
            }
            if ($key = array_search($t, $curTags)) {
                unset($curTags[$key]);
            }
        }

        $this->setTags(',', $curTags);

        return $this;
    }

    /**
     * @return int|null
     */
    public function getStoreId()
    {
        return $this->_getData('store_id');
    }
}
