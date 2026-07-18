<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

/**
 * @method Mage_Admin_Model_Variable getItemById(int $value)
 * @method Mage_Admin_Model_Variable[] getItems()
 */

class Mage_Admin_Model_Resource_Variable_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Define resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/variable');
    }
}
