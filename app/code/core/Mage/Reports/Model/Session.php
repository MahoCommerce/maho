<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

/**
 * @method $this unsData(string $value)
 */

class Mage_Reports_Model_Session extends Mage_Core_Model_Session_Abstract
{
    /**
     * Initialize session name space
     */
    public function __construct()
    {
        $this->init('reports');
    }

    #[\Override]
    protected function getDisposableKeys(): array
    {
        return ['product_index_viewed_count', 'product_index_compared_count'];
    }
}
