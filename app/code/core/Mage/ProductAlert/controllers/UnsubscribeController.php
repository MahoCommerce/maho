<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

class Mage_ProductAlert_UnsubscribeController extends Mage_Core_Controller_Front_Action
{
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
            if (!Mage::getSingleton('customer/session')->getBeforeUrl()) {
                Mage::getSingleton('customer/session')->setBeforeUrl($this->_getRefererUrl());
            }
        }
        return $this;
    }

    #[Maho\Config\Route('/productalert/unsubscribe/price', name: 'productalert.unsubscribe.price')]
    public function priceAction(): void
    {
        $productId  = (int) $this->getRequest()->getParam('product');

        if (!$productId) {
            $this->_redirect('');
            return;
        }
        $session    = Mage::getSingleton('catalog/session');

        /** @var Mage_Catalog_Model_Session $session */
        $product = Mage::getModel('catalog/product')->load($productId);
        if (!$product->getId() || !$product->isVisibleInCatalog()) {
            /** @var Mage_Catalog_Model_Product $product */
            Mage::getSingleton('customer/session')->addError($this->__('The product is not found.'));
            $this->_redirect('customer/account/');
            return ;
        }

        try {
            $this->unsubscribe(Mage_ProductAlert_Model_Type::Price, $productId);
            $session->addSuccess($this->__('The alert subscription has been deleted.'));
        } catch (Exception $e) {
            $session->addException($e, $this->__('Unable to update the alert subscription.'));
        }
        $this->_redirectUrl($product->getProductUrl());
    }

    #[Maho\Config\Route('/productalert/unsubscribe/priceAll', name: 'productalert.unsubscribe.priceall')]
    public function priceAllAction(): void
    {
        $session = Mage::getSingleton('customer/session');
        /** @var Mage_Customer_Model_Session $session */

        try {
            Mage::getService('productalert/alert')->unsubscribeAll(
                Mage_ProductAlert_Model_Type::Price,
                (int) $session->getCustomerId(),
                (int) Mage::app()->getStore()->getId(),
            );
            $session->addSuccess($this->__('You will no longer receive price alerts for this product.'));
        } catch (Exception $e) {
            $session->addException($e, $this->__('Unable to update the alert subscription.'));
        }
        $this->_redirect('customer/account/');
    }

    #[Maho\Config\Route('/productalert/unsubscribe/stock', name: 'productalert.unsubscribe.stock')]
    public function stockAction(): void
    {
        $productId  = (int) $this->getRequest()->getParam('product');

        if (!$productId) {
            $this->_redirect('');
            return;
        }

        $session = Mage::getSingleton('catalog/session');
        $product = Mage::getModel('catalog/product')->load($productId);

        if (!$product->getId() || !$product->isVisibleInCatalog()) {
            Mage::getSingleton('customer/session')->addError($this->__('The product was not found.'));
            $this->_redirect('customer/account/');
            return ;
        }

        try {
            $this->unsubscribe(Mage_ProductAlert_Model_Type::Stock, $productId);
            $session->addSuccess($this->__('You will no longer receive stock alerts for this product.'));
        } catch (Exception $e) {
            $session->addException($e, $this->__('Unable to update the alert subscription.'));
        }
        $this->_redirectUrl($product->getProductUrl());
    }

    #[Maho\Config\Route('/productalert/unsubscribe/stockAll', name: 'productalert.unsubscribe.stockall')]
    public function stockAllAction(): void
    {
        $session = Mage::getSingleton('customer/session');

        try {
            Mage::getService('productalert/alert')->unsubscribeAll(
                Mage_ProductAlert_Model_Type::Stock,
                (int) $session->getCustomerId(),
                (int) Mage::app()->getStore()->getId(),
            );
            $session->addSuccess($this->__('You will no longer receive stock alerts.'));
        } catch (Exception $e) {
            $session->addException($e, $this->__('Unable to update the alert subscription.'));
        }
        $this->_redirect('customer/account/');
    }

    /**
     * Delete the alert of $type that the customer has for the product $productId. A link that the customer opens a
     * second time finds no alert, and gets the same answer.
     */
    private function unsubscribe(Mage_ProductAlert_Model_Type $type, int $productId): void
    {
        try {
            Mage::getService('productalert/alert')->unsubscribe(
                $type,
                (int) Mage::getSingleton('customer/session')->getCustomerId(),
                $productId,
                (int) Mage::app()->getStore()->getId(),
            );
        } catch (Mage_Core_Exception_NoSuchEntity) {
            return;
        }
    }
}
