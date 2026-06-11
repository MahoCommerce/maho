<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Renders CMS Home page
     *
     * @param string $coreRoute
     */
    #[Maho\Config\Route('/', name: 'cms.home', methods: ['GET'])]
    public function indexAction($coreRoute = null)
    {
        $pageId = Mage::getStoreConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE);
        if (!Mage::helper('cms/page')->renderPage($this, $pageId)) {
            return $this->_forward('defaultIndex');
        }
    }

    /**
     * Default index action (with 404 Not Found headers)
     * Used if default page don't configure or available
     */
    public function defaultIndexAction(): void
    {
        $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found');
        $this->getResponse()->setHeader('Status', '404 File not found');

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Render CMS 404 Not found page
     *
     * @param string $coreRoute
     */
    #[\Override]
    #[Maho\Config\Route('/cms', name: 'cms.index')]
    #[Maho\Config\Route('/cms/index', name: 'cms.index_index')]
    #[Maho\Config\Route('/cms/index/noroute', name: 'cms.noroute')]
    public function norouteAction($coreRoute = null): void
    {
        $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found');
        $this->getResponse()->setHeader('Status', '404 File not found');

        $pageId = Mage::getStoreConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE);
        if (!Mage::helper('cms/page')->renderPage($this, $pageId)) {
            $this->_forward('defaultNoRoute');
        }
    }

    /**
     * Default no route page action
     * Used if no route page don't configure or available
     */
    public function defaultNoRouteAction(): void
    {
        $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found');
        $this->getResponse()->setHeader('Status', '404 File not found');

        $this->loadLayout();
        $this->renderLayout();
    }
}
