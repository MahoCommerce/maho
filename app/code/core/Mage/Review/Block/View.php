<?php

/**
 * Maho
 *
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Rating_Model_Resource_Rating_Option_Vote_Collection|false getRatingCollection()
 * @method $this setRatingCollection(Mage_Rating_Model_Resource_Rating_Option_Vote_Collection|false $value)
 * @method array getRatingSummaryCache()
 * @method setRatingSummaryCache(array $value)
 * @method int getReviewId()
 * @method int getTotalReviewsCache()
 * @method $this setTotalReviewsCache(int $entityPkValue, bool $approvedOnly, int $storeId)
 */
class Mage_Review_Block_View extends Mage_Catalog_Block_Product_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('review/view.phtml');
    }

    /**
     * Retrieve current product model from registry
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProductData()
    {
        return Mage::registry('current_product');
    }

    /**
     * Retrieve current review model from registry
     *
     * @return Mage_Review_Model_Review
     */
    public function getReviewData()
    {
        return Mage::registry('current_review');
    }

    /**
     * Prepare link to review list for current product
     *
     * @return string
     */
    public function getBackUrl()
    {
        return Mage::getUrl('*/*/list', ['id' => $this->getProductData()->getId()]);
    }

    /**
     * Retrieve collection of ratings
     *
     * @return false|Mage_Rating_Model_Resource_Rating_Option_Vote_Collection
     */
    public function getRating()
    {
        if (!$this->getRatingCollection()) {
            $ratingCollection = Mage::getModel('rating/rating_option_vote')
                ->getResourceCollection()
                ->setReviewFilter($this->getReviewId())
                ->setStoreFilter(Mage::app()->getStore()->getId())
                ->addRatingInfo(Mage::app()->getStore()->getId())
                ->load();
            $this->setRatingCollection(($ratingCollection->getSize()) ? $ratingCollection : false);
        }
        return $this->getRatingCollection();
    }

    /**
     * Retrieve rating summary for current product
     *
     * @return array
     */
    public function getRatingSummary()
    {
        if (!$this->getRatingSummaryCache()) {
            $this->setRatingSummaryCache(Mage::getModel('rating/rating')->getEntitySummary($this->getProductData()->getId()));
        }
        return $this->getRatingSummaryCache();
    }

    /**
     * Retrieve total review count for current product
     *
     * @return string
     */
    public function getTotalReviews()
    {
        if (!$this->getTotalReviewsCache()) {
            $this->setTotalReviewsCache(Mage::getModel('review/review')->getTotalReviews($this->getProductData()->getId(), false, Mage::app()->getStore()->getId()));
        }
        return $this->getTotalReviewsCache();
    }

    /**
     * Format date in long format
     *
     * @param string $date
     * @return string
     */
    public function dateFormat($date)
    {
        return $this->formatDate($date, Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
    }
}
