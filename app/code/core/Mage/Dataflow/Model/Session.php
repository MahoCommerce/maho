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
 * DataFlow Session Model
 *
 * @package    Mage_Dataflow
 *
 * @method Mage_Dataflow_Model_Resource_Session _getResource()
 * @method Mage_Dataflow_Model_Resource_Session getResource()
 */

class Mage_Dataflow_Model_Session extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/session');
    }

    public function getUserId(): ?int
    {
        $value = $this->getData('user_id');
        return $value === null ? null : (int) $value;
    }

    public function setUserId(?int $value): static
    {
        return $this->setData('user_id', $value);
    }

    public function getCreatedDate(): ?string
    {
        $value = $this->getData('created_date');
        return $value === null ? null : (string) $value;
    }

    public function setCreatedDate(?string $value): static
    {
        return $this->setData('created_date', $value);
    }

    public function getFile(): ?string
    {
        $value = $this->getData('file');
        return $value === null ? null : (string) $value;
    }

    public function setFile(?string $value): static
    {
        return $this->setData('file', $value);
    }

    public function getType(): ?string
    {
        $value = $this->getData('type');
        return $value === null ? null : (string) $value;
    }

    public function setType(?string $value): static
    {
        return $this->setData('type', $value);
    }

    public function getDirection(): ?string
    {
        $value = $this->getData('direction');
        return $value === null ? null : (string) $value;
    }

    public function setDirection(?string $value): static
    {
        return $this->setData('direction', $value);
    }

    public function getComment(): ?string
    {
        $value = $this->getData('comment');
        return $value === null ? null : (string) $value;
    }

    public function setComment(?string $value): static
    {
        return $this->setData('comment', $value);
    }
}
