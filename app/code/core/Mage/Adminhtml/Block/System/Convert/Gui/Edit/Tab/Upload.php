<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_System_Convert_Gui_Edit_Tab_Upload extends Mage_Adminhtml_Block_Widget_Form
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('system/convert/profile/upload.phtml');
    }

    public function getPostMaxSize(): string|false
    {
        return ini_get('post_max_size');
    }

    public function getUploadMaxSize(): string|false
    {
        return ini_get('upload_max_filesize');
    }

    public function getDataMaxSize(): string|false
    {
        return min($this->getPostMaxSize(), $this->getUploadMaxSize());
    }
}
