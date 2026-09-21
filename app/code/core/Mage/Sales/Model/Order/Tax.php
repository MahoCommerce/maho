<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * @method Mage_Sales_Model_Resource_Order_Tax _getResource()
 * @method Mage_Sales_Model_Resource_Order_Tax getResource()
 */

class Mage_Sales_Model_Order_Tax extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_tax');
    }

    public function getOrderId(): ?int
    {
        return $this->getData('order_id');
    }

    public function setOrderId(?int $value): static
    {
        return $this->setData('order_id', $value);
    }

    public function getCode(): ?string
    {
        return $this->getData('code');
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getTitle(): ?string
    {
        return $this->getData('title');
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    public function getPercent(): ?float
    {
        return $this->getData('percent');
    }

    public function setPercent(?float $value): static
    {
        return $this->setData('percent', $value);
    }

    public function getAmount(): ?float
    {
        return $this->getData('amount');
    }

    public function setAmount(?float $value): static
    {
        return $this->setData('amount', $value);
    }

    public function getPriority(): ?int
    {
        return $this->getData('priority');
    }

    public function setPriority(?int $value): static
    {
        return $this->setData('priority', $value);
    }

    public function getPosition(): ?int
    {
        return $this->getData('position');
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }

    public function getBaseAmount(): ?float
    {
        return $this->getData('base_amount');
    }

    public function setBaseAmount(?float $value): static
    {
        return $this->setData('base_amount', $value);
    }

    public function getProcess(): ?int
    {
        return $this->getData('process');
    }

    public function setProcess(?int $value): static
    {
        return $this->setData('process', $value);
    }

    public function getBaseRealAmount(): ?float
    {
        return $this->getData('base_real_amount');
    }

    public function setBaseRealAmount(?float $value): static
    {
        return $this->setData('base_real_amount', $value);
    }

    public function getHidden(): ?int
    {
        return $this->getData('hidden');
    }

    public function setHidden(?int $value): static
    {
        return $this->setData('hidden', $value);
    }
}
