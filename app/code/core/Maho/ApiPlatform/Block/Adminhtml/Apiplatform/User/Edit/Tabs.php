<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_User_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('apiplatform_user_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle($this->__('API User Information'));
    }

    #[\Override]
    protected function _beforeToHtml(): static
    {
        $this->addTab('main', [
            'label'   => $this->__('Account Information'),
            'title'   => $this->__('Account Information'),
            'content' => $this->getLayout()->createBlock('apiplatform/adminhtml_apiplatform_user_edit_tab_main')->toHtml(),
            'active'  => true,
        ]);

        $this->addTab('role', [
            'label'   => $this->__('Role'),
            'title'   => $this->__('Role'),
            'content' => $this->getLayout()->createBlock('apiplatform/adminhtml_apiplatform_user_edit_tab_role')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
