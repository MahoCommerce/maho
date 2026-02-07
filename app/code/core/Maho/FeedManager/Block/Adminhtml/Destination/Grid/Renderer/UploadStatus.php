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
            return '<span class="grid-severity-minor"><span>—</span></span>';
        }

        if ($value === 'success') {
            return '<span class="grid-severity-notice"><span>' . $this->__('Success') . '</span></span>';
        }

        if ($value === 'failed') {
            return '<span class="grid-severity-critical"><span>' . $this->__('Failed') . '</span></span>';
        }

        return '<span class="grid-severity-minor"><span>' . $this->escapeHtml(ucfirst($value)) . '</span></span>';
    }
}
