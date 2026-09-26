<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use League\Flysystem\FilesystemException;

class Maho_MediaCleaner_Block_Adminhtml_Mediacleaner_Grid_Renderer_Image extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $mount = Mage::getStorage('media');
        $file = Mage::helper('mediacleaner')->getImageMountPath($mount, (string) $row->getType(), (string) $row->getPath());
        if ($file === null) {
            return '';
        }

        try {
            $url = $mount->publicUrl($file);
        } catch (FilesystemException) {
            return '';
        }

        $maxWidth = (int) Mage::getStoreConfig('admin/mediacleaner/thumbnail_max_width');
        $src = $this->escapeHtml($url);
        $return = "<img src=\"{$src}\" style=\"max-width:{$maxWidth}px\" />";
        if (Mage::getStoreConfig('admin/mediacleaner/enable_image_click')) {
            $return = "<a href=\"{$src}\" target=\"_blank\">$return</a>";
        }

        return $return;
    }
}
