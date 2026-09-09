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
 * @method Mage_Log_Model_Resource_Log _getResource()
 * @method Mage_Log_Model_Resource_Log getResource()
 */

class Mage_Log_Model_Log extends Mage_Core_Model_Abstract
{
    public const XML_LOG_CLEAN_DAYS    = 'system/log/clean_after_day';

    /**
     * Init Resource Model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('log/log');
    }

    /**
     * @return int
     */
    public function getLogCleanTime()
    {
        return Mage::getStoreConfigAsInt(self::XML_LOG_CLEAN_DAYS) * 60 * 60 * 24;
    }

    /**
     * Clean Logs
     *
     * @return $this
     */
    public function clean()
    {
        $this->getResource()->clean($this);
        return $this;
    }

    public function getSessionId(): ?string
    {
        $value = $this->getData('session_id');
        return $value === null ? null : (string) $value;
    }

    public function setSessionId(?string $value): static
    {
        return $this->setData('session_id', $value);
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

    public function getLastUrlId(): ?int
    {
        $value = $this->getData('last_url_id');
        return $value === null ? null : (int) $value;
    }

    public function setLastUrlId(?int $value): static
    {
        return $this->setData('last_url_id', $value);
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
}
