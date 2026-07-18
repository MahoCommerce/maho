<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Cache_Additional extends Mage_Adminhtml_Block_Template
{
    public function getCleanImagesUrl(): string
    {
        return $this->getUrl('*/*/cleanImages');
    }

    public function getCleanSwatchesUrl(): string
    {
        return $this->getUrl('*/*/cleanSwatches');
    }

    public function getCleanMinifiedFilesUrl(): string
    {
        return $this->getUrl('*/*/cleanMinifiedFiles');
    }

    public function getRecompileAttributesUrl(): string
    {
        return $this->getUrl('*/*/recompileAttributes');
    }
}
