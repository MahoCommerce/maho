<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sitemap_Grid_Renderer_Time extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Prepare link to display in grid
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        return Mage::app()->getLocale()->utcToStore(null, $row->getSitemapTime())->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
    }
}
