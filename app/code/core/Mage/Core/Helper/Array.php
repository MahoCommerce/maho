<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Helper_Array extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Core';

    /**
     * Merge array recursive without overwrite keys.
     * PHP function array_merge_recursive merge array
     * with overwrite num keys
     *
     * @return array
     */
    public function mergeRecursiveWithoutOverwriteNumKeys(array $baseArray, array $mergeArray)
    {
        foreach ($mergeArray as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists($key, $baseArray)) {
                    $baseArray[$key] = $this->mergeRecursiveWithoutOverwriteNumKeys($baseArray[$key], $value);
                } else {
                    $baseArray[$key] = $value;
                }
            } else {
                if ($key) {
                    $baseArray[$key] = $value;
                } else {
                    $baseArray[] = $value;
                }
            }
        }

        return $baseArray;
    }
}
