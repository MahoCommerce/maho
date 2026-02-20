<?php

/**
 * Maho
 *
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Review_Model_Resource_Review _getResource()
 * @method Mage_Review_Model_Resource_Review getResource()
 * @method Mage_Review_Model_Resource_Review_Collection getCollection()
 *
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method array getCustomerId()
 * @method $this setCustomerId(int $value)
 * @method string getDetail()
 * @method $this setEntityId(int $value)
 * @method int getEntityPkValue()
 * @method $this setEntityPkValue(int $value)
 * @method string getNickname()
 * @method $this setRatingVotes(Mage_Rating_Model_Resource_Rating_Option_Vote_Collection $collection)
 * @method int getReviewId()
 * @method int getStatusId()
 * @method $this setStatusId(int $value)
 * @method $this setStoreId(int $value)
 * @method int getStoreId()
 * @method array getStores()
 * @method $this setStores(array $value)
 * @method string getTitle()
 */
class Mage_Review_Model_Review extends Mage_Core_Model_Abstract
{
    /**
     * Event prefix for observer
     *
     * @var string
     */
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
     * @return string
     */
    public function getReviewUrl()
    {
        return Mage::getUrl('review/product/view', ['id' => $this->getReviewId()]);
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
}
