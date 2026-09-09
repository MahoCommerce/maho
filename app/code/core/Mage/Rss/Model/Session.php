<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rss
 */

declare(strict_types=1);

class Mage_Rss_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('rss');
    }

    /**
     * @return bool
     */
    public function isAdminLoggedIn()
    {
        return $this->getAdmin() && $this->getAdmin()->getId();
    }

    /**
     * @return bool
     */
    public function isCustomerLoggedIn()
    {
        return $this->getCustomer() && $this->getCustomer()->getId();
    }

    public function getAdmin(bool $clear = false): ?Mage_Admin_Model_User
    {
        return $this->getData('admin', $clear ?: null);
    }

    public function setAdmin(?Mage_Admin_Model_User $value): static
    {
        return $this->setData('admin', $value);
    }

    public function getCustomer(bool $clear = false): ?Mage_Customer_Model_Customer
    {
        return $this->getData('customer', $clear ?: null);
    }
}
