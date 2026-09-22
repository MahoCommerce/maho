<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Uploader
 */

/**
 * File Input Config Parameters
 *
 * @package    Mage_Uploader
 *
 *      Array of element browse buttons ids
 *      Pass in true to allow directories to be selected (Google Chrome only)
 *      To prevent multiple file uploads set this to true.
 *      Also look at config parameter singleFile (Mage_Uploader_Model_Config_Uploader setSingleFile())
 *      Pass object of keys and values to set custom attributes on input fields.
 *      @see http://www.w3.org/TR/html-markup/input.file.html#input.file-attributes
 */
class Mage_Uploader_Model_Config_Browsebutton extends Mage_Uploader_Model_Config_Abstract
{
    /**
     * Set params for browse button
     */
    #[\Override]
    protected function _construct()
    {
        $this->setIsDirectory(false);
    }

    /**
     * Get MIME types from files extensions
     *
     * @param string|array $exts
     * @return string
     */
    public function getMimeTypesByExtensions($exts)
    {
        $mimes = array_unique($this->_getHelper()->getMimeTypeFromExtensionList($exts));

        // Not include general file type
        unset($mimes['application/octet-stream']);

        return implode(',', $mimes);
    }

    public function setAttributes(?array $value): static
    {
        return $this->setData('attributes', $value);
    }

    public function setDomNodes(?array $value): static
    {
        return $this->setData('dom_nodes', $value);
    }

    public function setIsDirectory(?bool $value): static
    {
        return $this->setData('is_directory', $value);
    }

    public function setSingleFile(?bool $value): static
    {
        return $this->setData('single_file', $value);
    }

}
