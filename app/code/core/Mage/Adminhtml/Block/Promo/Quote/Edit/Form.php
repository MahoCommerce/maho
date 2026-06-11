<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Promo_Quote_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('promo_quote_form');
        $this->setTitle(Mage::helper('salesrule')->__('Rule Information'));
    }

    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form(['id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post']);
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
