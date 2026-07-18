<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_Model_Wysiwyg_Images_Storage_Collection extends \Maho\Data\Collection\Filesystem
{
    #[\Override]
    protected function _generateRow($filename)
    {
        $row = parent::_generateRow($filename);
        $row['filename'] = preg_replace('~[/\\\]+~', DIRECTORY_SEPARATOR, $row['filename']);
        return $row;
    }
}
