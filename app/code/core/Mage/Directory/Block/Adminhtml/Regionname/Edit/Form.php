<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Regionname_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $regionName = Mage::registry('current_region_name');

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', [
                'locale' => $this->getRequest()->getParam('locale'),
                'region_id' => $this->getRequest()->getParam('region_id'),
            ]),
            'method' => 'post',
            'enctype' => 'multipart/form-data',
        ]);

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Region Name Information'),
            'class' => 'fieldset-wide',
        ]);

        // Get available regions for selection
        $regions = [];
        $regionCollection = Mage::getResourceModel('directory/region_collection');
        foreach ($regionCollection as $region) {
            $countryName = Mage::app()->getLocale()->getCountryTranslation($region->getCountryId());
            $regions[] = [
                'value' => $region->getRegionId(),
                'label' => $countryName . ' - ' . $region->getDefaultName() . ' (' . $region->getCode() . ')',
            ];
        }

        $fieldset->addField('region_id', 'select', [
            'name' => 'region_id',
            'label' => Mage::helper('adminhtml')->__('Region'),
            'title' => Mage::helper('adminhtml')->__('Region'),
            'required' => true,
            'values' => $regions,
            'disabled' => isset($regionName['region_id']) && $regionName['region_id'],
        ]);

        // Get available locales
        $locales = [];
        $availableLocales = Mage::app()->getLocale()->getOptionLocales();
        foreach ($availableLocales as $locale) {
            $locales[] = [
                'value' => $locale['value'],
                'label' => $locale['label'] . ' (' . $locale['value'] . ')',
            ];
        }

        $fieldset->addField('locale', 'select', [
            'name' => 'locale',
            'label' => Mage::helper('adminhtml')->__('Locale'),
            'title' => Mage::helper('adminhtml')->__('Locale'),
            'required' => true,
            'values' => $locales,
            'disabled' => isset($regionName['locale']) && $regionName['locale'],
        ]);

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => Mage::helper('adminhtml')->__('Name'),
            'title' => Mage::helper('adminhtml')->__('Name'),
            'required' => true,
            'maxlength' => 255,
            'note' => Mage::helper('adminhtml')->__('Localized name for this region'),
        ]);

        if (is_array($regionName)) {
            $form->setValues($regionName);
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
