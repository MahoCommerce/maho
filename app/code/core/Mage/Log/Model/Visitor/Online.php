<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

declare(strict_types=1);

/**
 * @method Mage_Log_Model_Resource_Visitor_Online _getResource()
 * @method Mage_Log_Model_Resource_Visitor_Online getResource()
 */

class Mage_Log_Model_Visitor_Online extends Mage_Core_Model_Abstract
{
    public const XML_PATH_ONLINE_INTERVAL      = 'customer/online_customers/online_minutes_interval';
    public const XML_PATH_UPDATE_FREQUENCY     = 'log/visitor/online_update_frequency';

    #[\Override]
    protected function _construct()
    {
        $this->_init('log/visitor_online');
    }

    /**
     * Prepare Online visitors collection
     *
     * @return $this
     */
    public function prepare()
    {
        $this->_getResource()->prepare($this);
        return $this;
    }

    /**
     * Retrieve last prepare at timestamp
     *
     * @return string|false
     */
    public function getPrepareAt()
    {
        return Mage::app()->loadCache('log_visitor_online_prepare_at');
    }

    /**
     * Set Prepare at timestamp (if time is null, set current timestamp)
     *
     * @param int $time
     * @return $this
     */
    public function setPrepareAt($time = null)
    {
        if (is_null($time)) {
            $time = time();
        }
        Mage::app()->saveCache($time, 'log_visitor_online_prepare_at');
        return $this;
    }

    /**
     * Retrieve data update Frequency in second
     *
     * @return int
     */
    public function getUpdateFrequency()
    {
        return Mage::getStoreConfig(self::XML_PATH_UPDATE_FREQUENCY);
    }

    /**
     * Retrieve Online Interval (in minutes)
     *
     * @return int
     */
    public function getOnlineInterval()
    {
        $value = Mage::getStoreConfigAsInt(self::XML_PATH_ONLINE_INTERVAL);
        if (!$value) {
            $value = Mage_Log_Model_Visitor::DEFAULT_ONLINE_MINUTES_INTERVAL;
        }
        return $value;
    }

    public function getVisitorType(): ?string
    {
        $value = $this->getData('visitor_type');
        return $value === null ? null : (string) $value;
    }

    public function setVisitorType(?string $value): static
    {
        return $this->setData('visitor_type', $value);
    }

    public function getRemoteAddr(): ?string
    {
        $value = $this->getData('remote_addr');
        return $value === null ? null : (string) $value;
    }

    public function setRemoteAddr(?string $value): static
    {
        return $this->setData('remote_addr', $value);
    }

    public function getFirstVisitAt(): ?string
    {
        $value = $this->getData('first_visit_at');
        return $value === null ? null : (string) $value;
    }

    public function setFirstVisitAt(?string $value): static
    {
        return $this->setData('first_visit_at', $value);
    }

    public function getLastVisitAt(): ?string
    {
        $value = $this->getData('last_visit_at');
        return $value === null ? null : (string) $value;
    }

    public function setLastVisitAt(?string $value): static
    {
        return $this->setData('last_visit_at', $value);
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

    public function getLastUrl(): ?string
    {
        $value = $this->getData('last_url');
        return $value === null ? null : (string) $value;
    }

    public function setLastUrl(?string $value): static
    {
        return $this->setData('last_url', $value);
    }
}
