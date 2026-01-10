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
 * Upload status renderer - color for meaning (success/error)
 */
class Maho_FeedManager_Block_Adminhtml_Destination_Grid_Renderer_UploadStatus extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $value = $row->getData($this->getColumn()->getIndex());

        if (!$value) {
            return '<span style="color:#9ca3af;font-size:12px;">—</span>';
        }

        if ($value === 'success') {
            return '<span style="display:inline-flex;align-items:center;gap:6px;">'
                . '<span style="width:8px;height:8px;border-radius:50%;background:#22c55e;"></span>'
                . '<span style="color:#166534;font-size:12px;font-weight:500;">Success</span>'
                . '</span>';
        }

        if ($value === 'failed') {
            return '<span style="display:inline-flex;align-items:center;gap:6px;">'
                . '<span style="width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>'
                . '<span style="color:#dc2626;font-size:12px;font-weight:500;">Failed</span>'
                . '</span>';
        }

        return '<span style="color:#6b7280;font-size:12px;">' . $this->escapeHtml(ucfirst($value)) . '</span>';
    }
}
