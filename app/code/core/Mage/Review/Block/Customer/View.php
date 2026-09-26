<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

class Mage_Review_Block_Customer_View extends Mage_Catalog_Block_Product_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('review/customer/view.phtml');

        $this->setReviewId((int) $this->getRequest()->getParam('id'));
    }

    /**
     * @return Mage_Catalog_Model_Product
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getProductData()
    {
        if ($this->getReviewId() && !$this->getProductCacheData()) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($this->getReviewData()->getEntityPkValue());
            $this->setProductCacheData($product);
        }
        return $this->getProductCacheData();
    }

    /**
     * @return Mage_Review_Model_Review
     */
    public function getReviewData()
    {
        if ($this->getReviewId() && !$this->getReviewCachedData()) {
            $this->setReviewCachedData(Mage::getModel('review/review')->load($this->getReviewId()));
        }
        return $this->getReviewCachedData();
    }

    /**
     * @return string
     */
    public function getBackUrl()
    {
        return Mage::getUrl('review/customer');
    }

    /**
     * @return Mage_Rating_Model_Resource_Rating_Option_Vote_Collection
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getRating()
    {
        if (!$this->getRatingCollection()) {
            $ratingCollection = Mage::getModel('rating/rating_option_vote')
                ->getResourceCollection()
                ->setReviewFilter($this->getReviewId())
                ->addRatingInfo(Mage::app()->getStore()->getId())
                ->setStoreFilter(Mage::app()->getStore()->getId())
                ->load();

            $this->setRatingCollection(($ratingCollection->getSize()) ? $ratingCollection : false);
        }

        return $this->getRatingCollection();
    }

    /**
     * @return Mage_Rating_Model_Rating|array
     */
    public function getRatingSummary()
    {
        if (!$this->getRatingSummaryCache()) {
            $this->setRatingSummaryCache(Mage::getModel('rating/rating')->getEntitySummary($this->getProductData()->getId()));
        }
        return $this->getRatingSummaryCache();
    }

    /**
     * @return int
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getTotalReviews()
    {
        if (!$this->getTotalReviewsCache()) {
            $this->setTotalReviewsCache((int) Mage::getModel('review/review')->getTotalReviews(
                $this->getProductData()->getId(),
                false,
                Mage::app()->getStore()->getId(),
            ));
        }
        return $this->getTotalReviewsCache();
    }

    /**
     * @param string $date
     * @return string
     */
    public function dateFormat($date)
    {
        return $this->formatDate($date, Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
    }

    /**
     * Check whether current customer is review owner
     *
     * @return bool
     */
    public function isReviewOwner()
    {
        return ($this->getReviewData()->getCustomerId() == Mage::getSingleton('customer/session')->getCustomerId());
    }

    public function getReviewId(): ?int
    {
        $value = $this->getData('review_id');
        return $value === null ? null : (int) $value;
    }

    public function setReviewId(?int $value): static
    {
        return $this->setData('review_id', $value);
    }

    public function getProductCacheData(): ?Mage_Catalog_Model_Product
    {
        return $this->getData('product_cache_data');
    }

    public function setProductCacheData(?Mage_Catalog_Model_Product $value): static
    {
        return $this->setData('product_cache_data', $value);
    }

    public function getRatingCollection(): Mage_Rating_Model_Resource_Rating_Option_Vote_Collection|false|null
    {
        return $this->getData('rating_collection');
    }

    public function setRatingCollection(Mage_Rating_Model_Resource_Rating_Option_Vote_Collection|false|null $value): static
    {
        return $this->setData('rating_collection', $value);
    }

    public function getRatingSummaryCache(): Mage_Rating_Model_Rating|array|null
    {
        return $this->getData('rating_summary_cache');
    }

    public function setRatingSummaryCache(Mage_Rating_Model_Rating|array|null $value): static
    {
        return $this->setData('rating_summary_cache', $value);
    }

    public function getReviewCachedData(): ?Mage_Review_Model_Review
    {
        return $this->getData('review_cached_data');
    }

    public function setReviewCachedData(?Mage_Review_Model_Review $value): static
    {
        return $this->setData('review_cached_data', $value);
    }

    public function getTotalReviewsCache(): ?int
    {
        $value = $this->getData('total_reviews_cache');
        return $value === null ? null : (int) $value;
    }

    public function setTotalReviewsCache(?int $value): static
    {
        return $this->setData('total_reviews_cache', $value);
    }
}
