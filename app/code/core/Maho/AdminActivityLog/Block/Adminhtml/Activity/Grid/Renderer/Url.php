<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Block_Adminhtml_Activity_Grid_Renderer_Url extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $value = $row->getData($this->getColumn()->getIndex());
        if (!$value) {
            return '';
        }

        $parsedUrl = parse_url($value);
        $path = $parsedUrl['path'] ?? '';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';

        $shortUrl = $path . $query;
        if (strlen($shortUrl) > 50) {
            $shortUrl = substr($shortUrl, 0, 47) . '...';
        }

        return '<span title="' . $this->escapeHtml($value) . '">' . $this->escapeHtml($shortUrl) . '</span>';
    }

    #[\Override]
    public function renderExport(\Maho\DataObject $row)
    {
        $value = $row->getData($this->getColumn()->getIndex());
        if (!$value) {
            return '';
        }

        // Return the full URL for CSV export - no truncation, no HTML
        return $value;
    }
}
