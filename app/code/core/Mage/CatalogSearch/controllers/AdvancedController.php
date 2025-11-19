<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog Search Controller
 *
 * @package    Mage_CatalogSearch
 * @module     Catalog
 */
class Mage_CatalogSearch_AdvancedController extends Mage_Core_Controller_Front_Action
{
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
