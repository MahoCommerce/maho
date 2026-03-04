<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ShippingBridge
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ShippingBridge_Model_Source_ProductAttribute
{
    protected const EXCLUDED_CODES = ['sku', 'name', 'price', 'weight'];

    public function toOptionArray(): array
    {
        /** @var Mage_Catalog_Model_Resource_Product_Attribute_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_attribute_collection');
        $collection->addVisibleFilter();
        $options = [];
        foreach ($collection as $attribute) {
            $code = $attribute->getAttributeCode();
            $label = $attribute->getFrontendLabel();

            if (!$label || in_array($code, self::EXCLUDED_CODES, true)) {
                continue;
            }

            $options[] = [
                'value' => $code,
                'label' => "{$label} ({$code})",
            ];
        }

        usort($options, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));

        return $options;
    }
}
