<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Controller_Rss_Abstract extends Mage_Adminhtml_Controller_Action
{
    protected function isFeedEnable(string $code): bool
    {
        return Mage::helper('rss')->isRssEnabled()
            && Mage::getStoreConfig('rss/' . $code);
    }

    /**
     * Do check feed enabled and prepare response
     */
    protected function checkFeedEnable(string $code): bool
    {
        if ($this->isFeedEnable($code)) {
            $this->getResponse()->setHeader('Content-type', 'text/xml; charset=UTF-8');
            return true;
        }
        $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found');
        $this->getResponse()->setHeader('Status', '404 File not found');
        $this->_forward('noRoute');
        return false;
    }
}
