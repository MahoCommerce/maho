<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
 */

/**
 * @method Mage_Tag_Model_Resource_Tag_Relation _getResource()
 * @method Mage_Tag_Model_Resource_Tag_Relation getResource()
 * @method bool hasStoreId()
 */
class Mage_Tag_Model_Tag_Relation extends Mage_Core_Model_Abstract
{
    /**
     * Relation statuses
     */
    public const STATUS_ACTIVE     = 1;
    public const STATUS_NOT_ACTIVE = 0;

    /**
     * Entity code.
     * Can be used as part of method name for entity processing
     */
    public const ENTITY = 'tag_relation';

    #[\Override]
    protected function _construct()
    {
        $this->_init('tag/tag_relation');
    }

    /**
     * Init indexing process after tag data commit
     *
     * @return $this
     */
    #[\Override]
    public function afterCommitCallback()
    {
        parent::afterCommitCallback();
        Mage::getSingleton('index/indexer')->processEntityAction(
            $this,
            self::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE,
        );
        return $this;
    }

    /**
     * Load relation by Product (optional), tag, customer and store
     *
     * @param int|null $productId
     * @param int $tagId
     * @param int $customerId
     * @param int|null $storeId
     * @return $this
     */
    public function loadByTagCustomer($productId, $tagId, $customerId, $storeId = null)
    {
        $this->setProductId($productId);
        $this->setTagId($tagId);
        $this->setCustomerId($customerId);
        if (!is_null($storeId)) {
            $this->setStoreId($storeId);
        }
        $this->_getResource()->loadByTagCustomer($this);
        return $this;
    }

    /**
     * Retrieve Relation Product Ids
     *
     * @return array
     */
    public function getProductIds()
    {
        $ids = $this->getData('product_ids');
        if (is_null($ids)) {
            $ids = $this->_getResource()->getProductIds($this);
            $this->setProductIds($ids);
        }
        return $ids;
    }

    /**
     * Retrieve list of related tag ids for products specified in current object
     *
     * @return array
     */
    public function getRelatedTagIds()
    {
        if (is_null($this->getData('related_tag_ids'))) {
            $this->setRelatedTagIds($this->_getResource()->getRelatedTagIds($this));
        }
        return $this->getData('related_tag_ids');
    }

    /**
     * Deactivate tag relations (using current settings)
     *
     * @return $this
     */
    public function deactivate()
    {
        $this->_getResource()->deactivate($this->getTagId(), $this->getCustomerId());
        return $this;
    }

    /**
     * Add TAG to PRODUCT relations
     *
     * @param array $productIds
     * @return $this
     */
    public function addRelations(Mage_Tag_Model_Tag $model, $productIds = [])
    {
        $this->setAddedProductIds($productIds);
        $this->setTagId($model->getTagId());
        $this->setCustomerId(null);
        $this->setStoreId($model->getStore());
        $this->_getResource()->addRelations($this);
        return $this;
    }

    public function getActive(): ?bool
    {
        $value = $this->getData('active');
        return $value === null ? null : (bool) $value;
    }

    public function setActive(?bool $value = true): static
    {
        return $this->setData('active', $value);
    }

    public function getAddedProductIds(): ?array
    {
        return $this->getData('added_product_ids');
    }

    public function setAddedProductIds(?array $value): static
    {
        return $this->setData('added_product_ids', $value);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getProductId(): array|int|null
    {
        $value = $this->getData('product_id');
        return $value === null || is_array($value) ? $value : (int) $value;
    }

    public function setProductId(array|int|null $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function setProductIds(?array $value): static
    {
        return $this->setData('product_ids', $value);
    }

    public function setRelatedTagIds(?array $value): static
    {
        return $this->setData('related_tag_ids', $value);
    }

    public function getStatusFilter(): array|int|string|null
    {
        return $this->getData('status_filter');
    }

    public function setStatusFilter(array|int|string|null $value): static
    {
        return $this->setData('status_filter', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getTagId(): ?int
    {
        $value = $this->getData('tag_id');
        return $value === null ? null : (int) $value;
    }

    public function setTagId(?int $value): static
    {
        return $this->setData('tag_id', $value);
    }

}
