<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

/**
 * DataFlow Import Model
 *
 * @package    Mage_Dataflow
 *
 * @method Mage_Dataflow_Model_Resource_Import _getResource()
 * @method Mage_Dataflow_Model_Resource_Import getResource()
 */

class Mage_Dataflow_Model_Import extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/import');
    }

    public function getSessionId(): ?int
    {
        $value = $this->getData('session_id');
        return $value === null ? null : (int) $value;
    }

    public function setSessionId(?int $value): static
    {
        return $this->setData('session_id', $value);
    }

    public function getSerialNumber(): ?int
    {
        $value = $this->getData('serial_number');
        return $value === null ? null : (int) $value;
    }

    public function setSerialNumber(?int $value): static
    {
        return $this->setData('serial_number', $value);
    }

    public function getValue(): ?string
    {
        $value = $this->getData('value');
        return $value === null ? null : (string) $value;
    }

    public function setValue(?string $value): static
    {
        return $this->setData('value', $value);
    }

    public function getStatus(): ?int
    {
        $value = $this->getData('status');
        return $value === null ? null : (int) $value;
    }

    public function setStatus(?int $value): static
    {
        return $this->setData('status', $value);
    }
}
