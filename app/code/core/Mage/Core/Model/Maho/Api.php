<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/**
 * Maho info API
 */

class Mage_Core_Model_Maho_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve information about the current Maho installation
     */
    public function info(): array
    {
        $result = [];
        $result['maho_version'] = Mage::getVersion();

        return $result;
    }
}
