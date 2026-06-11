<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Grid_Column_Renderer_Product extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $id   = $row->getId();

        return sprintf(
            '<a href="%s">%s</a>',
            $this->getUrl('*/catalog_product_review/', ['productId' => $id]),
            Mage::helper('adminhtml')->__('Show Reviews'),
        );
    }
}
