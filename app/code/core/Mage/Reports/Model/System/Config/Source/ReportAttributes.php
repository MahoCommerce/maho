<?php

/**
 * The product attributes that the report explorer can group the order items by: the attributes with a list of options.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_System_Config_Source_ReportAttributes
{
    /** @return list<array{value: string, label: string}> */
    public function toOptionArray(): array
    {
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addFieldToFilter('frontend_input', 'select')
            ->addFieldToFilter('backend_type', 'int')
            ->setOrder('frontend_label', 'ASC');
        $options = [];
        foreach ($attributes as $attribute) {
            $options[] = [
                'value' => (string) $attribute->getAttributeCode(),
                'label' => (string) ($attribute->getFrontendLabel() ?: $attribute->getAttributeCode()),
            ];
        }
        return $options;
    }
}
