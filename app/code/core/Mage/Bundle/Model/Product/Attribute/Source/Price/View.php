<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Bundle_Model_Product_Attribute_Source_Price_View extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Get all options
     *
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (is_null($this->_options)) {
            $this->_options = [
                [
                    'label' => Mage::helper('bundle')->__('As Low as'),
                    'value' =>  1,
                ],
                [
                    'label' => Mage::helper('bundle')->__('Price Range'),
                    'value' =>  0,
                ],
            ];
        }
        return $this->_options;
    }

    /**
     * Get a text for option value
     *
     * @param string|int $value
     * @return string|false
     */
    #[\Override]
    public function getOptionText($value)
    {
        $options = $this->getAllOptions();
        foreach ($options as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }

    /**
     * Retrieve flat column definition
     *
     * @return array
     */
    #[\Override]
    public function getFlatColums()
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $column = [
            'type'      => Maho\Db\Ddl\Table::TYPE_INTEGER,
            'unsigned'  => false,
            'nullable'  => true,
            'default'   => null,
            'extra'     => null,
            'comment'   => 'Bundle Price View ' . $attributeCode . ' column',
        ];

        return [$attributeCode => $column];
    }

    /**
     * Retrieve Select for update Attribute value in flat table
     *
     * @param   int $store
     * @return  Maho\Db\Select|null
     */
    #[\Override]
    public function getFlatUpdateSelect($store)
    {
        return Mage::getResourceModel('eav/entity_attribute_option')
            ->getFlatUpdateSelect($this->getAttribute(), $store, false);
    }
}
