<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
    public function indexAction(): void
    {
        $query = Mage::helper('catalogsearch')->getQuery();
        /** @var Mage_CatalogSearch_Model_Query $query */

        $query->setStoreId(Mage::app()->getStore()->getId());

        if ($query->getQueryText() != '') {
            if (Mage::helper('catalogsearch')->isMinQueryLength()) {
                $query->setId(0)
                    ->setIsActive(1)
                    ->setIsProcessed(1);
            } else {
                if ($query->getId()) {
                    $query->setPopularity($query->getPopularity() + 1);
                } else {
                    $query->setPopularity(1);
                }

                if ($query->getRedirect()) {
                    $query->save();
                    $this->getResponse()->setRedirect($query->getRedirect());
                    return;
                }
                $query->prepare();
            }

            Mage::helper('catalogsearch')->checkNotes();

            $this->loadLayout();
            $this->_initLayoutMessages('catalog/session');
            $this->_initLayoutMessages('checkout/session');
            $this->renderLayout();

            if (!Mage::helper('catalogsearch')->isMinQueryLength()) {
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
