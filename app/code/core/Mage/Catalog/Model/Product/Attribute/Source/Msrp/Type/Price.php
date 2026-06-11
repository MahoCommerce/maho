<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Price extends Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type
{
    /**
     * Get value from the store configuration settings
     */
    public const TYPE_USE_CONFIG = '4';

    /**
     * Get all options
     *
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = parent::getAllOptions();
            $this->_options[] = [
                'label' => Mage::helper('catalog')->__('Use config'),
                'value' => self::TYPE_USE_CONFIG,
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
        $attributeType = $this->getAttribute()->getBackendType();
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $helper = Mage::getResourceHelper('eav');
        $column = [
            'type'      => $helper->getDdlTypeByColumnType($attributeType),
            'unsigned'  => false,
            'nullable'  => true,
            'default'   => null,
            'extra'     => null,
        ];

        return [$attributeCode => $column];
    }

    /**
     * Retrieve select for flat attribute update
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
