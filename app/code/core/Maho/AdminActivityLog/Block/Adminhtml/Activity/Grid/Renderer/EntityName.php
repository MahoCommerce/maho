<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Block_Adminhtml_Activity_Grid_Renderer_EntityName extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $entityName = $row->getData($this->getColumn()->getIndex());

        if (empty($entityName)) {
            return '';
        }

        // For grouped entities, show count if more than one
        $activityCount = $row->getData('activity_count');
        if ($activityCount > 1) {
            $entityName .= sprintf("\n(%d activities)", $activityCount);
        }

        // Convert newlines to <br> tags for HTML display
        return nl2br($this->escapeHtml($entityName));
    }

    #[\Override]
    public function renderExport(\Maho\DataObject $row)
    {
        $entityName = $row->getData($this->getColumn()->getIndex());

        if (empty($entityName)) {
            return '';
        }

        // Return plain text for CSV export - replace line breaks with spaces
        return str_replace(["\n", "\r"], ' ', $entityName);
    }
}
