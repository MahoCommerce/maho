<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Enabled status renderer - visual indicator with color for meaning
 */
class Maho_FeedManager_Block_Adminhtml_Feed_Grid_Renderer_Enabled extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $value = (bool) $row->getData($this->getColumn()->getIndex());

        if ($value) {
            return '<span class="grid-severity-notice"><span>' . $this->__('Enabled') . '</span></span>';
        }

        return '<span class="grid-severity-minor"><span>' . $this->__('Disabled') . '</span></span>';
    }
}
