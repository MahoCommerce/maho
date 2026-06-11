<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
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
