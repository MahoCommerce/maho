<?php

/**
 * Reads the upload size limit of the server.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Helper_Uploader extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_Adminhtml';

    public function getPostMaxSize(): string
    {
        return (string) ini_get('post_max_size');
    }

    public function getUploadMaxSize(): string
    {
        return (string) ini_get('upload_max_filesize');
    }

    /**
     * Read the lower of the two limits, in the form that php.ini writes it.
     */
    public function getDataMaxSize(): string
    {
        $postMaxSize = $this->getPostMaxSize();
        $uploadMaxSize = $this->getUploadMaxSize();

        return ini_parse_quantity($postMaxSize) <= ini_parse_quantity($uploadMaxSize)
            ? $postMaxSize
            : $uploadMaxSize;
    }

    public function getDataMaxSizeInBytes(): int
    {
        return ini_parse_quantity($this->getDataMaxSize());
    }
}
