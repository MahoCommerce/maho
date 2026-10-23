<?php

/**
 * Adds the Tailwind browser build to the preview of the content editor, so a class that only
 * the draft uses has its style before the content build runs.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Block_Preview_Tailwind extends Mage_Core_Block_Abstract
{
    #[\Override]
    protected function _toHtml(): string
    {
        $stylesFile = (string) Mage::getDesign()->getFilename(Mage_Core_Model_Design_Content_Bundle::STYLES_FILE, ['_type' => 'skin']);
        $css = Mage::getModel('core/design_content_previewCss')->getCss($stylesFile);
        $script = Mage::getBaseDir('public') . '/js/' . Mage_Core_Model_Design_Content_PreviewCss::SCRIPT;
        if ($css === null || !is_file($script)) {
            return '';
        }

        $url = Mage::getBaseUrl('js') . Mage_Core_Model_Design_Content_PreviewCss::SCRIPT . '?v=' . filemtime($script);
        // The source comes from the theme files, and a "<" could close the element
        return '<script src="' . $this->escapeHtml($url) . '"></script>' . PHP_EOL
            . '<style type="text/tailwindcss">' . PHP_EOL . str_replace('<', '', $css) . '</style>' . PHP_EOL;
    }
}
