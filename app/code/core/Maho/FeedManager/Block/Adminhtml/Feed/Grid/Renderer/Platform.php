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
 * Platform renderer - subtle badge with platform-specific styling
 */
class Maho_FeedManager_Block_Adminhtml_Feed_Grid_Renderer_Platform extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $value = $row->getData($this->getColumn()->getIndex());

        if (!$value) {
            return '';
        }

        // Platform-specific colors (muted, not decorative - color for meaning)
        $styles = match ($value) {
            'google' => 'background:#f0fdf4;color:#166534;border-color:#bbf7d0;',
            'facebook' => 'background:#eff6ff;color:#1e40af;border-color:#bfdbfe;',
            'custom' => 'background:#f5f5f5;color:#525252;border-color:#e5e5e5;',
            default => 'background:#f5f5f5;color:#525252;border-color:#e5e5e5;',
        };

        $label = Maho_FeedManager_Model_Platform::getPlatformOptions()[$value] ?? ucfirst($value);

        return '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500;border:1px solid;' . $styles . '">'
            . $this->escapeHtml($label)
            . '</span>';
    }
}
