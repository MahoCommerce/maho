<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Eav_Model_Entity_Attribute_Source_Abstract implements Mage_Eav_Model_Entity_Attribute_Source_Interface
{
    /**
     * Reference to the attribute instance
     *
     * @var Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected $_attribute;

    /**
     * Options array
     *
     * @var array|null
     */
    protected $_options = null;

    /**
     * Set attribute instance
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @return $this
     */
    public function setAttribute($attribute)
    {
        $this->_attribute = $attribute;
        return $this;
    }

    /**
     * Get attribute instance
     *
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function getAttribute()
    {
        return $this->_attribute;
    }

    /**
     * Get a text for option value
     *
     * @param  string|int $value
     * @return string|bool
     */
    #[\Override]
    public function getOptionText($value)
    {
        $options = $this->getAllOptions();
        // Fixed for tax_class_id and custom_design
        if (count($options)) {
            foreach ($options as $option) {
                if (isset($option['value']) && $option['value'] == $value) {
                    return $option['label'] ?? $option['value'];
                }
            } // End
        }
        return $options[$value] ?? false;
    }

    /**
     * @param string $value
     * @return string|null
     */
    public function getOptionId($value)
    {
        $bcWarning = false;
        foreach ($this->getAllOptions() as $option) {
            if (strcasecmp($option['label'], $value) == 0) {
                return $option['value'];
            }
            if ($option['value'] == $value) {
                $bcWarning = true;
            }
        }
        if ($bcWarning) {
            Mage::log(
                'Mage_Eav_Model_Entity_Attribute_Source_Abstract::getOptionId() no longer accepts option_id as param',
                Mage::LOG_WARN,
            );
        }
        return null;
    }

    /**
     * Add Value Sort To Collection Select
     *
     * @param Mage_Eav_Model_Entity_Collection_Abstract $collection
     * @param string $dir direction
     * @return $this
     */
    public function addValueSortToCollection($collection, $dir = Varien_Data_Collection::SORT_ORDER_DESC)
    {
        return $this;
    }

    /**
     * Retrieve flat column definition
     *
     * @return array
     */
    public function getFlatColums()
    {
        return [];
    }

    /**
     * Retrieve Indexes(s) for Flat
     *
     * @return array
     */
    public function getFlatIndexes()
    {
        return [];
    }

    /**
     * Retrieve Select For Flat Attribute update
     *
     * @param int $store
     * @return Varien_Db_Select|null
     */
    public function getFlatUpdateSelect($store)
    {
        return null;
    }

    /**
     * Get a text for index option value
     *
     * @param string|int $value
     * @return string|bool
     */
    public function getIndexOptionText($value)
    {
        return $this->getOptionText($value);
    }
}
