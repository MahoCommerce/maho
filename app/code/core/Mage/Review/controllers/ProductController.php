<?php

/**
 * Maho
 *
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Review_ProductController extends Mage_Core_Controller_Front_Action
{
    /**
     * @return $this|Mage_Core_Controller_Front_Action|void
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        $allowGuest = Mage::helper('review')->getIsGuestAllowToWrite();
        if (!$this->getRequest()->isDispatched()) {
            return;
        }

        $action = strtolower($this->getRequest()->getActionName());
        if (!$allowGuest && $action == 'post' && $this->getRequest()->isPost()) {
            if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
                $this->setFlag('', self::FLAG_NO_DISPATCH, true);
                Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::getUrl('*/*/*', ['_current' => true]));
                Mage::getSingleton('review/session')->setFormData($this->getRequest()->getPost())
                    ->setRedirectUrl($this->_getRefererUrl());
                $this->_redirectUrl(Mage::helper('customer')->getLoginUrl());
            }
        }

        return $this;
    }
    /**
     * Initialize and check product
     *
     * @return Mage_Catalog_Model_Product|false
     */
    protected function _initProduct()
    {
        Mage::dispatchEvent('review_controller_product_init_before', ['controller_action' => $this]);
        $categoryId = (int) $this->getRequest()->getParam('category', false);
        $productId  = (int) $this->getRequest()->getParam('id');

        $product = $this->_loadProduct($productId);
        if (!$product) {
            return false;
        }

        if ($categoryId) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            Mage::register('current_category', $category);
        }

        try {
            Mage::dispatchEvent('review_controller_product_init', ['product' => $product]);
            Mage::dispatchEvent('review_controller_product_init_after', [
                'product'           => $product,
                'controller_action' => $this,
            ]);
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            return false;
        }

        return $product;
    }

    /**
     * Load product model with data by passed id.
     * Return false if product was not loaded or has incorrect status.
     *
     * @param int $productId
     * @return bool|Mage_Catalog_Model_Product
     */
    protected function _loadProduct($productId)
    {
        if (!$productId) {
            return false;
        }

        $product = Mage::getModel('catalog/product')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($productId);
        /** @var Mage_Catalog_Model_Product $product */
        if (!$product->getId() || !$product->isVisibleInCatalog() || !$product->isVisibleInSiteVisibility()) {
            return false;
        }

        Mage::register('current_product', $product);
        Mage::register('product', $product);

        return $product;
    }

    /**
     * Load review model with data by passed id.
     * Return false if review was not loaded or review is not approved.
     *
     * @param int $reviewId
     * @return bool|Mage_Review_Model_Review
     */
    protected function _loadReview($reviewId)
    {
        if (!$reviewId) {
            return false;
        }

        $review = Mage::getModel('review/review')->load($reviewId);
        /** @var Mage_Review_Model_Review $review */
        if (!$review->getId() || !$review->isApproved() || !$review->isAvailableOnStore(Mage::app()->getStore())) {
            return false;
        }

        Mage::register('current_review', $review);

        return $review;
    }

    /**
     * Submit new review action
     */
    public function postAction(): void
    {
        if (!$this->_validateFormKey()) {
            // returns to the product item page
            $this->_redirectReferer();
            return;
        }

        if ($data = Mage::getSingleton('review/session')->getFormData(true)) {
            $rating = [];
            if (isset($data['ratings']) && is_array($data['ratings'])) {
                $rating = $data['ratings'];
            }
        } else {
            $data   = $this->getRequest()->getPost();
            $rating = $this->getRequest()->getParam('ratings', []);
        }

        if (($product = $this->_initProduct()) && !empty($data)) {
            $session = Mage::getSingleton('core/session');
            /** @var Mage_Core_Model_Session $session */
            $review = Mage::getModel('review/review')->setData($this->_cropReviewData($data));
            /** @var Mage_Review_Model_Review $review */

            $validate = $review->validate();
            if ($validate === true) {
                try {
                    $review->setEntityId($review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
                        ->setEntityPkValue($product->getId())
                        ->setStatusId(Mage_Review_Model_Review::STATUS_PENDING)
                        ->setCustomerId(Mage::getSingleton('customer/session')->getCustomerId())
                        ->setStoreId(Mage::app()->getStore()->getId())
                        ->setStores([Mage::app()->getStore()->getId()])
                        ->save();

                    foreach ($rating as $ratingId => $optionId) {
                        Mage::getModel('rating/rating')
                        ->setRatingId($ratingId)
                        ->setReviewId($review->getId())
                        ->setCustomerId(Mage::getSingleton('customer/session')->getCustomerId())
                        ->addOptionVote($optionId, $product->getId());
                    }

                    $review->aggregate();
                    $session->addSuccess($this->__('Your review has been accepted for moderation.'));
                } catch (Exception $e) {
                    $session->setFormData($data);
                    $session->addError($this->__('Unable to post the review.'));
                }
            } else {
                $session->setFormData($data);
                if (is_array($validate)) {
                    foreach ($validate as $errorMessage) {
                        $session->addError($errorMessage);
                    }
                } else {
                    $session->addError($this->__('Unable to post the review.'));
                }
            }
        }

        if ($redirectUrl = Mage::getSingleton('review/session')->getRedirectUrl(true)) {
            $this->_redirectUrl($redirectUrl);
            return;
        }
        $this->_redirectReferer();
    }

    /**
     * Show details of one review
     */
    public function viewAction(): void
    {
        $review = $this->_loadReview((int) $this->getRequest()->getParam('id'));
        if (!$review) {
            $this->_forward('noroute');
            return;
        }

        $product = $this->_loadProduct($review->getEntityPkValue());
        if (!$product) {
            $this->_forward('noroute');
            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('review/session');
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }

    /**
     * Crops POST values
     * @return array
     */
    protected function _cropReviewData(array $reviewData)
    {
        $croppedValues = [];
        $allowedKeys = array_fill_keys(['detail', 'title', 'nickname'], true);

        foreach ($reviewData as $key => $value) {
            if (isset($allowedKeys[$key])) {
                $croppedValues[$key] = $value;
            }
        }

        return $croppedValues;
    }
}
