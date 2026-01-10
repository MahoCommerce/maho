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
 * Destination type renderer - icon + label badge
 */
class Maho_FeedManager_Block_Adminhtml_Destination_Grid_Renderer_Type extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $value = $row->getData($this->getColumn()->getIndex());

        if (!$value) {
            return '';
        }

        // Type-specific styling (muted colors, functional)
        [$icon, $styles] = match ($value) {
            'sftp' => ['⇅', 'background:#f0f9ff;color:#0369a1;border-color:#bae6fd;'],
            'ftp' => ['⇅', 'background:#fefce8;color:#a16207;border-color:#fef08a;'],
            'google_api' => ['G', 'background:#f0fdf4;color:#166534;border-color:#bbf7d0;'],
            'facebook_api' => ['f', 'background:#eff6ff;color:#1e40af;border-color:#bfdbfe;'],
            default => ['•', 'background:#f5f5f5;color:#525252;border-color:#e5e5e5;'],
        };

        $label = Maho_FeedManager_Model_Destination::getTypeOptions()[$value] ?? ucfirst($value);

        return '<span style="display:inline-flex;align-items:center;gap:6px;">'
            . '<span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:4px;font-size:11px;font-weight:600;border:1px solid;' . $styles . '">' . $icon . '</span>'
            . '<span style="font-size:12px;color:#374151;">' . $this->escapeHtml($label) . '</span>'
            . '</span>';
    }
}
