<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/**
 * @method Mage_Core_Model_Resource_Url_Rewrite _getResource()
 * @method Mage_Core_Model_Resource_Url_Rewrite getResource()
 * @method Mage_Core_Model_Resource_Url_Rewrite_Collection getResourceCollection()
 *
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
    #[\Override]
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

    public function getCategoryId(): ?int
    {
        $value = $this->getData('category_id');
        return $value === null ? null : (int) $value;
    }

    public function setCategoryId(?int $value): static
    {
        return $this->setData('category_id', $value);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): static
    {
        return $this->setData('description', $value);
    }

    public function getIdPath(): ?string
    {
        $value = $this->getData('id_path');
        return $value === null ? null : (string) $value;
    }

    public function setIdPath(?string $value): static
    {
        return $this->setData('id_path', $value);
    }

    public function getIsSystem(): ?bool
    {
        $value = $this->getData('is_system');
        return $value === null ? null : (bool) $value;
    }

    public function setIsSystem(?bool $value = true): static
    {
        return $this->setData('is_system', $value);
    }

    public function getOptions(): ?string
    {
        $value = $this->getData('options');
        return $value === null ? null : (string) $value;
    }

    public function setOptions(?string $value): static
    {
        return $this->setData('options', $value);
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getRequestPath(): ?string
    {
        $value = $this->getData('request_path');
        return $value === null ? null : (string) $value;
    }

    public function setRequestPath(?string $value): static
    {
        return $this->setData('request_path', $value);
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getTags(): array|string|null
    {
        return $this->getData('tags');
    }

    public function setTags(array|string|null $value): static
    {
        return $this->setData('tags', $value);
    }

    public function getTargetPath(): ?string
    {
        $value = $this->getData('target_path');
        return $value === null ? null : (string) $value;
    }

    public function setTargetPath(?string $value): static
    {
        return $this->setData('target_path', $value);
    }

}
