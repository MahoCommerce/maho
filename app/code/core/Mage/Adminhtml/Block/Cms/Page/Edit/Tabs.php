<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Cms_Page_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('page_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('cms')->__('Page Information'));
    }

    /**
     * Add one tab for each group of the attribute set of the pages that holds an attribute. The
     * Page Information tab shows the attributes of the first group, so that group has no tab.
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        $groups = Mage::getResourceSingleton('cms/page')->getCustomAttributeGroups();
        array_shift($groups);
        foreach ($groups as ['group' => $group, 'attributes' => $attributes]) {
            if ($attributes) {
                $this->addTab('attribute_group_' . $group->getId(), $this->getLayout()->createBlock('adminhtml/cms_page_edit_tab_attributes')
                    ->setData('group', $group)
                    ->setData('attributes', $attributes));
            }
        }
        return parent::_beforeToHtml();
    }
}
