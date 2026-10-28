<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

class Mage_Eav_Block_Adminhtml_Attribute_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'attribute_id';
        $this->_blockGroup = 'eav';
        $this->_controller = 'adminhtml_attribute';

        parent::__construct();

        $this->_addButton('save_and_edit_button', [
            'label' => Mage::helper('eav')->__('Save and Continue'),
            'onclick' => 'eavAttributeForm.save(true)',
            'class' => 'save',
        ], 100);
        $this->_updateButton('save', 'onclick', 'eavAttributeForm.save()');

        if (!$this->getAttribute()->getIsUserDefined()) {
            $this->_removeButton('delete');
        }

        $config = Mage::helper('core')->jsonEncode([
            'tabsId' => 'eav_attribute_tabs',
            'hiddenFields' => $this->getHiddenFields(),
        ]);
        $this->_formScripts[] = "var eavAttributeForm = new EavAttributeForm({$config});";
    }

    public function getAttribute(): Mage_Eav_Model_Entity_Attribute
    {
        return Mage::registry('entity_attribute');
    }

    #[\Override]
    public function getHeaderText()
    {
        if ($this->getAttribute()->getId()) {
            $label = $this->getAttribute()->getFrontendLabel();
            $label = is_array($label) ? reset($label) : $label;
            return Mage::helper('eav')->__('Edit Attribute "%s"', $this->escapeHtml((string) $label));
        }
        return Mage::helper('eav')->__('New Attribute');
    }

    public function getValidationUrl(): string
    {
        return $this->getUrl('*/*/validate', ['_current' => true]);
    }

    #[\Override]
    public function getFormActionUrl()
    {
        return $this->getUrl('*/*/save', ['_current' => true, 'back' => null]);
    }

    /**
     * Return the fields that the edit form hides for each input type.
     *
     * @return array<string, list<string>>
     */
    protected function getHiddenFields(): array
    {
        return [
            'image' => ['is_unique', 'frontend_class', '_default_value'],
            'multiline' => ['is_unique', 'frontend_class', '_default_value'],
            'boolean' => ['is_unique', 'frontend_class'],
            'date' => ['frontend_class'],
            'select' => ['frontend_class'],
            'multiselect' => ['frontend_class'],
            'textarea' => ['frontend_class'],
        ];
    }
}
