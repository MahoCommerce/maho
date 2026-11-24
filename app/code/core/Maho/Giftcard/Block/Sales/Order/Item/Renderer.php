<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * @phpstan-import-type ItemOption from Mage_Catalog_Helper_Product_Configuration
 */
class Maho_Giftcard_Block_Sales_Order_Item_Renderer extends Mage_Sales_Block_Order_Item_Renderer_Default
{
    /**
     * Get formatted option value with date formatting support
     *
     * @param string|list<string>|ItemOption $optionValue
     * @return array{value: string|list<string>, full_view?: string}
     */
    #[\Override]
    public function getFormatedOptionValue($optionValue): array
    {
        // Extract value from option array if needed
        $value = is_array($optionValue) && isset($optionValue['value']) ? $optionValue['value'] : $optionValue;

        // Format ISO dates (YYYY-MM-DD) for display
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                $dateObj = new DateTime($value);
                $formattedDate = Mage::helper('core')->formatDate($dateObj, Mage_Core_Model_Locale::FORMAT_TYPE_SHORT, false);

                // Replace value in the option array before passing to parent
                if (is_array($optionValue)) {
                    $optionValue['value'] = $formattedDate;
                } else {
                    $optionValue = $formattedDate;
                }
            } catch (Exception) {
                // Fall through to default handling
            }
        }

        return parent::getFormatedOptionValue($optionValue);
    }
}
