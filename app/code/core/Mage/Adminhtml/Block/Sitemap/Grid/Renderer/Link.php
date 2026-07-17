<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sitemap_Grid_Renderer_Link extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Prepare link to display in grid
     *
     * @return string
     * @throws Mage_Core_Model_Store_Exception|Mage_Core_Exception
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $fileName = preg_replace('/^\//', '', $row->getSitemapPath() . $row->getSitemapFilename());
        $url = $this->escapeHtml(
            Mage::app()->getStore($row->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . $fileName,
        );

        // Sitemap files are stored in the public directory
        if (file_exists(Mage::getBaseDir('public') . DS . $fileName)) {
            return sprintf('<a href="%1$s" target="_blank">%1$s</a>', $url);
        }
        return $url;
    }
}
