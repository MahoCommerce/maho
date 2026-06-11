<?php

/**
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rss
 */

class Mage_Rss_Helper_Catalog extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Rss';

    /**
     * @return string
     */
    public function getTagFeedUrl()
    {
        $url = '';
        if (Mage::getStoreConfig('rss/catalog/tag') && $this->_getRequest()->getParam('tagId')) {
            $tagModel = Mage::getModel('tag/tag')->load($this->_getRequest()->getParam('tagId'));
            if ($tagModel->getId()) {
                return Mage::getUrl('rss/catalog/tag', ['tagName' => urlencode($tagModel->getName())]);
            }
        }
        return $url;
    }
}
