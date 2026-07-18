<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

class Mage_ImportExport_Block_Adminhtml_Import_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Add fieldset
     */
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form([
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/validate'),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ]);
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => Mage::helper('importexport')->__('Import Settings')]);
        $fieldset->addField('entity', 'select', [
            'name'     => 'entity',
            'title'    => Mage::helper('importexport')->__('Entity Type'),
            'label'    => Mage::helper('importexport')->__('Entity Type'),
            'required' => true,
            'values'   => Mage::getModel('importexport/source_import_entity')->toOptionArray(),
        ]);
        $fieldset->addField('behavior', 'select', [
            'name'     => 'behavior',
            'title'    => Mage::helper('importexport')->__('Import Behavior'),
            'label'    => Mage::helper('importexport')->__('Import Behavior'),
            'required' => true,
            'values'   => Mage::getModel('importexport/source_import_behavior')->toOptionArray(),
        ]);
        $fieldset->addField(Mage_ImportExport_Model_Import::FIELD_NAME_SOURCE_FILE, 'file', [
            'name'     => Mage_ImportExport_Model_Import::FIELD_NAME_SOURCE_FILE,
            'label'    => Mage::helper('importexport')->__('Select File to Import'),
            'title'    => Mage::helper('importexport')->__('Select File to Import'),
            'required' => true,
        ]);

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
