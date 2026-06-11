<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

class Mage_ImportExport_Model_Import_Proxy_Product extends Mage_Catalog_Model_Product
{
    /**
     * DO NOT Initialize resources.
     */
    #[\Override]
    protected function _construct() {}

    /**
     * Retrieve object id
     *
     * @return int
     */
    #[\Override]
    public function getId()
    {
        return $this->_getData('id');
    }
}
