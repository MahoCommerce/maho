<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

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
