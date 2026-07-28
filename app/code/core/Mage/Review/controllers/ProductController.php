<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
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
                // Not the current URL: review/product/post is POST-only, so replaying it with
                // the post-login GET answers 405. The referring product page re-renders the
                // form data preserved just below.
                Mage::getSingleton('customer/session')->setBeforeAuthUrl($this->_getRefererUrl());
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
     * Submit new review action
     */
    #[Maho\Config\Route('/review/product/post', name: 'review.product.post', methods: ['POST'])]
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
