<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Category_Tab_Attributes extends Mage_Adminhtml_Block_Catalog_Form
{
    /**
     * Retrieve Category object
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        return Mage::registry('current_category');
    }

    /**
     * Initialize tab
     */
    public function __construct()
    {
        parent::__construct();
        $this->setShowGlobalIcon(true);
    }

    /**
     * Load Wysiwyg on demand and Prepare layout
     */
    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (Mage::getSingleton('cms/wysiwyg_config')->isEnabled()) {
            $this->getLayout()->getBlock('head')->setCanLoadWysiwyg(true);
        }
        return $this;
    }

    /**
     * Prepare form before rendering HTML
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareForm()
    {
        $group      = $this->getGroup();
        $attributes = $this->getAttributes();

        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('group_' . $group->getId());
        $form->setDataObject($this->getCategory());

        $fieldset = $form->addFieldset('fieldset_group_' . $group->getId(), [
            'legend'    => Mage::helper('catalog')->__($group->getAttributeGroupName()),
            'class'     => 'fieldset-wide',
        ]);

        if ($this->getAddHiddenFields()) {
            if (!$this->getCategory()->getId()) {
                // path
                if ($this->getRequest()->getParam('parent')) {
                    $fieldset->addField('path', 'hidden', [
                        'name'  => 'path',
                        'value' => $this->getRequest()->getParam('parent'),
                    ]);
                } else {
                    $fieldset->addField('path', 'hidden', [
                        'name'  => 'path',
                        'value' => 1,
                    ]);
                }
            } else {
                $fieldset->addField('id', 'hidden', [
                    'name'  => 'id',
                    'value' => $this->getCategory()->getId(),
                ]);
                $fieldset->addField('path', 'hidden', [
                    'name'  => 'path',
                    'value' => $this->getCategory()->getPath(),
                ]);
            }
        }

        $this->_setFieldset($attributes, $fieldset);
        foreach ($attributes as $attribute) {
            $rootId = Mage_Catalog_Model_Category::TREE_ROOT_ID;
            /** @var Mage_Eav_Model_Entity_Attribute $attribute */
            if ($attribute->getAttributeCode() == 'url_key') {
                if ((!$this->getCategory()->getId() && $this->getRequest()->getParam('parent', $rootId) == $rootId)
                    || ($this->getCategory()->getParentId() == $rootId)
                ) {
                    $fieldset->removeField('url_key');
                } else {
                    $renderer = $this->getLayout()->createBlock('adminhtml/catalog_form_renderer_attribute_urlkey');
                    if ($renderer instanceof \Maho\Data\Form\Element\Renderer\RendererInterface) {
                        $form->getElement('url_key')->setRenderer($renderer);
                    }
                }
            }
        }

        if ($this->getCategory()->getLevel() == 1) {
            $fieldset->removeField('custom_use_parent_settings');
        } else {
            if ($this->getCategory()->getCustomUseParentSettings()) {
                foreach ($this->getCategory()->getDesignAttributes() as $attribute) {
                    if ($element = $form->getElement($attribute->getAttributeCode())) {
                        $element->setDisabled(true);
                    }
                }
            }
            if ($element = $form->getElement('custom_use_parent_settings')) {
                $element->setData('onchange', 'onCustomUseParentChanged(this)');
            }
        }

        if ($this->getCategory()->hasLockedAttributes()) {
            foreach ($this->getCategory()->getLockedAttributes() as $attribute) {
                if ($element = $form->getElement($attribute)) {
                    $element->setReadonly(true, true);
                }
            }
        }

        if (!$this->getCategory()->getId()) {
            $this->getCategory()->setIncludeInMenu(1);
        }

        $form->addValues($this->getCategory()->getData());

        // Add note to the "Is Dynamic Category" field
        if ($element = $form->getElement('is_dynamic')) {
            $element->setNote(Mage::helper('catalog')->__('When enabled, associated products are automatically recalculated when reindex runs.'));
        }

        // If this is the Dynamic Category attribute group, add the dynamic rules form
        if ($group->getAttributeGroupName() == 'Dynamic Category') {
            // Add dynamic rules fieldset
            $rulesFieldset = $form->addFieldset('dynamic_rules_fieldset', [
                'legend' => Mage::helper('catalog')->__('Dynamic Category Rules'),
                'class'  => 'fieldset-wide',
            ]);

            $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
                ->setTemplate('promo/fieldset.phtml')
                ->setNewChildUrl($this->getUrl('*/*/newConditionHtml/form/dynamic_conditions_fieldset'));
            $rulesFieldset->setRenderer($renderer);

            // Get or create the dynamic rule for this category
            $rule = $this->getDynamicRule();

            $conditionsField = $rulesFieldset->addField('conditions', 'text', [
                'name' => 'rule[conditions]',
                'label' => Mage::helper('catalog')->__('Conditions'),
                'title' => Mage::helper('catalog')->__('Conditions'),
                'required' => false,
            ]);
            $conditionsField->setRule($rule);
            $conditionsField->setRenderer(Mage::getBlockSingleton('rule/conditions'));

            // Set the rules JS loading
            $this->getLayout()->getBlock('head')->setCanLoadRulesJs(true);
        }

        Mage::dispatchEvent('adminhtml_catalog_category_edit_prepare_form', ['form' => $form]);

        $form->setFieldNameSuffix('general');
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Get dynamic rule for this category
     *
     * @throws Exception
     */
    public function getDynamicRule(): Mage_Catalog_Model_Category_Dynamic_Rule
    {
        $category = $this->getCategory();

        if (!$this->hasData('dynamic_rule')) {
            $rule = Mage::getModel('catalog/category_dynamic_rule');

            if ($category && $category->getId()) {
                $collection = Mage::getResourceModel('catalog/category_dynamic_rule_collection')
                    ->addCategoryFilter($category->getId())
                    ->setPageSize(1);

                if ($collection->getSize() > 0) {
                    $rule = $collection->getFirstItem();
                    // Force reload to trigger _afterLoad if conditions not loaded
                    if ($rule->getId() && !$rule->getConditions()->getConditions()) {
                        $rule = Mage::getModel('catalog/category_dynamic_rule')->load($rule->getId());
                    }
                } else {
                    $rule->setCategoryId($category->getId());
                }
            }

            $this->setData('dynamic_rule', $rule);
        }

        return $this->getData('dynamic_rule');
    }

    /**
     * Get form HTML with additional JavaScript for dynamic rules
     */
    #[\Override]
    public function getFormHtml(): string
    {
        $formHtml = parent::getFormHtml();

        // Add rules JavaScript if this is the Dynamic Category group
        $group = $this->getGroup();
        if ($group && $group->getAttributeGroupName() == 'Dynamic Category') {
            $newChildUrl = $this->getUrl('*/*/newConditionHtml/form/dynamic_conditions_fieldset');

            $script = '<script type="text/javascript">
                document.addEventListener("DOMContentLoaded", function() {
                    if (typeof VarienRulesForm !== "undefined") {
                        var conditionsFieldset = document.getElementById("dynamic_conditions_fieldset");
                        if (conditionsFieldset) {
                            window.dynamicCategoryRulesForm = new VarienRulesForm("dynamic_conditions_fieldset", "' . $newChildUrl . '");
                        }
                    }
                });
            </script>';

            return $formHtml . $script;
        }

        return $formHtml;
    }

    /**
     * Retrieve Additional Element Types
     *
     * @return array
     */
    #[\Override]
    protected function _getAdditionalElementTypes()
    {
        return [
            'image' => Mage::getConfig()->getBlockClassName('adminhtml/catalog_category_helper_image'),
            'textarea' => Mage::getConfig()->getBlockClassName('adminhtml/catalog_helper_form_wysiwyg'),
        ];
    }
}
