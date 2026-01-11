<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Region_Edit_Tab_Main extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $region = Mage::registry('current_region');
        $isUpdate = (bool) $region->getId();

        $form = new \Maho\Data\Form();

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('directory')->__('Region Information'),
            'class' => 'fieldset-wide',
        ]);

        // Create country options
        $countries = Mage::getModel('adminhtml/system_config_source_country')->toOptionArray();
        unset($countries[0]);

        $fieldset->addField('country_id', 'select', [
            'name' => 'country_id',
            'label' => Mage::helper('directory')->__('Country'),
            'title' => Mage::helper('directory')->__('Country'),
            'required' => true,
            'values' => $countries,
            'disabled' => $isUpdate,
        ]);

        $fieldset->addField('code', 'text', [
            'name' => 'code',
            'label' => Mage::helper('directory')->__('Region Code'),
            'title' => Mage::helper('directory')->__('Region Code'),
            'maxlength' => 32,
            'note' => Mage::helper('directory')->__('Short code for this region (e.g. CA, NY, TX)'),
        ]);

        $fieldset->addField('default_name', 'text', [
            'name' => 'default_name',
            'label' => Mage::helper('directory')->__('Default Name'),
            'title' => Mage::helper('directory')->__('Default Name'),
            'required' => true,
            'maxlength' => 255,
            'note' => Mage::helper('directory')->__('Default name for this region in English'),
        ]);

        $form->setValues($region->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('directory')->__('Region Information');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('directory')->__('Region Information');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }
}
