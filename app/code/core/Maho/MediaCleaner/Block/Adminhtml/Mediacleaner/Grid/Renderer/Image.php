<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

class Maho_MediaCleaner_Block_Adminhtml_Mediacleaner_Grid_Renderer_Image extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $url = Mage::getBaseUrl('media');
        $maxWidth = (int) Mage::getStoreConfig('admin/mediacleaner/thumbnail_max_width');

        match ($row->getType()) {
            'category'      => $url .= 'catalog/category/',
            'product'       => $url .= 'catalog/product/',
            'product_cache' => $url .= 'catalog/product/cache/',
            'wysiwyg'       => $url .= 'wysiwyg/',
            default         => null,
        };

        $src = $this->escapeHtml($url . $row->getPath());
        $return = "<img src=\"{$src}\" style=\"max-width:{$maxWidth}px\" />";
        if (Mage::getStoreConfig('admin/mediacleaner/enable_image_click')) {
            $return = "<a href=\"{$src}\" target=\"_blank\">$return</a>";
        }

        return $return;
    }
}
