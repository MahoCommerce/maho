<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Category dynamic rules tab
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Category_Tab_Dynamic 
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Load Wysiwyg on demand and Prepare layout
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (Mage::getSingleton('cms/wysiwyg_config')->isEnabled()) {
            $this->getLayout()->getBlock('head')->setCanLoadTinyMce(true);
        }
        
        // Enable rules JavaScript for dynamic category conditions
        $this->getLayout()->getBlock('head')->setCanLoadRulesJs(true);
        
        return $this;
    }

    /**
     * Prepare form before rendering HTML
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('dynamic_');

        $category = $this->getCategory();

        $fieldset = $form->addFieldset('dynamic_fieldset', [
            'legend' => Mage::helper('catalog')->__('Dynamic Category Settings'),
            'class'  => 'fieldset-wide'
        ]);

        $fieldset->addField('is_dynamic', 'select', [
            'name'     => 'is_dynamic',
            'label'    => Mage::helper('catalog')->__('Is Dynamic Category'),
            'title'    => Mage::helper('catalog')->__('Is Dynamic Category'),
            'values'   => Mage::getSingleton('adminhtml/system_config_source_yesno')->toOptionArray(),
            'onchange' => 'toggleDynamicOptions(this.value)'
        ]);

        // Rules fieldset
        $rulesFieldset = $form->addFieldset('dynamic_rules_fieldset', [
            'legend' => Mage::helper('catalog')->__('Dynamic Category Rules'),
            'class'  => 'fieldset-wide'
        ]);

        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl('*/*/newConditionHtml/form/dynamic_conditions_fieldset'));

        $rulesFieldset->setRenderer($renderer);

        $rule = $this->getRule();
        
        $rulesFieldset->addField('conditions', 'text', [
            'name' => 'rule[conditions]',
            'label' => Mage::helper('catalog')->__('Conditions'),
            'title' => Mage::helper('catalog')->__('Conditions'),
            'required' => false,
        ])->setRule($rule)->setRenderer(Mage::getBlockSingleton('rule/conditions'));

        $form->setValues($category->getData());

        // Set form script
        $form->setFieldNameSuffix('category');
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Retrieve category object
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        return Mage::registry('current_category');
    }

    /**
     * Get dynamic rule for this category
     *
     * @return Mage_Catalog_Model_Category_Dynamic_Rule
     * @throws Exception
     */
    public function getRule()
    {
        $category = $this->getCategory();
        
        if (!$this->hasData('rule')) {
            $rule = Mage::getModel('catalog/category_dynamic_rule');
            
            if ($category && $category->getId()) {
                $collection = Mage::getModel('catalog/category_dynamic_rule')->getCollection()
                    ->addCategoryFilter($category->getId())
                    ->setPageSize(1);
                
                if ($collection->getSize() > 0) {
                    $rule = $collection->getFirstItem();
                } else {
                    $rule->setCategoryId($category->getId());
                }
            }
            
            $this->setData('rule', $rule);
        }
        
        return $this->getData('rule');
    }

    /**
     * Get tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('catalog')->__('Dynamic Category');
    }

    /**
     * Get tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('catalog')->__('Dynamic Category Rules');
    }

    /**
     * Check if tab can be displayed
     *
     * @return bool
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Check if tab is hidden
     *
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Get form HTML with additional JavaScript
     *
     * @return string
     */
    public function getFormHtml()
    {
        $formHtml = parent::getFormHtml();
        
        $newChildUrl = $this->getUrl('*/*/newConditionHtml/form/dynamic_conditions_fieldset');
        
        $script = '<script type="text/javascript">
            function toggleDynamicOptions(value) {
                var conditionsFieldset = $("dynamic_conditions_fieldset");
                if (conditionsFieldset) {
                    if (value == "1") {
                        conditionsFieldset.show();
                    } else {
                        conditionsFieldset.hide();
                    }
                }
            }
            
            // Initialize on page load
            document.observe("dom:loaded", function() {
                var isDynamicField = $("dynamic_is_dynamic");
                if (isDynamicField) {
                    toggleDynamicOptions(isDynamicField.value);
                }
                
                // Initialize VarienRulesForm for dynamic category conditions
                if (typeof VarienRulesForm !== "undefined") {
                    var conditionsFieldset = $("dynamic_conditions_fieldset");
                    if (conditionsFieldset) {
                        window.dynamicCategoryRulesForm = new VarienRulesForm("dynamic_conditions_fieldset", "' . $newChildUrl . '");
                    }
                }
            });
        </script>';
        
        return $formHtml . $script;
    }
}