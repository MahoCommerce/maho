<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

declare(strict_types=1);

class Mage_CatalogIndex_Model_Catalog_Index_Kill_Flag extends Mage_Core_Model_Flag
{
    protected $_flagCode = 'catalogindex_kill';

    /**
     * @return bool
     */
    public function checkIsThisProcess()
    {
        return ($this->getFlagData() == getmypid());
    }
}
