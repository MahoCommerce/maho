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

        if (!$this->getRequest()->isDispatched()) {
            return;
        }

        $action = strtolower($this->getRequest()->getActionName());
        if ($action == 'post' && $this->getRequest()->isPost()
            && !$this->reviewService()->isGuestAllowed((int) Mage::app()->getStore()->getId())
        ) {
            if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
                $this->setFlag('', self::FLAG_NO_DISPATCH, true);
                // Not the current URL: review/product/post is POST-only, so it would 405.
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
        try {
            $product = $this->reviewService()->getProduct((int) $productId, (int) Mage::app()->getStore()->getId());
        } catch (Mage_Core_Exception_NoSuchEntity) {
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
            $review = Mage::getModel('review/review')->setData($this->_cropReviewData($data))
                ->setEntityPkValue((int) $product->getId());
            $customerId = Mage::getSingleton('customer/session')->getCustomerId();
            try {
                $this->reviewService()->submit(
                    $review,
                    is_array($rating) ? $rating : [],
                    (int) Mage::app()->getStore()->getId(),
                    $customerId ? (int) $customerId : null,
                );
                $session->addSuccess($this->__('Your review has been accepted for moderation.'));
            } catch (Mage_Core_Exception_Input $e) {
                $session->setFormData($data);
                foreach ($e->getErrors() as $error) {
                    $session->addError($error['message']);
                }
            } catch (Mage_Core_Exception $e) {
                $session->setFormData($data);
                $session->addError($e->getMessage());
            } catch (Throwable $e) {
                Mage::logException($e);
                $session->setFormData($data);
                $session->addError($this->__('Unable to post the review.'));
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

    protected function reviewService(): Mage_Review_Service_Review
    {
        return Mage::getService('review/review');
    }
}
