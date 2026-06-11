<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

/**
 * Immutable record of a consumer's revocation declaration (EU Directive 2023/2673).
 *
 * The row itself is the legal receipt: it must be written even when emails fail
 * or are suppressed, and its declaration fields are never edited after insert.
 */
class Maho_Revocation_Model_Request extends Mage_Core_Model_Abstract
{
    public const PROCESSED_STATUS_ACCEPTED = 'accepted';
    public const PROCESSED_STATUS_REJECTED = 'rejected';
    public const PROCESSED_STATUS_INFO_REQUESTED = 'info_requested';

    public const SUPPRESSED_REASON_RATE_LIMIT = 'rate_limit_recipient';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('revocation/request');
    }

    public function getStoreId(): ?int
    {
        $value = $this->_getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): self
    {
        return $this->setData('store_id', $value);
    }

    public function getOrderId(): ?int
    {
        $value = $this->_getData('order_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderId(?int $value): self
    {
        return $this->setData('order_id', $value);
    }

    public function getOrderReference(): string
    {
        return (string) $this->_getData('order_reference');
    }

    public function setOrderReference(string $value): self
    {
        return $this->setData('order_reference', $value);
    }

    public function getCustomerName(): string
    {
        return (string) $this->_getData('customer_name');
    }

    public function setCustomerName(string $value): self
    {
        return $this->setData('customer_name', $value);
    }

    public function getEmail(): string
    {
        return (string) $this->_getData('email');
    }

    public function setEmail(string $value): self
    {
        return $this->setData('email', $value);
    }

    public function getReason(): ?string
    {
        $value = $this->_getData('reason');
        return $value === null ? null : (string) $value;
    }

    public function setReason(?string $value): self
    {
        return $this->setData('reason', $value);
    }

    /**
     * 1 only when the request was submitted by a logged-in customer through the
     * "Revoke this contract" link on their own order page (the my-account entry
     * point), with order ownership re-checked server-side in
     * Maho_Revocation_Model_Service::_resolveOrder(). Public-form submissions are
     * always 0, even when order reference + name + email all match — an unverified
     * self-assertion must never elevate itself, because a verified+linked request
     * is what lets an admin apply order-level changes.
     */
    public function getVerified(): int
    {
        return (int) $this->_getData('verified');
    }

    public function setVerified(int $value): self
    {
        return $this->setData('verified', $value);
    }

    public function getReceivedAt(): string
    {
        return (string) $this->_getData('received_at');
    }

    public function setReceivedAt(string $value): self
    {
        return $this->setData('received_at', $value);
    }

    public function getIp(): ?string
    {
        $value = $this->_getData('ip');
        return $value === null ? null : (string) $value;
    }

    public function setIp(?string $value): self
    {
        return $this->setData('ip', $value);
    }

    public function getUserAgent(): ?string
    {
        $value = $this->_getData('user_agent');
        return $value === null ? null : (string) $value;
    }

    public function setUserAgent(?string $value): self
    {
        return $this->setData('user_agent', $value);
    }

    public function getLocale(): ?string
    {
        $value = $this->_getData('locale');
        return $value === null ? null : (string) $value;
    }

    public function setLocale(?string $value): self
    {
        return $this->setData('locale', $value);
    }

    public function getProcessedAt(): ?string
    {
        $value = $this->_getData('processed_at');
        return $value === null ? null : (string) $value;
    }

    public function setProcessedAt(?string $value): self
    {
        return $this->setData('processed_at', $value);
    }

    public function getProcessedStatus(): ?string
    {
        $value = $this->_getData('processed_status');
        return $value === null ? null : (string) $value;
    }

    public function setProcessedStatus(?string $value): self
    {
        return $this->setData('processed_status', $value);
    }

    public function getAdminNote(): ?string
    {
        $value = $this->_getData('admin_note');
        return $value === null ? null : (string) $value;
    }

    public function setAdminNote(?string $value): self
    {
        return $this->setData('admin_note', $value);
    }

    public function getSuppressedAt(): ?string
    {
        $value = $this->_getData('suppressed_at');
        return $value === null ? null : (string) $value;
    }

    public function setSuppressedAt(?string $value): self
    {
        return $this->setData('suppressed_at', $value);
    }

    public function getSuppressedReason(): ?string
    {
        $value = $this->_getData('suppressed_reason');
        return $value === null ? null : (string) $value;
    }

    public function setSuppressedReason(?string $value): self
    {
        return $this->setData('suppressed_reason', $value);
    }

    public function getOrder(): ?Mage_Sales_Model_Order
    {
        if (!$this->getOrderId()) {
            return null;
        }
        $order = Mage::getModel('sales/order')->load($this->getOrderId());
        return $order->getId() ? $order : null;
    }

    public function appendAdminNote(string $note): self
    {
        $existing = trim((string) $this->getAdminNote());
        $line = '[' . Mage::app()->getLocale()->nowUtc() . ' UTC] ' . $note;
        $this->setAdminNote($existing === '' ? $line : $existing . "\n" . $line);
        return $this;
    }
}
