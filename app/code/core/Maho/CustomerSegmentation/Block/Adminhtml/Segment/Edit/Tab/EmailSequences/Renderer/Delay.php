<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_EmailSequences_Renderer_Delay extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row): string
    {
        $minutes = (int) $row->getData($this->getColumn()->getIndex());

        if ($minutes === 0) {
            return Mage::helper('customersegmentation')->__('Immediate');
        }
        if ($minutes < 60) {
            return $minutes . ' ' . Mage::helper('customersegmentation')->__('minutes');
        }

        if ($minutes < 1440) {
            $hours = round($minutes / 60, 1);
            return $hours . ' ' . Mage::helper('customersegmentation')->__('hours');
        }
        $days = round($minutes / 1440, 1);
        return $days . ' ' . Mage::helper('customersegmentation')->__('days');
    }
}
