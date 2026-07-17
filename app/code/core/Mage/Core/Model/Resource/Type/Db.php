<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

abstract class Mage_Core_Model_Resource_Type_Db extends Mage_Core_Model_Resource_Type_Abstract
{
    public function __construct()
    {
        $this->_entityClass = 'Mage_Core_Model_Resource_Entity_Table';
    }
}
