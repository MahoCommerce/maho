<?php

/**
 * Maho
 *
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Tax_Model_Class_Source_Product extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * @param bool $withEmpty
     * @return array
     */
    #[\Override]
    public function getAllOptions($withEmpty = false)
    {
        if (is_null($this->_options)) {
            $this->_options = Mage::getResourceModel('tax/class_collection')
                ->addFieldToFilter('class_type', Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
                ->load()
                ->toOptionArray();
        }

        $options = $this->_options;
        array_unshift($options, ['value' => '0', 'label' => Mage::helper('tax')->__('None')]);
        if ($withEmpty) {
            array_unshift($options, ['value' => '', 'label' => Mage::helper('tax')->__('-- Please Select --')]);
        }
        return $options;
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
        $options = $this->getAllOptions(false);

        foreach ($options as $item) {
            if ($item['value'] == $value) {
                return $item['label'];
            }
        }
        return false;
    }

    public function toOptionArray(): array
    {
        return $this->getAllOptions();
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
            'unsigned'  => true,
            'nullable'  => true,
            'default'   => null,
            'extra'     => null,
            'comment'   => $attributeCode . ' tax column',
        ];

        return [$attributeCode => $column];
    }

    /**
     * Retrieve Select for update attribute value in flat table
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
