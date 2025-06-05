<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Country_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $country = Mage::registry('current_country');


        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $this->getRequest()->getParam('id')]),
            'method' => 'post',
            'enctype' => 'multipart/form-data',
        ]);

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Country Information'),
            'class' => 'fieldset-wide',
        ]);

        if ($country->getCountryId()) {
            $fieldset->addField('country_id', 'hidden', [
                'name' => 'country_id',
            ]);
        }

        $fieldset->addField('country_id_input', 'text', [
            'name' => $country->getCountryId() ? 'country_id_display' : 'country_id',
            'label' => Mage::helper('adminhtml')->__('Country ID'),
            'title' => Mage::helper('adminhtml')->__('Country ID'),
            'required' => true,
            'disabled' => (bool) $country->getCountryId(),
            'value' => $country->getCountryId(),
            'note' => $country->getCountryId() ?
                Mage::helper('adminhtml')->__('Country ID cannot be changed after creation') :
                Mage::helper('adminhtml')->__('Two character country code (e.g. US, GB, DE)'),
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

        // Explicitly set the country_id_input field value since it has a different field ID
        if ($country->getCountryId() && $form->getElement('country_id_input')) {
            $form->getElement('country_id_input')->setValue($country->getCountryId());
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
