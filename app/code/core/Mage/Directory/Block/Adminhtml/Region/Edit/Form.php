<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Region_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $region = Mage::registry('current_region');

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $this->getRequest()->getParam('id')]),
            'method' => 'post',
            'enctype' => 'multipart/form-data',
        ]);

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Region Information'),
            'class' => 'fieldset-wide',
        ]);

        if ($region->getRegionId()) {
            $fieldset->addField('region_id', 'hidden', [
                'name' => 'region_id',
            ]);
        }

        // Create country options
        $countries = Mage::getResourceModel('directory/country_collection')
            ->loadByStore()
            ->toOptionArray('');

        $fieldset->addField('country_id', 'select', [
            'name' => 'country_id',
            'label' => Mage::helper('adminhtml')->__('Country'),
            'title' => Mage::helper('adminhtml')->__('Country'),
            'required' => true,
            'values' => $countries,
        ]);

        $fieldset->addField('code', 'text', [
            'name' => 'code',
            'label' => Mage::helper('adminhtml')->__('Region Code'),
            'title' => Mage::helper('adminhtml')->__('Region Code'),
            'maxlength' => 32,
            'note' => Mage::helper('adminhtml')->__('Short code for this region (e.g. CA, NY, TX)'),
        ]);

        $fieldset->addField('default_name', 'text', [
            'name' => 'default_name',
            'label' => Mage::helper('adminhtml')->__('Default Name'),
            'title' => Mage::helper('adminhtml')->__('Default Name'),
            'required' => true,
            'maxlength' => 255,
            'note' => Mage::helper('adminhtml')->__('Default name for this region in English'),
        ]);

        $form->setValues($region->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
