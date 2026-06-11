<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

use Maho\Data\Form;

class Maho_CatalogLinkRule_Block_Adminhtml_Rule_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $form = new Form([
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $this->getRequest()->getParam('id')]),
            'method' => 'post',
        ]);

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
