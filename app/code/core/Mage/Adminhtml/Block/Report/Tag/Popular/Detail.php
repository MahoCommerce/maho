<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Tag_Popular_Detail extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'report_tag_popular_detail';
        $tag = Mage::getModel('tag/tag')->load($this->getRequest()->getParam('id'));
        $this->_headerText = Mage::helper('reports')->__('Tag "%s" details', $this->escapeHtml($tag->getName()));
        parent::__construct();
        $this->_removeButton('add');
        $this->setBackUrl($this->getUrl('*/report_tag/popular/'));
        $this->_addBackButton();
    }
}
