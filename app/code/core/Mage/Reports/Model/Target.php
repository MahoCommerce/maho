<?php

/**
 * The target of a metric for one month and one store view, or for every store view with store 0.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Target extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/target');
    }

    public function getDataset(): ?string
    {
        $value = $this->getData('dataset');
        return $value === null ? null : (string) $value;
    }

    public function setDataset(?string $value): static
    {
        return $this->setData('dataset', $value);
    }

    public function getMetric(): ?string
    {
        $value = $this->getData('metric');
        return $value === null ? null : (string) $value;
    }

    public function setMetric(?string $value): static
    {
        return $this->setData('metric', $value);
    }

    public function getMonth(): ?string
    {
        $value = $this->getData('month');
        return $value === null ? null : (string) $value;
    }

    public function setMonth(?string $value): static
    {
        return $this->setData('month', $value);
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

    public function getValue(): ?float
    {
        $value = $this->getData('value');
        return $value === null ? null : (float) $value;
    }

    public function setValue(?float $value): static
    {
        return $this->setData('value', $value);
    }
}
