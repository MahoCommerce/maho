<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

/**
 * @method Mage_Log_Model_Resource_Customer _getResource()
 * @method Mage_Log_Model_Resource_Customer getResource()
 */
class Mage_Log_Model_Customer extends Mage_Core_Model_Abstract
{
    /**
     * Define resource model
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('log/customer');
    }

    /**
     * Load last log by customer id
     *
     * @param Mage_Log_Model_Customer|int $customer
     * @return $this
     */
    public function loadByCustomer($customer)
    {
        if ($customer instanceof Mage_Customer_Model_Customer) {
            $customer = $customer->getId();
        }

        return $this->load($customer, 'customer_id');
    }

    /**
     * Return last login at in Unix time format
     *
     * @return int|null
     */
    public function getLoginAtTimestamp()
    {
        $loginAt = $this->getLoginAt();
        if ($loginAt) {
            return strtotime($loginAt);
        }

        return null;
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getLoginAt(): ?string
    {
        $value = $this->getData('login_at');
        return $value === null ? null : (string) $value;
    }

    public function setLoginAt(?string $value): static
    {
        return $this->setData('login_at', $value);
    }

    public function getLogoutAt(): ?string
    {
        $value = $this->getData('logout_at');
        return $value === null ? null : (string) $value;
    }

    public function setLogoutAt(?string $value): static
    {
        return $this->setData('logout_at', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getVisitorId(): ?int
    {
        $value = $this->getData('visitor_id');
        return $value === null ? null : (int) $value;
    }

    public function setVisitorId(?int $value): static
    {
        return $this->setData('visitor_id', $value);
    }

}
