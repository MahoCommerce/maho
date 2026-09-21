<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

/**
 * @method Mage_Reports_Model_Resource_Event_Type _getResource()
 * @method Mage_Reports_Model_Resource_Event_Type getResource()
 */

class Mage_Reports_Model_Event_Type extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('reports/event_type');
    }

    public function getEventName(): ?string
    {
        return $this->getData('event_name');
    }

    public function setEventName(?string $value): static
    {
        return $this->setData('event_name', $value);
    }

    public function getCustomerLogin(): ?int
    {
        return $this->getData('customer_login');
    }

    public function setCustomerLogin(?int $value): static
    {
        return $this->setData('customer_login', $value);
    }
}
