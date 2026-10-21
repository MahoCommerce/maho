<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Catalog_GalleryPhoneLayout
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Mage_Catalog_Block_Product_View_Media::GALLERY_PHONE_LAYOUT_CAROUSEL, 'label' => Mage::helper('adminhtml')->__('Swipe Carousel')],
            ['value' => Mage_Catalog_Block_Product_View_Media::GALLERY_PHONE_LAYOUT_BELOW, 'label' => Mage::helper('adminhtml')->__('Thumbnails Below the Image')],
        ];
    }
}
