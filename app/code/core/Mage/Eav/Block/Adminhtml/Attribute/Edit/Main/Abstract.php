<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * EAV attribute add/edit form main tab
 *
 * @category   Mage
 * @package    Mage_Eav
 */
abstract class Mage_Eav_Block_Adminhtml_Attribute_Edit_Main_Abstract extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /** @var Mage_Eav_Model_Entity_Attribute $_attribute */
    protected $_attribute = null;

    /**
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @return $this
     */
    public function setAttributeObject($attribute)
    {
        $this->_attribute = $attribute;
        return $this;
    }

    /**
     * @return Mage_Eav_Model_Entity_Attribute
     */
    public function getAttributeObject()
    {
        return $this->_attribute ?? Mage::registry('entity_attribute');
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('eav')->__('Properties');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('eav')->__('Properties');
    }

    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    #[\Override]
    public function isHidden()
    {
        return false;
    }

    /**
     *
     *
     * @return Mage_Core_Block_Abstract
     */
    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        // If entity type has a scoped table, change renderer to allow "Use Default Value" checkbox
        if (!Mage::app()->isSingleStoreMode() && $this->getAttributeObject()->getResource()->hasScopeTable()) {
            Varien_Data_Form::setFieldsetElementRenderer(
                $this->getLayout()->createBlock('eav/adminhtml_attribute_edit_renderer_fieldset_element')
            );
        }

        return $this;
    }

    /**
     * Prepare default form elements for editing attribute
     */
    #[\Override]
    protected function _prepareForm()
    {
        $attributeObject = $this->getAttributeObject();
        $entityTypeCode = $attributeObject->getEntityType()->getEntityTypeCode();

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getData('action'),
            'method' => 'post'
        ]);

        $form->setDataObject($attributeObject);

        $fieldset = $form->addFieldset(
            'base_fieldset',
            ['legend' => Mage::helper('eav')->__('Attribute Properties')]
        );
        if ($attributeObject->getAttributeId()) {
            $fieldset->addField('attribute_id', 'hidden', [
                'name' => 'attribute_id',
            ]);
        }

        $this->_addElementTypes($fieldset);

        $validateClass = sprintf(
            'validate-code validate-length maximum-length-%d',
            Mage_Eav_Model_Entity_Attribute::ATTRIBUTE_CODE_MAX_LENGTH
        );
        $fieldset->addField('attribute_code', 'text', [
            'name'  => 'attribute_code',
            'label' => Mage::helper('eav')->__('Attribute Code'),
            'title' => Mage::helper('eav')->__('Attribute Code'),
            'note'  => Mage::helper('eav')->__(
                'For internal use. Must be unique with no spaces. Maximum length of attribute code must be less then %s symbols',
                Mage_Eav_Model_Entity_Attribute::ATTRIBUTE_CODE_MAX_LENGTH
            ),
            'class' => $validateClass,
            'required' => true,
        ]);

        $fieldset->addField('frontend_input', 'select', [
            'name'   => 'frontend_input',
            'label'  => Mage::helper('eav')->__('Input Type'),
            'title'  => Mage::helper('eav')->__('Input Type'),
            'values' => Mage::helper('eav')->getInputTypes($entityTypeCode),
            'value'  => 'text',
        ]);

        $fieldset->addField('frontend_class', 'select', [
            'name'   => 'frontend_class',
            'label'  => Mage::helper('eav')->__('Input Validation'),
            'title'  => Mage::helper('eav')->__('Input Validation'),
            'values' => Mage::helper('eav')->getFrontendClasses($entityTypeCode),
        ]);

        $fieldset->addField('is_required', 'boolean', [
            'name'  => 'is_required',
            'label' => Mage::helper('eav')->__('Values Required'),
            'title' => Mage::helper('eav')->__('Values Required'),
        ]);

        $fieldset->addField('is_unique', 'boolean', [
            'name'  => 'is_unique',
            'label' => Mage::helper('eav')->__('Unique Value'),
            'title' => Mage::helper('eav')->__('Unique Value'),
            'note'  => Mage::helper('eav')->__(
                'Not shared with other %s',
                strtolower(Mage::helper('eav')->formatTypeCode($entityTypeCode))
            )
        ]);

        $fieldset->addField('default_value_text', 'text', [
            'name'  => 'default_value_text',
            'label' => Mage::helper('eav')->__('Default Value'),
            'title' => Mage::helper('eav')->__('Default Value'),
            'value' => $attributeObject->getDefaultValue(),
        ]);

        $fieldset->addField('default_value_yesno', 'boolean', [
            'name'  => 'default_value_yesno',
            'label' => Mage::helper('eav')->__('Default Value'),
            'title' => Mage::helper('eav')->__('Default Value'),
            'value' => $attributeObject->getDefaultValue(),
        ]);

        $fieldset->addField('default_value_date', 'date', [
            'name'   => 'default_value_date',
            'label'  => Mage::helper('eav')->__('Default Value'),
            'title'  => Mage::helper('eav')->__('Default Value'),
            'value'  => $attributeObject->getDefaultValue(),
            'format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
        ]);

        $fieldset->addField('default_value_textarea', 'textarea', [
            'name'  => 'default_value_textarea',
            'label' => Mage::helper('eav')->__('Default Value'),
            'title' => Mage::helper('eav')->__('Default Value'),
            'value' => $attributeObject->getDefaultValue(),
        ]);

        if ($attributeObject->getResource()->hasFormTable()) {
            $fieldset->addField('used_in_forms', 'multiselect', [
                'name'   => 'used_in_forms',
                'label'  => Mage::helper('adminhtml')->__('Use in Forms'),
                'title'  => Mage::helper('adminhtml')->__('Use in Forms'),
                'values' => Mage::helper('eav')->getForms($entityTypeCode),
                'value'  => $attributeObject->getUsedInForms(),
            ]);
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Return dependency block object
     */
    protected function _getDependence(): Mage_Adminhtml_Block_Widget_Form_Element_Dependence
    {
        if (!$this->getChild('form_after')) {
            /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $block */
            $block = $this->getLayout()->createBlock('adminhtml/widget_form_element_dependence');
            $block->addConfigOption('on_event', false)
                ->addFieldDependence('frontend_class', 'frontend_input', ['text', 'customselect']);
            $this->setChild('form_after', $block);
        }
        return $this->getChild('form_after');
    }

    /**
     * Initialize form fields values
     *
     * @inheritDoc
     */
    #[\Override]
    protected function _initFormValues()
    {
        Mage::dispatchEvent('adminhtml_block_eav_attribute_edit_form_init', ['form' => $this->getForm()]);

        $attributeObject = $this->getAttributeObject();
        $data = $attributeObject->getData();

        // If website specified, unprefix relevant fields before adding to form
        if ($attributeObject->getWebsite() && (int)$attributeObject->getWebsite()->getId()) {
            foreach ($attributeObject->getResource()->getScopeFields($attributeObject) as $field) {
                if (array_key_exists('scope_' . $field, $data)) {
                    $data[$field] = $data['scope_' . $field];
                    unset($data['scope_' . $field]);
                }
            }
        }

        $this->getForm()->addValues($data);
        return parent::_initFormValues();
    }

    /**
     * This method is called before rendering HTML
     *
     * @return Mage_Eav_Block_Adminhtml_Attribute_Edit_Main_Abstract
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        parent::_beforeToHtml();

        $form = $this->getForm();
        $attributeObject = $this->getAttributeObject();

        // Disable any fields in config global/eav_attributes/$entity_type/$field/locked_fields
        if ($attributeObject->getId()) {
            $disableAttributeFields = Mage::helper('eav')
                ->getAttributeLockedFields($attributeObject->getEntityType()->getEntityTypeCode());
            $disabledFields = $disableAttributeFields[$attributeObject->getAttributeCode()] ?? [];

            // Add in default locked fields
            $disabledFields[] = 'attribute_code';
            $disabledFields[] = 'frontend_input';
            if (!$attributeObject->getIsUserDefined()) {
                $disabledFields[] = 'is_unique';
            }

            foreach ($disabledFields as $field) {
                if ($elm = $form->getElement($field)) {
                    $elm->setDisabled(1);
                    $elm->setReadonly(1);
                }
            }
        }

        // Set scope value and disable global fields if website selected
        if ($attributeObject->getResource()->hasScopeTable()) {
            $websiteId = $attributeObject->getWebsite() ? (int)$attributeObject->getWebsite()->getId() : 0;
            $scopeFields = $attributeObject->getResource()->getScopeFields($attributeObject);

            /** @var Varien_Data_Form_Element_Fieldset $fieldset */
            $fieldset = $this->getForm()->getElement('base_fieldset');
            foreach ($fieldset->getElements() as $elm) {
                $field = $elm->getId();
                if (str_starts_with($field, 'default_value')) {
                    $field = 'default_value';
                }
                if (in_array($field, $scopeFields)) {
                    $elm->setScope(Mage_Eav_Model_Entity_Attribute::SCOPE_WEBSITE);
                } else {
                    $elm->setScope(Mage_Eav_Model_Entity_Attribute::SCOPE_GLOBAL);
                    $elm->setDisabled($elm->getDisabled() || $websiteId);
                }
            }
        }

        return $this;
    }
}
