<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ConfigurableSwatches
 */

class Mage_ConfigurableSwatches_Model_System_Config_Source_Catalog_Product_Configattribute
{
    /**
     * Attributes array
     *
     * @var null|array
     */
    protected $_attributes = null;

    public function toOptionArray(): array
    {
        if (is_null($this->_attributes)) {
            $attrCollection = Mage::getResourceModel('catalog/product_attribute_collection')
                ->addVisibleFilter()
                ->setFrontendInputTypeFilter('select')
                ->addFieldToFilter('additional_table.is_configurable', 1)
                ->addFieldToFilter('additional_table.is_visible', 1)
                ->addFieldToFilter('main_table.is_user_defined', 1)
                ->setOrder('frontend_label', \Maho\Data\Collection::SORT_ORDER_ASC);

            $this->_attributes = [];
            /** @var Mage_Eav_Model_Attribute $attribute */
            foreach ($attrCollection as $attribute) {
                $this->_attributes[] = [
                    'label' => $attribute->getFrontendLabel(),
                    'value' => $attribute->getId(),
                ];
            }
        }
        return $this->_attributes;
    }
}
