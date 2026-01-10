<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
            return '<span style="display:inline-flex;align-items:center;gap:6px;">'
                . '<span style="width:8px;height:8px;border-radius:50%;background:#22c55e;"></span>'
                . '<span style="color:#166534;font-weight:500;">Enabled</span>'
                . '</span>';
        }

        return '<span style="display:inline-flex;align-items:center;gap:6px;">'
            . '<span style="width:8px;height:8px;border-radius:50%;background:#9ca3af;"></span>'
            . '<span style="color:#6b7280;">Disabled</span>'
            . '</span>';
    }
}
