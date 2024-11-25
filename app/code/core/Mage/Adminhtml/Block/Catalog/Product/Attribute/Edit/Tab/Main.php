<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product attribute add/edit form main tab
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Attribute_Edit_Tab_Main extends Mage_Eav_Block_Adminhtml_Attribute_Edit_Main_Abstract
{
    /**
     * Add additional form elements for editing product attributes
     */
    #[\Override]
    protected function _prepareForm()
    {
        parent::_prepareForm();

        $attributeObject = $this->getAttributeObject();
        $entityTypeCode = $attributeObject->getEntityType()->getEntityTypeCode();

        /** @var Varien_Data_Form $form */
        $form = $this->getForm();

        /** @var Varien_Data_Form_Element_Fieldset $fieldset */
        $fieldset = $form->getElement('base_fieldset');

        $inputTypes = Mage::helper('eav')->getInputTypes($entityTypeCode);
        if ($attributeObject->getFrontendInput() !== 'gallery') {
            unset($inputTypes['gallery']);
        }
        $form->getElement('frontend_input')->setValues($inputTypes);

        $scopes = [
            Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE   => Mage::helper('catalog')->__('Store View'),
            Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE => Mage::helper('catalog')->__('Website'),
            Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL  => Mage::helper('catalog')->__('Global'),
        ];

        if (in_array($attributeObject->getAttributeCode(), ['status', 'tax_class_id'])) {
            unset($scopes[Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE]);
        }

        $fieldset->addField('is_global', 'select', [
            'name'  => 'is_global',
            'label' => Mage::helper('catalog')->__('Scope'),
            'title' => Mage::helper('catalog')->__('Scope'),
            'note'  => Mage::helper('catalog')->__('Declare attribute value saving scope'),
            'values' => $scopes
        ], 'attribute_code');

        $fieldset->addField('apply_to', 'apply', [
            'name'        => 'apply_to[]',
            'label'       => Mage::helper('catalog')->__('Apply To'),
            'values'      => Mage_Catalog_Model_Product_Type::getOptions(),
            'mode_labels' => [
                'all'     => Mage::helper('catalog')->__('All Product Types'),
                'custom'  => Mage::helper('catalog')->__('Selected Product Types')
            ],
            'required'    => true
        ]);

        $fieldset->addField('is_configurable', 'boolean', [
            'name' => 'is_configurable',
            'label' => Mage::helper('catalog')->__('Use To Create Configurable Product'),
        ]);

        $form->getElement('frontend_input')
             ->setLabel(Mage::helper('catalog')->__('Input Type for Store Owner'))
             ->setTitle(Mage::helper('catalog')->__('Input Type for Store Owner'));

        $form->getElement('frontend_class')
             ->setLabel(Mage::helper('catalog')->__('Input Validation for Store Owner'))
             ->setTitle(Mage::helper('catalog')->__('Input Validation for Store Owner'));

        // frontend properties fieldset
        $fieldset = $form->addFieldset('front_fieldset', ['legend' => Mage::helper('catalog')->__('Frontend Properties')]);

        $fieldset->addField('is_searchable', 'boolean', [
            'name'     => 'is_searchable',
            'label'    => Mage::helper('catalog')->__('Use in Quick Search'),
            'title'    => Mage::helper('catalog')->__('Use in Quick Search'),
        ]);

        $fieldset->addField('is_visible_in_advanced_search', 'boolean', [
            'name' => 'is_visible_in_advanced_search',
            'label' => Mage::helper('catalog')->__('Use in Advanced Search'),
            'title' => Mage::helper('catalog')->__('Use in Advanced Search'),
        ]);

        $fieldset->addField('is_comparable', 'boolean', [
            'name' => 'is_comparable',
            'label' => Mage::helper('catalog')->__('Comparable on Front-end'),
            'title' => Mage::helper('catalog')->__('Comparable on Front-end'),
        ]);

        $fieldset->addField('is_filterable', 'select', [
            'name' => 'is_filterable',
            'label' => Mage::helper('catalog')->__('Use In Layered Navigation'),
            'values' => [
                ['value' => '0', 'label' => Mage::helper('catalog')->__('No')],
                ['value' => '1', 'label' => Mage::helper('catalog')->__('Filterable (with results)')],
                ['value' => '2', 'label' => Mage::helper('catalog')->__('Filterable (no results)')],
            ],
        ]);

        $fieldset->addField('is_filterable_in_search', 'boolean', [
            'name' => 'is_filterable_in_search',
            'label' => Mage::helper('catalog')->__('Use In Search Results Layered Navigation'),
        ]);

        $fieldset->addField('is_used_for_promo_rules', 'boolean', [
            'name' => 'is_used_for_promo_rules',
            'label' => Mage::helper('catalog')->__('Use for Promo Rule Conditions'),
            'title' => Mage::helper('catalog')->__('Use for Promo Rule Conditions'),
        ]);

        $fieldset->addField('position', 'text', [
            'name' => 'position',
            'label' => Mage::helper('catalog')->__('Position'),
            'title' => Mage::helper('catalog')->__('Position in Layered Navigation'),
            'note' => Mage::helper('catalog')->__('Position of attribute in layered navigation block'),
            'class' => 'validate-digits',
        ]);

        $fieldset->addField('is_html_allowed_on_front', 'boolean', [
            'name' => 'is_html_allowed_on_front',
            'label' => Mage::helper('catalog')->__('Allow HTML Tags on Frontend'),
            'title' => Mage::helper('catalog')->__('Allow HTML Tags on Frontend'),
        ]);

        $fieldset->addField('is_wysiwyg_enabled', 'boolean', [
            'name' => 'is_wysiwyg_enabled',
            'label' => Mage::helper('catalog')->__('Enable WYSIWYG'),
            'title' => Mage::helper('catalog')->__('Enable WYSIWYG'),
        ]);

        // if (!$attributeObject->getId() || $attributeObject->getIsWysiwygEnabled()) {
        //     $attributeObject->setIsHtmlAllowedOnFront(1);
        // }

        $fieldset->addField('is_visible_on_front', 'boolean', [
            'name'      => 'is_visible_on_front',
            'label'     => Mage::helper('catalog')->__('Visible on Product View Page on Front-end'),
            'title'     => Mage::helper('catalog')->__('Visible on Product View Page on Front-end'),
        ]);

        $fieldset->addField('used_in_product_listing', 'boolean', [
            'name'      => 'used_in_product_listing',
            'label'     => Mage::helper('catalog')->__('Used in Product Listing'),
            'title'     => Mage::helper('catalog')->__('Used in Product Listing'),
            'note'      => Mage::helper('catalog')->__('Depends on design theme'),
        ]);
        $fieldset->addField('used_for_sort_by', 'boolean', [
            'name'      => 'used_for_sort_by',
            'label'     => Mage::helper('catalog')->__('Used for Sorting in Product Listing'),
            'title'     => Mage::helper('catalog')->__('Used for Sorting in Product Listing'),
            'note'      => Mage::helper('catalog')->__('Depends on design theme'),
        ]);

        $form->getElement('apply_to')->setSize(5);

        if ($applyTo = $attributeObject->getApplyTo()) {
            $applyTo = is_array($applyTo) ? $applyTo : explode(',', $applyTo);
            $form->getElement('apply_to')->setValue($applyTo);
        } else {
            $form->getElement('apply_to')->addClass('no-display ignore-validate');
        }

        /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $block */
        $block = $this->_getDependence();

        $block
            ->addFieldDependence('is_filterable', 'frontend_input', ['select', 'multiselect', 'price'])
            ->addFieldDependence('is_filterable_in_search', 'frontend_input', ['select', 'multiselect', 'price'])
            ->addComplexFieldDependence('is_required', $block::MODE_NOT, [
                'frontend_input' => ['media_image'],
            ])
            ->addComplexFieldDependence('is_unique', $block::MODE_NOT, [
                'frontend_input' => ['media_image'],
            ])
            ->addComplexFieldDependence('is_global', $block::MODE_NOT, [
                'frontend_input' => ['price'],
            ])
            ->addComplexFieldDependence('is_configurable', $block::MODE_AND, [
                'is_global' => '1',
                'frontend_input' => ['select'],
            ])
            ->addComplexFieldDependence('position', $block::MODE_NOT, [
                'is_filterable' => '0',
            ])
            ->addComplexFieldDependence('used_for_sort_by', $block::MODE_NOT, [
                'frontend_input' => ['multiselect', 'textarea', 'gallery'],
            ])
            ->addComplexFieldDependence('is_html_allowed_on_front', $block::MODE_AND, [
                'frontend_input' => ['text', 'textarea', 'select', 'multiselect', 'customselect'],
            ])
            ->addComplexFieldDependence('is_wysiwyg_enabled', $block::MODE_AND, [
                'frontend_input' => 'textarea',
                'is_html_allowed_on_front' => '1',
            ]);

        Mage::dispatchEvent('adminhtml_catalog_product_attribute_edit_prepare_form', [
            'form'       => $form,
            'attribute'  => $attributeObject,
            'dependence' => $block,
        ]);

        return $this;
    }

    /**
     * Set additional element types for product attribute edit form
     */
    #[\Override]
    protected function _getAdditionalElementTypes()
    {
        return [
            'apply' => Mage::getConfig()->getBlockClassName('adminhtml/catalog_product_helper_form_apply'),
        ];
    }
}
