<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Newsletter_Template extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('newsletter/template/list.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild('grid', $this->getLayout()->createBlock('adminhtml/newsletter_template_grid', 'newsletter.template.grid'));
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getCreateUrl()
    {
        return $this->getUrl('*/*/new');
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('newsletter')->__('Newsletter Templates');
    }
}
