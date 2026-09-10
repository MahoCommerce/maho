<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_NODE_PAGE_TEMPLATE_FILTER     = 'global/cms/page/tempate_filter';
    public const XML_NODE_BLOCK_TEMPLATE_FILTER    = 'global/cms/block/tempate_filter';
    public const XML_NODE_ALLOWED_STREAM_WRAPPERS  = 'global/cms/allowed_stream_wrappers';

    protected $_moduleName = 'Mage_Cms';

    /**
     * Retrieve Template processor for Page Content
     *
     * @return Mage_Core_Model_Abstract|\Maho\Filter\Template
     */
    public function getPageTemplateProcessor()
    {
        $model = (string) Mage::getConfig()->getNode(self::XML_NODE_PAGE_TEMPLATE_FILTER);
        return Mage::getModel($model);
    }

    /**
     * The url that reports what a save removes from a content field.
     *
     * A field must carry this url to get the warning. Only add it to a field that the save
     * path sanitizes. Use the scope 'catalog' for a product or a category attribute.
     */
    public function getSanitizePreviewUrl(string $scope = 'cms'): string
    {
        return Mage::getSingleton('adminhtml/url')->getUrl('*/cms_wysiwyg/sanitizePreview', ['scope' => $scope]);
    }

    /** Attach the warning to a plain textarea, which runs no editor setup of its own. */
    public function getSanitizePreviewHtml(string $htmlId, string $scope = 'cms'): string
    {
        $url = $this->getSanitizePreviewUrl($scope);
        return <<<HTML
            <script>
                mahoOnReady(() => {
                    new mahoSanitizePreview('$htmlId', '$url');
                });
            </script>
            HTML;
    }

    /**
     * Retrieve Template processor for Block Content
     *
     * @return Mage_Core_Model_Abstract|\Maho\Filter\Template
     */
    public function getBlockTemplateProcessor()
    {
        $model = (string) Mage::getConfig()->getNode(self::XML_NODE_BLOCK_TEMPLATE_FILTER);
        return Mage::getModel($model);
    }

    /**
     * Return list with allowed stream wrappers
     *
     * @return array
     */
    public function getAllowedStreamWrappers()
    {
        $allowedStreamWrappers = Mage::getConfig()->getNode(self::XML_NODE_ALLOWED_STREAM_WRAPPERS);
        if ($allowedStreamWrappers instanceof Mage_Core_Model_Config_Element) {
            $allowedStreamWrappers = $allowedStreamWrappers->asArray();
        }

        return is_array($allowedStreamWrappers) ? $allowedStreamWrappers : [];
    }
}
