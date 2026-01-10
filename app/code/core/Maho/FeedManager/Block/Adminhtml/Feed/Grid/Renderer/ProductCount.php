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
 * Product count renderer - monospace for data, tabular nums for alignment
 */
class Maho_FeedManager_Block_Adminhtml_Feed_Grid_Renderer_ProductCount extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $value = $row->getData($this->getColumn()->getIndex());

        if ($value === null || $value === '') {
            return '<span style="color:#9ca3af;font-family:ui-monospace,monospace;font-size:12px;">—</span>';
        }

        $formatted = number_format((int) $value);

        return '<span style="font-family:ui-monospace,monospace;font-size:12px;font-variant-numeric:tabular-nums;color:#374151;">'
            . $formatted
            . '</span>';
    }
}
