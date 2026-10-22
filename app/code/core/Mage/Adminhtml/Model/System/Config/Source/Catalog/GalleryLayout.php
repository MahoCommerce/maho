<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Catalog_GalleryLayout
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Mage_Catalog_Block_Product_View_Media::GALLERY_LAYOUT_BELOW, 'label' => Mage::helper('adminhtml')->__('Thumbnails Below the Image')],
            ['value' => Mage_Catalog_Block_Product_View_Media::GALLERY_LAYOUT_LEFT, 'label' => Mage::helper('adminhtml')->__('Thumbnails Left of the Image')],
            ['value' => Mage_Catalog_Block_Product_View_Media::GALLERY_LAYOUT_RIGHT, 'label' => Mage::helper('adminhtml')->__('Thumbnails Right of the Image')],
            ['value' => Mage_Catalog_Block_Product_View_Media::GALLERY_LAYOUT_GRID, 'label' => Mage::helper('adminhtml')->__('Grid of All Images')],
        ];
    }
}
