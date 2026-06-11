<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs_Renderer_Filesize extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $bytes = (int) $row->getData($this->getColumn()->getIndex());
        return Mage::helper('feedmanager')->formatFileSize($bytes);
    }
}
