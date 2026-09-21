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
        $value = $this->getData('giftcard_id');
        return $value === null ? null : (int) $value;
    }

    public function setGiftcardId(?int $value): static
    {
        return $this->setData('giftcard_id', $value);
    }

    public function getAction(): ?string
    {
        $value = $this->getData('action');
        return $value === null ? null : (string) $value;
    }

    public function setAction(?string $value): static
    {
        return $this->setData('action', $value);
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

    public function getBalanceBefore(): ?float
    {
        $value = $this->getData('balance_before');
        return $value === null ? null : (float) $value;
    }

    public function setBalanceBefore(?float $value): static
    {
        return $this->setData('balance_before', $value);
    }

    public function getBalanceAfter(): ?float
    {
        $value = $this->getData('balance_after');
        return $value === null ? null : (float) $value;
    }

    public function setBalanceAfter(?float $value): static
    {
        return $this->setData('balance_after', $value);
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

    public function getAdminUserId(): ?int
    {
        $value = $this->getData('admin_user_id');
        return $value === null ? null : (int) $value;
    }

    public function setAdminUserId(?int $value): static
    {
        return $this->setData('admin_user_id', $value);
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

    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }
}
