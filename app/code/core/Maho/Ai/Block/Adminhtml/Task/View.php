<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Block_Adminhtml_Task_View extends Mage_Adminhtml_Block_Widget
{
    public function getTask(): ?Maho_Ai_Model_Task
    {
        return Mage::registry('current_ai_task');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/tasks');
    }
}
