<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Attribute_Frontend_File extends Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
{
    /**
     * Get file URL
     *
     * @param \Maho\DataObject $object
     * @return string|false
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getUrl($object)
    {
        $fileName = $object->getData($this->getAttribute()->getAttributeCode());

        if ($fileName) {
            return Mage::app()->getStore($object->getStore())->getBaseUrl('media') . 'catalog/files/' . ltrim($fileName, '/');
        }

        return false;
    }

    /**
     * Get file name without path
     *
     * @param \Maho\DataObject $object
     * @return string|false
     */
    public function getFileName($object)
    {
        $fileName = $object->getData($this->getAttribute()->getAttributeCode());

        if ($fileName) {
            return basename($fileName);
        }

        return false;
    }

    /**
     * Get value as HTML with download link
     *
     * @param \Maho\DataObject $object
     * @return string
     */
    public function getValueAsHtml($object)
    {
        $url = $this->getUrl($object);
        $fileName = $this->getFileName($object);

        if ($url && $fileName) {
            $icon = Mage::helper('core')->getIconSvg('file-download');

            return '<a href="' . Mage::helper('core')->escapeHtml($url) . '" target="_blank" class="product-file-link" style="display: inline-flex; align-items: center; gap: 0.25rem;">'
                . $icon
                . Mage::helper('core')->escapeHtml($fileName)
                . '</a>';
        }

        return '';
    }

    /**
     * Get attribute value for display (calls getValueAsHtml)
     *
     * @return string
     */
    #[\Override]
    public function getValue(\Maho\DataObject $object)
    {
        return $this->getValueAsHtml($object);
    }
}
