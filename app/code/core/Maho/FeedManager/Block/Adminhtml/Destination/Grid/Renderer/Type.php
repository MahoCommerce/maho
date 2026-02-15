<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
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

        $label = Maho_FeedManager_Model_Destination::getTypeOptions()[$value] ?? ucfirst($value);

        return $this->escapeHtml($label);
    }
}
