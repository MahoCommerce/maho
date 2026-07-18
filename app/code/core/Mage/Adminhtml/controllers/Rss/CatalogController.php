<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Rss_CatalogController extends Mage_Adminhtml_Controller_Rss_Abstract
{
    #[\Override]
    protected function _isAllowed()
    {
        $path = '';
        $action = strtolower($this->getRequest()->getActionName());
        if ($action == 'review') {
            $path = 'catalog/reviews_ratings';
        } elseif ($action == 'notifystock') {
            $path = 'catalog/products';
        }
        return Mage::getSingleton('admin/session')->isAllowed($path);
    }

    #[Maho\Config\Route('/admin/rss_catalog/notifystock')]
    public function notifystockAction(): void
    {
        if ($this->checkFeedEnable('admin_catalog/notifystock')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }

    #[Maho\Config\Route('/admin/rss_catalog/review')]
    public function reviewAction(): void
    {
        if ($this->checkFeedEnable('admin_catalog/review')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }
}
