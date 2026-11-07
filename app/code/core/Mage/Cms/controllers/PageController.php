<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_PageController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/cms/page/view/{page_id}', name: 'cms.page.view', requirements: ['page_id' => '\d+'])]
    public function viewAction()
    {
        $pageId = $this->getRequest()
            ->getParam('page_id', $this->getRequest()->getParam('id', false));
        $page = Mage::getSingleton('cms/page')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($pageId);
        $helper = Mage::helper('cms/page');
        if ($page->getId() && $helper->isHomePage($page)) {
            $this->getResponse()->setRedirect(Mage::getUrl('', ['_query' => $this->getRequest()->getQuery()]), 301);
            return;
        }
        if ($helper->isNoRoutePage($page) || !$helper->renderPage($this, $page->getId())) {
            return $this->_forward('noRoute');
        }
    }
}
