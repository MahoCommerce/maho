<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Helper_Js extends Mage_Core_Helper_Js
{
    protected $_moduleName = 'Mage_Adminhtml';

    /**
     * Decode serialized grid data
     *
     * Ignores non-numeric array keys
     *
     * '1&2&3&4' will be decoded into [1, 2, 3, 4];
     *
     * otherwise the following format is anticipated:
     * 1=<encoded string>&2=<encoded string>:
     * [
     *   1 => [...],
     *   2 => [...],
     * ]
     *
     * @param   string $encoded
     * @return  array
     */
    public function decodeGridSerializedInput($encoded)
    {
        $isSimplified = !str_contains($encoded, '=');
        $result = [];
        parse_str($encoded, $decoded);
        foreach ($decoded as $key => $value) {
            if (is_numeric($key)) {
                if ($isSimplified) {
                    $result[] = $key;
                } else {
                    $result[$key] = null;
                    parse_str(base64_decode($value), $result[$key]);
                }
            }
        }
        return $result;
    }
}
