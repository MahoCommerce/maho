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
 * EAV Attribute add/edit form options tab
 *
 * @category   Mage
 * @package    Mage_Eav
 */
abstract class Mage_Eav_Block_Adminhtml_Attribute_Edit_Options_Abstract extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /** @var ?Mage_Eav_Model_Entity_Attribute $_attribute */
    protected $_attribute = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('eav/attribute/options.phtml');
    }

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
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function getAttributeObject()
    {
        return $this->_attribute ?? Mage::registry('entity_attribute');
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('eav')->__('Manage Label / Options');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('eav')->__('Manage Label / Options');
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
     * Retrieve HTML of delete button, returns new instance for each call so IDs are unique
     *
     * @return string
     */
    public function getDeleteButtonHtml()
    {
        /** @var Mage_Adminhtml_Block_Widget_Button $block */
        $block = $this->getLayout()->createBlock('adminhtml/widget_button');
        $block->setData([
            'label' => Mage::helper('eav')->__('Delete'),
            'class' => 'delete delete-option'
        ]);
        return $block->toHtml();
    }

    /**
     * Retrieve HTML of add button
     *
     * @return string
     */
    public function getAddNewButtonHtml()
    {
        if (!$this->getChild('add_button')) {
            /** @var Mage_Adminhtml_Block_Widget_Button $block */
            $block = $this->getLayout()->createBlock('adminhtml/widget_button');
            $block->setData([
                'label' => Mage::helper('eav')->__('Add Option'),
                'class' => 'add',
                'id'    => 'add_new_option_button'
            ]);
            $this->setChild('add_button', $block);
        }
        return $this->getChildHtml('add_button');
    }

    /**
     * Retrieve stores collection with default store
     *
     * @return Mage_Core_Model_Resource_Store_Collection
     */
    public function getStores()
    {
        $stores = $this->getData('stores');
        if (is_null($stores)) {
            $stores = Mage::getModel('core/store')
                ->getResourceCollection()
                ->setLoadDefault(true)
                ->load();
            $this->setData('stores', $stores);
        }
        return $stores;
    }

    /**
     * Retrieve attribute option values if attribute input type select or multiselect
     *
     * @return array
     */
    public function getOptionValues()
    {
        $values = $this->getData('option_values');
        if (!is_null($values)) {
            return $values;
        }
        $values = [];

        $attributeObject = $this->getAttributeObject();
        $entityTypeCode = $attributeObject->getEntityType()->getEntityTypeCode();
        $inputType = $attributeObject->getFrontendInput();

        // Get global/eav_inputtypes/$inputType/options_panel config.xml node
        $optionsInfo = Mage::helper('eav')->getInputTypeOptionsPanelInfo($entityTypeCode)[$inputType] ?? [];

        if (!empty($optionsInfo)) {
            $defaultValues = explode(',', (string)$attributeObject->getDefaultValue());

            $optionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
                ->setAttributeFilter($attributeObject->getId())
                ->setPositionOrder('desc', true)
                ->load();

            /** @var Mage_Eav_Model_Entity_Attribute_Option $option */
            foreach ($optionCollection as $option) {
                $value = new Varien_Object();
                if (in_array($option->getId(), $defaultValues)) {
                    $value['checked'] = 'checked="checked"';
                } else {
                    $value['checked'] = '';
                }

                $value['intype'] = $optionsInfo['intype'];
                $value['id'] = $option->getId();
                $value['sort_order'] = $option->getSortOrder();
                foreach ($this->getStores() as $store) {
                    $storeValues = $this->getStoreOptionValues($store->getId());
                    if (isset($storeValues[$option->getId()])) {
                        $value['store' . $store->getId()] = Mage::helper('core')->escapeHtml($storeValues[$option->getId()]);
                    } else {
                        $value['store' . $store->getId()] = '';
                    }
                }
                if ($this->isConfigurableSwatchesEnabled()) {
                    $value['swatch'] = $option->getSwatchValue();
                }
                $values[] = $value;
            }
        }

        $this->setData('option_values', $values);
        return $values;
    }

    /**
     * Retrieve frontend labels of attribute for each store
     *
     * @return array
     */
    public function getLabelValues()
    {
        $values = [];
        $frontendLabel = $this->getAttributeObject()->getFrontend()->getLabel();
        if (is_array($frontendLabel)) {
            return $frontendLabel;
        }
        $values[0] = $frontendLabel;
        $storeLabels = $this->getAttributeObject()->getStoreLabels();
        foreach ($this->getStores() as $store) {
            if ($store->getId() != 0) {
                $values[$store->getId()] = $storeLabels[$store->getId()] ?? '';
            }
        }
        return $values;
    }

    /**
     * Retrieve attribute option values for given store id
     *
     * @param int $storeId
     * @return array
     */
    public function getStoreOptionValues($storeId)
    {
        $values = $this->getData('store_option_values_' . $storeId);
        if (is_null($values)) {
            $values = [];
            $valuesCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
                ->setAttributeFilter($this->getAttributeObject()->getId())
                ->setStoreFilter($storeId, false)
                ->load();
            /** @var Mage_Eav_Model_Entity_Attribute_Option $item */
            foreach ($valuesCollection as $item) {
                $values[$item->getId()] = $item->getValue();
            }
            $this->setData('store_option_values_' . $storeId, $values);
        }
        return $values;
    }

    /**
     * Check if configurable swatches module is enabled and attribute is swatch type
     */
    public function isConfigurableSwatchesEnabled(): bool
    {
        return $this->isModuleEnabled('Mage_ConfigurableSwatches')
            && Mage::helper('configurableswatches')->attrIsSwatchType($this->getAttributeObject());
    }
}
