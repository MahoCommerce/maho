<?php

/**
 * Maho
 *
 * @package    Mage_Page
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Page_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Page';

    /**
     * Get favicon HTML link tag
     */
    public function getFaviconHtml(string $defaultSkinUrl = 'images/favicon.svg'): string
    {
        $faviconFile = Mage::getStoreConfig('design/head/shortcut_icon');

        if ($faviconFile) {
            // Use uploaded favicon from media
            $faviconUrl = Mage::getBaseUrl('media') . 'favicon/' . $faviconFile;
            $faviconExt = strtolower(pathinfo($faviconFile, PATHINFO_EXTENSION));

            $mimeTypes = [
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'ico' => 'image/x-icon',
                'gif' => 'image/gif',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'avif' => 'image/avif',
            ];

            $mimeType = $mimeTypes[$faviconExt] ?? 'image/x-icon';
            $sizesAttr = $faviconExt === 'svg' ? ' sizes="any"' : '';

            return sprintf(
                '<link rel="icon" type="%s" href="%s"%s>',
                $mimeType,
                $faviconUrl,
                $sizesAttr,
            );
        }

        // Fall back to skin default
        $skinUrl = Mage::getDesign()->getSkinUrl($defaultSkinUrl);
        $ext = strtolower(pathinfo($defaultSkinUrl, PATHINFO_EXTENSION));
        $mimeType = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
        $sizesAttr = $ext === 'svg' ? ' sizes="any"' : '';

        return sprintf(
            '<link rel="icon" type="%s" href="%s"%s>',
            $mimeType,
            $skinUrl,
            $sizesAttr,
        );
    }
}
