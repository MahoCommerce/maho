<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2016-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Model_Entity_Attribute_Source_Boolean extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Option values
     */
    public const VALUE_YES = 1;
    public const VALUE_NO = 0;

    /**
     * Retrieve all options array
     *
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (is_null($this->_options)) {
            $this->_options = [
                [
                    'label' => Mage::helper('eav')->__('Yes'),
                    'value' => self::VALUE_YES
                ],
                [
                    'label' => Mage::helper('eav')->__('No'),
                    'value' => self::VALUE_NO
                ],
            ];
        }
        return $this->_options;
    }

    /**
     * Retrieve option array
     *
     * @return array
     */
    public function getOptionArray()
    {
        $_options = [];
        foreach ($this->getAllOptions() as $option) {
            $_options[$option['value']] = $option['label'];
        }
        return $_options;
    }

    /**
     * Retrieve option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getOptionArray();
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
            'unsigned'  => false,
            'default'   => null,
            'extra'     => null
        ];

        if (Mage::helper('core')->useDbCompatibleMode()) {
            $column['type']     = 'tinyint(1)';
            $column['is_null']  = true;
        } else {
            $column['type']     = Varien_Db_Ddl_Table::TYPE_SMALLINT;
            $column['length']   = 1;
            $column['nullable'] = true;
            $column['comment']  = $attributeCode . ' column';
        }

        return [$attributeCode => $column];
    }

    /**
     * Retrieve Indexes(s) for Flat
     *
     * @return array
     */
    #[\Override]
    public function getFlatIndexes()
    {
        $indexes = [];

        $index = 'IDX_' . strtoupper($this->getAttribute()->getAttributeCode());
        $indexes[$index] = [
            'type'      => 'index',
            'fields'    => [$this->getAttribute()->getAttributeCode()]
        ];

        return $indexes;
    }

    /**
     * Retrieve Select For Flat Attribute update
     *
     * @param int $store
     * @return Varien_Db_Select|null
     */
    #[\Override]
    public function getFlatUpdateSelect($store)
    {
        return Mage::getResourceModel('eav/entity_attribute')
            ->getFlatUpdateSelect($this->getAttribute(), $store);
    }

    /**
     * Get a text for index option value
     *
     * @param  string|int $value
     * @return string|bool
     */
    #[\Override]
    public function getIndexOptionText($value)
    {
        switch ($value) {
            case self::VALUE_YES:
                return 'Yes';
            case self::VALUE_NO:
                return 'No';
        }

        return parent::getIndexOptionText($value);
    }
}
