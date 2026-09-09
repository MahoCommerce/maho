<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

/**
 * @method Mage_Tax_Model_Resource_Sales_Order_Tax _getResource()
 * @method Mage_Tax_Model_Resource_Sales_Order_Tax getResource()
 * @method Mage_Tax_Model_Resource_Sales_Order_Tax_Collection getCollection()
 */

class Mage_Tax_Model_Sales_Order_Tax extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/sales_order_tax');
    }

    public function getOrderId(): ?int
    {
        $value = $this->getData('order_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderId(?int $value): static
    {
        return $this->setData('order_id', $value);
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    public function getPercent(): ?float
    {
        $value = $this->getData('percent');
        return $value === null ? null : (float) $value;
    }

    public function setPercent(?float $value): static
    {
        return $this->setData('percent', $value);
    }

    public function getAmount(): ?float
    {
        $value = $this->getData('amount');
        return $value === null ? null : (float) $value;
    }

    public function setAmount(?float $value): static
    {
        return $this->setData('amount', $value);
    }

    public function getPriority(): ?int
    {
        $value = $this->getData('priority');
        return $value === null ? null : (int) $value;
    }

    public function setPriority(?int $value): static
    {
        return $this->setData('priority', $value);
    }

    public function getPosition(): ?int
    {
        $value = $this->getData('position');
        return $value === null ? null : (int) $value;
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }

    public function getBaseAmount(): ?float
    {
        $value = $this->getData('base_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseAmount(?float $value): static
    {
        return $this->setData('base_amount', $value);
    }

    public function getProcess(): ?int
    {
        $value = $this->getData('process');
        return $value === null ? null : (int) $value;
    }

    public function setProcess(?int $value): static
    {
        return $this->setData('process', $value);
    }

    public function getBaseRealAmount(): ?float
    {
        $value = $this->getData('base_real_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseRealAmount(?float $value): static
    {
        return $this->setData('base_real_amount', $value);
    }

    public function getHidden(): ?int
    {
        $value = $this->getData('hidden');
        return $value === null ? null : (int) $value;
    }

    public function setHidden(?int $value): static
    {
        return $this->setData('hidden', $value);
    }
}
