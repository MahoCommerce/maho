<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

class Mage_ImportExport_Block_Adminhtml_Export_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form([
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/getFilter'),
            'method' => 'post',
        ]);
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => Mage::helper('importexport')->__('Export Settings')]);
        $fieldset->addField('entity', 'select', [
            'name'     => 'entity',
            'title'    => Mage::helper('importexport')->__('Entity Type'),
            'label'    => Mage::helper('importexport')->__('Entity Type'),
            'required' => false,
            'onchange' => 'editForm.getFilter();',
            'values'   => Mage::getModel('importexport/source_export_entity')->toOptionArray(),
        ]);
        $fieldset->addField('file_format', 'select', [
            'name'     => 'file_format',
            'title'    => Mage::helper('importexport')->__('Export File Format'),
            'label'    => Mage::helper('importexport')->__('Export File Format'),
            'required' => false,
            'values'   => Mage::getModel('importexport/source_export_format')->toOptionArray(),
        ]);

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
