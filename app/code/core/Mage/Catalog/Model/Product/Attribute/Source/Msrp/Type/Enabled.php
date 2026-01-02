<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Enabled extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Enable MAP
     */
    public const MSRP_ENABLE_YES = 1;

    /**
     * Disable MAP
     */
    public const MSRP_ENABLE_NO = 0;

    /**
     * Get value from the store configuration settings
     */
    public const MSRP_ENABLE_USE_CONFIG = 2;

    /**
     * Retrieve all attribute options
     *
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = [
                [
                    'label' => Mage::helper('catalog')->__('Yes'),
                    'value' => self::MSRP_ENABLE_YES,
                ],
                [
                    'label' => Mage::helper('catalog')->__('No'),
                    'value' => self::MSRP_ENABLE_NO,
                ],
                [
                    'label' => Mage::helper('catalog')->__('Use config'),
                    'value' => self::MSRP_ENABLE_USE_CONFIG,
                ],
            ];
        }
        return $this->_options;
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
            'type'      => Maho\Db\Ddl\Table::TYPE_SMALLINT,
            'length'    => 1,
            'unsigned'  => false,
            'nullable'  => true,
            'default'   => null,
            'extra'     => null,
            'comment'   => $attributeCode . ' column',
        ];

        return [$attributeCode => $column];
    }

    /**
     * Retrieve Select For Flat Attribute update
     *
     * @param int $store
     * @return Maho\Db\Select|null
     */
    #[\Override]
    public function getFlatUpdateSelect($store)
    {
        return Mage::getResourceModel('eav/entity_attribute')
            ->getFlatUpdateSelect($this->getAttribute(), $store);
    }
}
