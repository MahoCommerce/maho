<?php

/**
 * Settings of the file input that the browse button opens.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_Uploader_Config_Browsebutton extends Mage_Adminhtml_Model_Uploader_Config_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->setIsDirectory(false);
    }

    /**
     * Build the value of an accept attribute from a list of file extensions.
     *
     * @param list<string>|string $exts
     */
    public function getMimeTypesByExtensions(array|string $exts): string
    {
        return implode(',', Mage::helper('core')->getMimeTypes($exts));
    }

    /**
     * Set an attribute of the file input. See https://html.spec.whatwg.org/#file-upload-state
     */
    public function setAttributes(?array $value): static
    {
        return $this->setData('attributes', $value);
    }

    /**
     * Set the ids of the elements that open the file input.
     */
    public function setDomNodes(?array $value): static
    {
        return $this->setData('dom_nodes', $value);
    }

    /**
     * Let the person select a directory. Google Chrome only.
     */
    public function setIsDirectory(?bool $value = true): static
    {
        return $this->setData('is_directory', $value);
    }

    /**
     * Let the person select one file only. Also set it on the uploader config.
     */
    public function setSingleFile(?bool $value = true): static
    {
        return $this->setData('single_file', $value);
    }
}
