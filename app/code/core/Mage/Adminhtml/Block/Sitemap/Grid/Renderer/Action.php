<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sitemap_Grid_Renderer_Action extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $this->getColumn()->setActions([[
            'url'     => $this->getUrl('*/sitemap/generate', ['sitemap_id' => $row->getSitemapId()]),
            'caption' => Mage::helper('sitemap')->__('Generate'),
        ]]);
        return parent::render($row);
    }
}
