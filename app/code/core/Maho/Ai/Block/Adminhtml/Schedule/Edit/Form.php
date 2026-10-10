<?php

/**
 * The form element around the tabs of a scheduled assistant task.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Schedule_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['_current' => ['id']]),
            'method' => 'post',
        ]);
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
