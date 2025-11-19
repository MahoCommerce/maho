<?php

/**
 * Maho
 *
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Review_Block_Product_View extends Mage_Catalog_Block_Product_View
{
    protected $_reviewsCollection;

    /**
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _toHtml()
    {
        $this->getProduct()->setShortDescription(null);

        return parent::_toHtml();
    }

    /**
     * Replace review summary html with more detailed review summary
     * Reviews collection count will be jerked here
     *
     * @param string|false $templateType
     * @param bool $displayIfNoReviews
     * @return string
     * @throws Mage_Core_Model_Store_Exception|Mage_Core_Exception
     */
    #[\Override]
    public function getReviewsSummaryHtml(Mage_Catalog_Model_Product $product, $templateType = false, $displayIfNoReviews = false)
    {
        /** @var Mage_Core_Block_Template $reviewContBlock */
        $reviewContBlock = $this->getLayout()->getBlock('product_review_list.count');
        return
            $this->getLayout()->createBlock('rating/entity_detailed')
                ->setEntityId($this->getProduct()->getId())
                ->toHtml()
            .
            $reviewContBlock
                ->assign('count', $this->getReviewsCollection()->getSize())
                ->toHtml()
        ;
    }

    /**
     * @return Mage_Review_Model_Resource_Review_Collection
     * @throws Mage_Core_Model_Store_Exception|Mage_Core_Exception
     */
    public function getReviewsCollection()
    {
        if ($this->_reviewsCollection === null) {
            $this->_reviewsCollection = Mage::getModel('review/review')->getCollection()
                ->addStoreFilter(Mage::app()->getStore()->getId())
                ->addStatusFilter(Mage_Review_Model_Review::STATUS_APPROVED)
                ->addEntityFilter('product', $this->getProduct()->getId())
                ->setDateOrder();
        }
        return $this->_reviewsCollection;
    }

    /**
     * Force product view page behave like without options
     *
     * @return false
     */
    #[\Override]
    public function hasOptions()
    {
        return false;
    }
}
