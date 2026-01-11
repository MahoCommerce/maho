<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Region_Edit_Tab_Translations_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('translation_');

        $fieldset = $form->addFieldset('add_fieldset', [
            'legend' => Mage::helper('directory')->__('Add Translation'),
            'class' => 'fieldset-wide ignore-validate',
        ]);

        // Get available locales
        $locales = Mage::app()->getLocale()->getOptionLocales();
        foreach ($locales as &$locale) {
            $locale['label'] .= " ({$locale['value']})";
        }

        $fieldset->addField('locale', 'select', [
            'name' => 'locale',
            'label' => Mage::helper('directory')->__('Locale'),
            'title' => Mage::helper('directory')->__('Locale'),
            'required' => true,
            'values' => $locales,
        ]);

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => Mage::helper('directory')->__('Name'),
            'title' => Mage::helper('directory')->__('Name'),
            'required' => true,
            'maxlength' => 255,
            'note' => Mage::helper('directory')->__('Localized name for this region'),
        ]);

        $gridBlock = $this->getLayout()->getBlock('directory_region_edit_tab_translations_grid');
        $gridBlockJsObject = '';
        if ($gridBlock) {
            $gridBlockJsObject = $gridBlock->getJsObjectName();
        }

        $idPrefix = $form->getHtmlIdPrefix();
        $saveUrl = $this->getSaveUrl();

        $fieldset->addField('save_button', 'note', [
            'text' => $this->getButtonHtml(
                Mage::helper('adminhtml')->__('Save'),
                "DirectoryEditForm.saveTranslation('$idPrefix', '$saveUrl', '$gridBlockJsObject')",
                'save',
            ),
        ]);

        $this->setForm($form);

        return parent::_prepareForm();
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/translationSave', ['_current' => true]);
    }
}
