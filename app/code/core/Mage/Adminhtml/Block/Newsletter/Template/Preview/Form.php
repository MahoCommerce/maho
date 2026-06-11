<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Newsletter_Template_Preview_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Preparing from for revision page
     *
     * @return Mage_Adminhtml_Block_Widget_Form
     */
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form([
            'id' => 'preview_form',
            'action' => $this->getUrl('*/*/drop', ['_current' => true]),
            'method' => 'post',
        ]);

        if ($data = $this->getFormData()) {
            $mapper = ['preview_store_id' => 'store_id'];

            foreach ($data as $key => $value) {
                if (array_key_exists($key, $mapper)) {
                    $name = $mapper[$key];
                } else {
                    $name = $key;
                }
                $form->addField($key, 'hidden', ['name' => $name]);
            }
            $form->setValues($data);
        }

        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
