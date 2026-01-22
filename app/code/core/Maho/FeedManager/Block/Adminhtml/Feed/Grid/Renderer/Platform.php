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

        $label = Maho_FeedManager_Model_Platform::getPlatformOptions()[$value] ?? ucfirst($value);

        return $this->escapeHtml($label);
    }
}
