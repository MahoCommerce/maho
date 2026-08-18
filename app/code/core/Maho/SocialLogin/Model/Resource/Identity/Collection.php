<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Model_Resource_Identity_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('sociallogin/identity');
    }

    public function addCustomerFilter(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }
}
