<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_Model_Customer_Attribute_Source_Store extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    /**
     * Retrieve Full Option values array
     *
     * @param bool $withEmpty       Argument has no effect, included for PHP 7.2 method signature compatibility
     * @param bool $defaultValues   Argument has no effect, included for PHP 7.2 method signature compatibility
     * @return array
     */
    #[\Override]
    public function getAllOptions($withEmpty = true, $defaultValues = false)
    {
        if (!$this->_options) {
            $collection = Mage::getResourceModel('core/store_collection');
            if ($this->getAttribute()->getAttributeCode() == 'store_id') {
                $collection->setWithoutDefaultFilter();
            }
            $this->_options = Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm();
            if ($this->getAttribute()->getAttributeCode() == 'created_in') {
                array_unshift($this->_options, ['value' => '0', 'label' => Mage::helper('customer')->__('Admin')]);
            }
        }
        return $this->_options;
    }

    #[\Override]
    public function getOptionText($value)
    {
        if (!$value) {
            $value = '0';
        }
        $isMultiple = false;
        if (strpos($value, ',')) {
            $isMultiple = true;
            $value = explode(',', $value);
        }

        if (!$this->_options) {
            $collection = Mage::getResourceModel('core/store_collection');
            if ($this->getAttribute()->getAttributeCode() == 'store_id') {
                $collection->setWithoutDefaultFilter();
            }
            $this->_options = $collection->load()->toOptionArray();
            if ($this->getAttribute()->getAttributeCode() == 'created_in') {
                array_unshift($this->_options, ['value' => '0', 'label' => Mage::helper('customer')->__('Admin')]);
            }
        }

        if ($isMultiple) {
            $values = [];
            foreach ($value as $val) {
                $values[] = $this->_options[$val];
            }
            return $values;
        }
        return $this->_options[$value];
    }
}
