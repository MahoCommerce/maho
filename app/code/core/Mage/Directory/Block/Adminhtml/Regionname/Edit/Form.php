<?php

declare(strict_types=1);

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

        $currentLocale = $this->getRequest()->getParam('locale');
        $currentRegionId = $this->getRequest()->getParam('region_id');

        // Determine if this is an update operation
        $isUpdate = $regionName && isset($regionName['locale']) && isset($regionName['region_id']) && $regionName['locale'] && $regionName['region_id'];

        // Debug logging
        Mage::log('Form Debug - Registry data: ' . print_r($regionName, true), null, 'regionname_form_debug.log');
        Mage::log('Form Debug - Is Update: ' . ($isUpdate ? 'YES' : 'NO'), null, 'regionname_form_debug.log');

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save'),
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
        $regionCollection = Mage::getResourceModel('directory/region_collection')
            ->setOrder('default_name', 'ASC');

        foreach ($regionCollection as $region) {
            $countryName = Mage::app()->getLocale()->getCountryTranslation($region->getCountryId());
            $regions[] = [
                'value' => $region->getRegionId(),
                'label' => $countryName . ' - ' . $region->getDefaultName() . ' (' . $region->getCode() . ')',
                'country_name' => $countryName,
                'region_name' => $region->getDefaultName(),
            ];
        }

        // Sort regions alphabetically by country name, then by region name
        usort($regions, function ($a, $b) {
            $countryCompare = strcmp($a['country_name'], $b['country_name']);
            if ($countryCompare === 0) {
                return strcmp($a['region_name'], $b['region_name']);
            }
            return $countryCompare;
        });

        $fieldset->addField('region_id', 'select', [
            'name' => 'region_id',
            'label' => Mage::helper('adminhtml')->__('Region'),
            'title' => Mage::helper('adminhtml')->__('Region'),
            'required' => true,
            'values' => $regions,
            'disabled' => $isUpdate,
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
            'disabled' => $isUpdate,
        ]);

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => Mage::helper('adminhtml')->__('Name'),
            'title' => Mage::helper('adminhtml')->__('Name'),
            'required' => true,
            'maxlength' => 255,
            'note' => Mage::helper('adminhtml')->__('Localized name for this region'),
        ]);

        // Add hidden field to indicate if this is an update operation
        $fieldset->addField('is_update', 'hidden', [
            'name' => 'is_update',
            'value' => $isUpdate ? '1' : '0',
        ]);

        // Add hidden fields for original locale and region_id when updating
        if ($isUpdate) {
            $fieldset->addField('original_locale', 'hidden', [
                'name' => 'original_locale',
                'value' => (string) $regionName['locale'],
            ]);

            $fieldset->addField('original_region_id', 'hidden', [
                'name' => 'original_region_id',
                'value' => (string) $regionName['region_id'],
            ]);

            // Also add hidden fields with the current values since disabled fields don't submit
            $fieldset->addField('locale_hidden', 'hidden', [
                'name' => 'locale',
                'value' => (string) $regionName['locale'],
            ]);

            $fieldset->addField('region_id_hidden', 'hidden', [
                'name' => 'region_id',
                'value' => (string) $regionName['region_id'],
            ]);

            // Debug logging
            Mage::log('Form Debug - Setting hidden fields with values:', null, 'regionname_form_debug.log');
            Mage::log('Form Debug - locale: ' . $regionName['locale'], null, 'regionname_form_debug.log');
            Mage::log('Form Debug - region_id: ' . $regionName['region_id'], null, 'regionname_form_debug.log');
            Mage::log('Form Debug - is_update: 1', null, 'regionname_form_debug.log');
        }

        if (is_array($regionName)) {
            $form->setValues($regionName);
        }

        // Set explicit values for hidden fields after setValues() to prevent override
        if ($isUpdate) {
            $form->getElement('is_update')->setValue('1');
            $form->getElement('original_locale')->setValue((string) $regionName['locale']);
            $form->getElement('original_region_id')->setValue((string) $regionName['region_id']);
            $form->getElement('locale_hidden')->setValue((string) $regionName['locale']);
            $form->getElement('region_id_hidden')->setValue((string) $regionName['region_id']);
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
