<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Country_Edit_Tab_Main extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $country = Mage::registry('current_country');
        $isUpdate = (bool) $country->getOrigData('country_id');

        $form = new Varien_Data_Form();

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Country Information'),
            'class' => 'fieldset-wide',
        ]);

        $fieldset->addField('country_id', 'text', [
            'name' => 'country_id',
            'label' => Mage::helper('adminhtml')->__('Country ID'),
            'title' => Mage::helper('adminhtml')->__('Country ID'),
            'required' => true,
            'disabled' => $isUpdate,
            'note' => $isUpdate
                ? Mage::helper('adminhtml')->__('Country ID cannot be changed after creation')
                : Mage::helper('adminhtml')->__('Two character country code (e.g. US, GB, DE)'),
        ]);

        $fieldset->addField('iso2_code', 'text', [
            'name' => 'iso2_code',
            'label' => Mage::helper('adminhtml')->__('ISO2 Code'),
            'title' => Mage::helper('adminhtml')->__('ISO2 Code'),
            'maxlength' => 2,
            'note' => Mage::helper('adminhtml')->__('Two character ISO code (usually same as Country ID)'),
        ]);

        $fieldset->addField('iso3_code', 'text', [
            'name' => 'iso3_code',
            'label' => Mage::helper('adminhtml')->__('ISO3 Code'),
            'title' => Mage::helper('adminhtml')->__('ISO3 Code'),
            'maxlength' => 3,
            'note' => Mage::helper('adminhtml')->__('Three character ISO code (e.g. USA, GBR, DEU)'),
        ]);

        $form->setValues($country->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('adminhtml')->__('Country Information');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('adminhtml')->__('Country Information');
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
