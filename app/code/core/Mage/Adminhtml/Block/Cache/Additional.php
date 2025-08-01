<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
}
