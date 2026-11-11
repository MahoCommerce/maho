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
        /** @var Mage_CatalogSearch_Service_Query $service */
        $service = Mage::getService('catalogsearch/query');
        $text = $this->getRequest()->getParam(Mage::helper('catalogsearch')->getQueryParamName());
        $query = $service->prepare((int) Mage::app()->getStore()->getId(), is_string($text) ? $text : '');

        if ($query->getQueryText() != '') {
            $redirect = $service->getRedirect($query);
            if ($redirect !== null) {
                $service->log($query);
                $this->getResponse()->setRedirect($redirect);
                return;
            }

            if (!Mage::getSingleton('catalogsearch/layer')->isValidRequest($this->getRequest())) {
                $this->_forward('noRoute');
                return;
            }

            Mage::helper('catalogsearch')->checkNotes();

            $this->loadLayout();
            $this->_initLayoutMessages('catalog/session');
            $this->_initLayoutMessages('checkout/session');
            $this->renderLayout();

            // The result block sets the number of results during the render
            $service->log($query);

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
