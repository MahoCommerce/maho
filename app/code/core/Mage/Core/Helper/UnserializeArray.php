<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Helper_UnserializeArray extends Mage_Core_Helper_Abstract
{
    /**
     * @param mixed $str  Serialized string, JSON string, or already-decoded value (passed through)
     * @return mixed      Decoded array for serialized/JSON input; input unchanged for non-string input
     * @throws Exception  When string input cannot be decoded
     * @SuppressWarnings("PHPMD.ErrorControlOperator")
     */
    public function unserialize($str)
    {
        $str ??= '';

        // Pass through if the value has already been decoded upstream
        // (e.g., a previous _afterLoad pass did setExtra(array), and the
        // model still holds the array on a re-load). json_validate()
        // requires string — a non-string fatals with TypeError otherwise.
        if (!is_string($str)) {
            return $str;
        }

        if (json_validate($str)) {
            return Mage::helper('core')->jsonDecode($str);
        }

        try {
            $result = @unserialize($str, ['allowed_classes' => false]);
            if ($result === false && $str !== serialize(false)) {
                throw new Exception('Error unserializing data.');
            }
            return $result;
        } catch (Error $e) {
            throw new Exception('Error unserializing data: ' . $e->getMessage(), 0, $e);
        }
    }
}
