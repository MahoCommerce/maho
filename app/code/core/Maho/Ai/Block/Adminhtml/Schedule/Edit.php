<?php

/**
 * Edit page of a scheduled assistant task.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Schedule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'ai';
        $this->_controller = 'adminhtml_schedule';

        $this->_updateButton('save', 'label', Mage::helper('ai')->__('Save Scheduled Task'));
        $this->_updateButton('delete', 'label', Mage::helper('ai')->__('Delete Scheduled Task'));
    }

    #[\Override]
    public function getHeaderText()
    {
        $schedule = Mage::registry('ai_task_schedule');
        if ($schedule instanceof Maho_Ai_Model_Task_Schedule && $schedule->getId()) {
            return Mage::helper('ai')->__("Edit Scheduled Task '%s'", $this->escapeHtml((string) $schedule->getTitle()));
        }

        return Mage::helper('ai')->__('New Scheduled Task');
    }
}
