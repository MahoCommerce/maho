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
        $countryId = $this->getRequest()->getParam('id');

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $countryId]),
            'method' => 'post',
            'enctype' => 'multipart/form-data',
        ]);

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Country Information'),
            'class' => 'fieldset-wide',
        ]);

        $fieldset->addField('country_id', 'text', [
            'name' => 'country_id',
            'label' => Mage::helper('adminhtml')->__('Country ID'),
            'title' => Mage::helper('adminhtml')->__('Country ID'),
            'required' => true,
            'disabled' => (bool) $countryId,
            'note' => $countryId
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
}
