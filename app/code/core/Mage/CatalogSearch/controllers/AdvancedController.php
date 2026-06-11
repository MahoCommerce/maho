<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

/**
 * Catalog Search Controller
 *
 * @package    Mage_CatalogSearch
 * @module     Catalog
 */

class Mage_CatalogSearch_AdvancedController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/catalogsearch/advanced', name: 'catalogsearch.advanced.index', methods: ['GET'])]
    public function indexAction(): void
    {
        if (!Mage::helper('catalogsearch')->isAdvancedSearchEnabled()) {
            $this->_forward('noroute');
            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('catalogsearch/session');
        $this->renderLayout();
    }

    #[Maho\Config\Route('/catalogsearch/advanced/result', name: 'catalogsearch.advanced.result', methods: ['GET'])]
    public function resultAction(): void
    {
        if (!Mage::helper('catalogsearch')->isAdvancedSearchEnabled()) {
            $this->_forward('noroute');
            return;
        }

        $this->loadLayout();
        try {
            Mage::getSingleton('catalogsearch/advanced')->addFilters($this->getRequest()->getQuery());
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('catalogsearch/session')->addError($e->getMessage());
            $this->_redirectError(
                Mage::getModel('core/url')
                    ->setQueryParams($this->getRequest()->getQuery())
                    ->getUrl('*/*/'),
            );
            return;
        }
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }
}
