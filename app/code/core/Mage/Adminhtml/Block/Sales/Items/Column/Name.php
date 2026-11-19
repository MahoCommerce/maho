<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Sales_Items_Column_Name extends Mage_Adminhtml_Block_Sales_Items_Column_Default
{
    /**
     * Truncate the value, escape the HTML and add line breaks. Both parts are safe HTML.
     *
     * Escaping does not encode an entity again, so a custom option value that the order already keeps escaped does
     * not change.
     *
     * @return array{value: string, remainder: string}
     */
    public function getFormattedOption(?string $value): array
    {
        $remainder = '';
        $value = (string) Mage::helper('core/string')->truncate($value, 55, '', $remainder);
        return [
            'value' => nl2br((string) $this->escapeHtml($value)),
            'remainder' => nl2br((string) $this->escapeHtml((string) $remainder)),
        ];
    }
}
