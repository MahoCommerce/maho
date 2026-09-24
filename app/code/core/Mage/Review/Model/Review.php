<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

/**
 * @method Mage_Review_Model_Resource_Review _getResource()
 * @method Mage_Review_Model_Resource_Review getResource()
 * @method Mage_Review_Model_Resource_Review_Collection getCollection()
 */
class Mage_Review_Model_Review extends Mage_Core_Model_Abstract
{
    /**
     * Event prefix for observer
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'review';

    /**
     * Review entity codes
     */
    public const ENTITY_PRODUCT_CODE   = 'product';
    public const ENTITY_CUSTOMER_CODE  = 'customer';
    public const ENTITY_CATEGORY_CODE  = 'category';

    public const STATUS_APPROVED       = 1;
    public const STATUS_PENDING        = 2;
    public const STATUS_NOT_APPROVED   = 3;

    #[\Override]
    protected function _construct()
    {
        $this->_init('review/review');
    }

    /**
     * @return Mage_Review_Model_Resource_Review_Product_Collection
     */
    public function getProductCollection()
    {
        return Mage::getResourceModel('review/review_product_collection');
    }

    /**
     * @return Mage_Review_Model_Resource_Review_Status_Collection
     */
    public function getStatusCollection()
    {
        return Mage::getResourceModel('review/review_status_collection');
    }

    /**
     * @param int $entityPkValue
     * @param bool $approvedOnly
     * @param int $storeId
     * @return string
     */
    public function getTotalReviews($entityPkValue, $approvedOnly = false, $storeId = 0)
    {
        return $this->getResource()->getTotalReviews($entityPkValue, $approvedOnly, $storeId);
    }

    /**
     * @return $this
     */
    public function aggregate()
    {
        $this->getResource()->aggregate($this);
        return $this;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     */
    public function getEntitySummary($product, $storeId = 0)
    {
        $product->setRatingSummary($product->getReviewSummary($storeId));
    }

    /**
     * @return int
     */
    public function getPendingStatus()
    {
        return self::STATUS_PENDING;
    }

    /**
     * @return array|bool
     */
    public function validate()
    {
        $errors = [];

        // Validate title
        if (!Mage::helper('core')->isValidNotBlank($this->getTitle())) {
            $errors[] = Mage::helper('review')->__('Review summary can\'t be empty');
        }

        // Validate nickname
        if (!Mage::helper('core')->isValidNotBlank($this->getNickname())) {
            $errors[] = Mage::helper('review')->__('Nickname can\'t be empty');
        }

        // Validate detail
        if (!Mage::helper('core')->isValidNotBlank($this->getDetail())) {
            $errors[] = Mage::helper('review')->__('Review can\'t be empty');
        }

        if (empty($errors)) {
            return true;
        }
        return $errors;
    }

    /**
     * Perform actions after object delete
     *
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    protected function _afterDeleteCommit()
    {
        $this->getResource()->afterDeleteCommit($this);
        return parent::_afterDeleteCommit();
    }

    /**
     * Append review summary to product collection
     *
     * @param Mage_Catalog_Model_Resource_Product_Collection $collection
     * @return $this
     */
    public function appendSummary($collection)
    {
        $entityIds = [];
        foreach ($collection->getItems() as $item) {
            $entityIds[] = $item->getId();
        }

        if (!count($entityIds)) {
            return $this;
        }

        $summaryData = Mage::getResourceModel('review/review_summary_collection')
            ->addEntityFilter($entityIds)
            ->addStoreFilter(Mage::app()->getStore()->getId())
            ->load();

        /** @var Mage_Review_Model_Review_Summary $summary */
        foreach ($summaryData as $summary) {
            if (($item = $collection->getItemById($summary->getEntityPkValue()))) {
                $item->setRatingSummary($summary);
            }
        }

        foreach ($collection->getItems() as $item) {
            if (!$item->hasData('rating_summary')) {
                $item->setRatingSummary(Mage::getModel('review/review_summary'));
            }
        }

        return $this;
    }

    /**
     * @return Mage_Core_Model_Abstract
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _beforeDelete()
    {
        $this->_protectFromNonAdmin();
        return parent::_beforeDelete();
    }

    /**
     * Check if current review approved or not
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->getStatusId() == self::STATUS_APPROVED;
    }

    /**
     * Check if current review available on passed store
     *
     * @param int|Mage_Core_Model_Store $store
     * @return bool
     */
    public function isAvailableOnStore($store = null)
    {
        $store = Mage::app()->getStore($store);
        if ($store) {
            return in_array($store->getId(), (array) $this->getStores());
        }

        return false;
    }

    /**
     * Get review entity type id by code
     *
     * @param string $entityCode
     * @return int|bool
     */
    public function getEntityIdByCode($entityCode)
    {
        return $this->getResource()->getEntityIdByCode($entityCode);
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

    public function getDetail(): ?string
    {
        $value = $this->getData('detail');
        return $value === null ? null : (string) $value;
    }

    public function setEntityId(?int $value): static
    {
        return $this->setData('entity_id', $value);
    }

    public function getEntityPkValue(): ?int
    {
        $value = $this->getData('entity_pk_value');
        return $value === null ? null : (int) $value;
    }

    public function setEntityPkValue(?int $value): static
    {
        return $this->setData('entity_pk_value', $value);
    }

    public function getNickname(): ?string
    {
        $value = $this->getData('nickname');
        return $value === null ? null : (string) $value;
    }

    public function setRatingVotes(?Mage_Rating_Model_Resource_Rating_Option_Vote_Collection $value): static
    {
        return $this->setData('rating_votes', $value);
    }

    public function getReviewId(): ?int
    {
        $value = $this->getData('review_id');
        return $value === null ? null : (int) $value;
    }

    public function getStatusId(): ?int
    {
        $value = $this->getData('status_id');
        return $value === null ? null : (int) $value;
    }

    public function setStatusId(?int $value): static
    {
        return $this->setData('status_id', $value);
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

    public function getStores(): ?array
    {
        return $this->getData('stores');
    }

    public function setStores(?array $value): static
    {
        return $this->setData('stores', $value);
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

}
