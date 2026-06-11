<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Tag_Pending extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('tag/index.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild('tagsGrid', $this->getLayout()->createBlock('adminhtml/tag_grid_pending'));
        return parent::_prepareLayout();
    }

    public function getCreateButtonHtml()
    {
        return '';
    }

    public function getGridHtml()
    {
        return $this->getChildHtml('tagsGrid');
    }

    public function getHeaderHtml()
    {
        return Mage::helper('tag')->__('Pending Tags');
    }
}
