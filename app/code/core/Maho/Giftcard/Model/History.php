<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Gift Card History Model
 */
class Maho_Giftcard_Model_History extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('giftcard/history');
    }

    /**
     * Get gift card
     *
     * @return Maho_Giftcard_Model_Giftcard
     */
    public function getGiftcard()
    {
        return Mage::getModel('giftcard/giftcard')->load($this->getGiftcardId());
    }

    /**
     * Get order
     *
     * @return Mage_Sales_Model_Order|null
     */
    public function getOrder()
    {
        if (!$this->getOrderId()) {
            return null;
        }

        return Mage::getModel('sales/order')->load($this->getOrderId());
    }

    public function getGiftcardId(): ?int
    {
        return $this->getData('giftcard_id');
    }

    public function setGiftcardId(?int $value): static
    {
        return $this->setData('giftcard_id', $value);
    }

    public function getAction(): ?string
    {
        return $this->getData('action');
    }

    public function setAction(?string $value): static
    {
        return $this->setData('action', $value);
    }

    public function getBaseAmount(): ?float
    {
        return $this->getData('base_amount');
    }

    public function setBaseAmount(?float $value): static
    {
        return $this->setData('base_amount', $value);
    }

    public function getBalanceBefore(): ?float
    {
        return $this->getData('balance_before');
    }

    public function setBalanceBefore(?float $value): static
    {
        return $this->setData('balance_before', $value);
    }

    public function getBalanceAfter(): ?float
    {
        return $this->getData('balance_after');
    }

    public function setBalanceAfter(?float $value): static
    {
        return $this->setData('balance_after', $value);
    }

    public function getOrderId(): ?int
    {
        return $this->getData('order_id');
    }

    public function setOrderId(?int $value): static
    {
        return $this->setData('order_id', $value);
    }

    public function getAdminUserId(): ?int
    {
        return $this->getData('admin_user_id');
    }

    public function setAdminUserId(?int $value): static
    {
        return $this->setData('admin_user_id', $value);
    }

    public function getComment(): ?string
    {
        return $this->getData('comment');
    }

    public function setComment(?string $value): static
    {
        return $this->setData('comment', $value);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData('created_at');
    }
}
