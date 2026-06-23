<?php

/**
 * Source model listing product attributes for structured-data mapping.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Model_System_Config_Source_Product_Attribute
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => Mage::helper('structureddata')->__('-- None --')]];

        /** @var Mage_Catalog_Model_Resource_Product_Attribute_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_attribute_collection');
        $collection->addVisibleFilter()
            ->addFieldToFilter('frontend_input', ['in' => ['text', 'select']])
            ->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
            $label = $attribute->getFrontendLabel() ?: $attribute->getAttributeCode();
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => sprintf('%s (%s)', $label, $attribute->getAttributeCode()),
            ];
        }

        return $options;
    }
}
