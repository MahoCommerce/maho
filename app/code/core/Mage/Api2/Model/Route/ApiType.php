<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

class Mage_Api2_Model_Route_ApiType extends Mage_Api2_Model_Route_Abstract implements Mage_Api2_Model_Route_Interface
{
    /**
     * Matches a Request with parts defined by a map. Assigns and
     * returns an array of variables on a successful match.
     *
     * @param string|Mage_Api2_Model_Request $path Path string or Request object to match
     * @param bool $partial OPTIONAL Partial path matching (default: false)
     * @return array<string, mixed>|false An array of assigned values or false on a mismatch
     */
    #[\Override]
    public function match($path, bool $partial = false): array|false
    {
        // First try normal PATH_INFO matching
        $result = parent::match($path, $partial);

        // If no match and 'type' query parameter exists from Request object, use it as fallback
        if (!$result && $path instanceof Mage_Api2_Model_Request) {
            $apiType = $path->getParam('type');
            if ($apiType && in_array($apiType, Mage_Api2_Model_Server::getApiTypes())) {
                // When using query parameter, check if path starts with /api_type and trim it
                $pathInfo = $path->getPathInfo();
                if (str_starts_with($pathInfo, '/' . $apiType)) {
                    $this->setMatchedPath($apiType);
                } else {
                    $this->setMatchedPath('');
                }
                // Merge with defaults
                return ['api_type' => $apiType] + $this->getDefaults();
            }
        }

        return $result;
    }
}
