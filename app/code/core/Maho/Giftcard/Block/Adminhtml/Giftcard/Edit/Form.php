<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Empty `<form id="edit_form">` wrapper auto-resolved by the Edit
 * Form_Container as its `form` child. The actual fields live in the
 * Edit/Tab/Form.php block (registered as the General tab in the
 * adminhtml_giftcard_edit layout handle) and are injected into this
 * wrapper by the Mage tabs JavaScript via the `destElementId` hook.
 *
 * Mirrors the canonical Mage_Adminhtml_Block_Cms_Page_Edit_Form pattern.
 */
class Maho_Giftcard_Block_Adminhtml_Giftcard_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form([
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/save', ['id' => $this->getRequest()->getParam('id')]),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ]);
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
