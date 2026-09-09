<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

class Mage_CatalogSearch_ResultController extends Mage_Core_Controller_Front_Action
{
    /**
     * Retrieve catalog session
     *
     * @return Mage_Catalog_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('catalog/session');
    }
    /**
     * Display search result
     */
    #[Maho\Config\Route('/catalogsearch/result', name: 'catalogsearch.result.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $helper = Mage::helper('catalogsearch');
        $query = $helper->getQuery();
        /** @var Mage_CatalogSearch_Model_Query $query */

        $query->setStoreId(Mage::app()->getStore()->getId());

        // The term is persisted once, after the search, and only while the client is inside
        // its analytics budget. A blocked client still gets the full result page.
        $canLog = false;

        if ($query->getQueryText() != '') {
            if ($helper->isMinQueryLength()) {
                $query->setId(0)
                    ->setIsActive(1)
                    ->setIsProcessed(1);
            } else {
                $canLog = $helper->canLogQuery();
                if ($canLog) {
                    $query->setPopularity($query->getId() ? $query->getPopularity() + 1 : 1);
                }

                if ($query->getRedirect()) {
                    if ($canLog) {
                        $query->save();
                    }
                    $this->getResponse()->setRedirect($query->getRedirect());
                    return;
                }
            }

            $helper->checkNotes();

            $this->loadLayout();
            $this->_initLayoutMessages('catalog/session');
            $this->_initLayoutMessages('checkout/session');
            $this->renderLayout();

            if ($canLog) {
                $query->save();
            }

            // Redirect to product if there's only one result
            if (Mage::getStoreConfigFlag('catalog/search/redirect_to_product_if_one_result')) {
                $searchResultBlock = Mage::app()->getLayout()->getBlock('search_result_list');
                if ($searchResultBlock) {
                    /** @var Mage_CatalogSearch_Model_Resource_Fulltext_Collection $productCollection */
                    $productCollection = $searchResultBlock->getLoadedProductCollection();
                    if ($productCollection && $productCollection->getSize() === 1) {
                        $product = $productCollection->getFirstItem();
                        $this->_redirectUrl($product->getProductUrl());
                    }
                }
            }
        } else {
            $this->_redirectReferer();
        }
    }
}
