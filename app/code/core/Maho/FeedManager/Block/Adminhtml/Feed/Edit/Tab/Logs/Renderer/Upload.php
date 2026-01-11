<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs_Renderer_Upload
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $status = $row->getData('upload_status');
        $message = $row->getData('upload_message');
        $uploadedAt = $row->getData('uploaded_at');

        if (!$status) {
            return '<span style="color:#999">-</span>';
        }

        $color = match ($status) {
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_SUCCESS => '#2e7d32',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED => '#c62828',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_PENDING => '#f57c00',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED => '#757575',
            default => '#333',
        };

        $icon = match ($status) {
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_SUCCESS => '✓',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED => '✗',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_PENDING => '⏳',
            Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED => '⊘',
            default => '',
        };

        $label = Maho_FeedManager_Model_Log::getUploadStatusOptions()[$status] ?? ucfirst($status);

        $html = "<span style=\"color:{$color}\">{$icon} {$label}</span>";

        // Add tooltip with message if available
        if ($message) {
            $escapedMessage = $this->escapeHtml($message);
            $html = "<span title=\"{$escapedMessage}\" style=\"color:{$color}; cursor:help\">{$icon} {$label}</span>";
        }

        return $html;
    }
}
