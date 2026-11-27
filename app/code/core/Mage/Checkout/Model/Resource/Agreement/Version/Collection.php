<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

/**
 * @extends Mage_Core_Model_Resource_Db_Collection_Abstract<Mage_Checkout_Model_Agreement_Version>
 */
class Mage_Checkout_Model_Resource_Agreement_Version_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('checkout/agreement_version');
    }
}
