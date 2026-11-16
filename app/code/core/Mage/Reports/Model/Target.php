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

    /** The first day of the month of the target, or null when the month is not a real YYYY-MM month. */
    public function getMonthStart(): ?DateTimeImmutable
    {
        $month = (string) $this->getMonth();
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', substr($month, 0, 7) . '-01');
        if ($start === false || !preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $month) || $start->format('Y-m') !== substr($month, 0, 7)) {
            return null;
        }
        return $start;
    }

    /** @return array<string, string>|true */
    public function validate(): array|true
    {
        $helper = Mage::helper('reports');
        $errors = [];
        if ($this->getMonthStart() === null) {
            $errors['month'] = $helper->__('The month must be YYYY-MM.');
        }
        if ($this->getValue() === null || !is_finite($this->getValue())) {
            $errors['value'] = $helper->__('The target value is required.');
        }
        return $errors === [] ? true : $errors;
    }
}
