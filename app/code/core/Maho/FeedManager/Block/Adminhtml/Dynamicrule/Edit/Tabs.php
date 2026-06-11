<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Block_Adminhtml_Dynamicrule_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('dynamicrule_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle($this->__('Dynamic Rule'));
    }

    #[\Override]
    protected function _beforeToHtml(): self
    {
        $this->addTab('general', [
            'label' => $this->__('General'),
            'title' => $this->__('General'),
            'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_dynamicrule_edit_tab_general')->toHtml(),
        ]);

        $this->addTab('cases', [
            'label' => $this->__('Output Rules'),
            'title' => $this->__('Output Rules'),
            'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_dynamicrule_edit_tab_cases')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
