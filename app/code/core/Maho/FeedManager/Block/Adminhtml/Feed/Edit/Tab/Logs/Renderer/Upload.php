<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs_Renderer_Upload extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $status = $row->getData('upload_status');
        $message = $row->getData('upload_message');

        if (!$status) {
            return '<span class="grid-severity-minor"><span>-</span></span>';
        }

        $severityClass = match ($status) {
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_SUCCESS => 'grid-severity-notice',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED => 'grid-severity-critical',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_PENDING => 'grid-severity-major',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED => 'grid-severity-minor',
            default => 'grid-severity-minor',
        };

        $label = Maho_FeedManager_Model_Log::getUploadStatusOptions()[$status] ?? ucfirst($status);
        $escapedLabel = $this->escapeHtml($label);

        if ($message) {
            $escapedMessage = $this->escapeHtml($message);
            return "<span class=\"{$severityClass}\" title=\"{$escapedMessage}\"><span>{$escapedLabel}</span></span>";
        }

        return "<span class=\"{$severityClass}\"><span>{$escapedLabel}</span></span>";
    }
}
