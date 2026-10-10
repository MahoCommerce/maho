<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Schedule_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('ai_schedule_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('ai')->__('Scheduled Task'));
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->addTab('main', 'ai/adminhtml_schedule_edit_tab_main');
        $this->addTab('runs', 'ai/adminhtml_schedule_edit_tab_runs');

        return parent::_beforeToHtml();
    }
}
