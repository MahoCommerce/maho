<?php

/**
 * A request of a customer or a guest to export or to erase the personal data of the store.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

/**
 * @method Maho_PrivacyCenter_Model_Resource_Request getResource()
 * @method Maho_PrivacyCenter_Model_Resource_Request_Collection getCollection()
 */
class Maho_PrivacyCenter_Model_Request extends Mage_Core_Model_Abstract
{
    public const string TYPE_EXPORT = 'export';
    public const string TYPE_ERASURE = 'erasure';

    /** The request waits for the confirmation link of its email. */
    public const string STATUS_PENDING = 'pending';
    /** The owner of the email confirmed the request. An erasure waits for its cooling-off period and its approval. */
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_REJECTED = 'rejected';
    public const string STATUS_CANCELED = 'canceled';
    /** The confirmation link expired before its use. */
    public const string STATUS_EXPIRED = 'expired';

    #[\Override]
    protected $_eventPrefix = 'privacy_request';

    #[\Override]
    protected $_eventObject = 'request';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('privacycenter/request');
    }

    public function isExport(): bool
    {
        return $this->getType() === self::TYPE_EXPORT;
    }

    public function isErasure(): bool
    {
        return $this->getType() === self::TYPE_ERASURE;
    }

    /**
     * Whether the request is still open: not completed, rejected, canceled or expired.
     */
    public function isOpen(): bool
    {
        return in_array($this->getStatus(), [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PROCESSING], true);
    }

    /**
     * Whether the customer can still cancel the request: an erasure before it runs.
     */
    public function canCancel(): bool
    {
        return $this->isErasure() && in_array($this->getStatus(), [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
    }

    /**
     * Whether a download link of the export is valid now.
     */
    public function isExportAvailable(): bool
    {
        return $this->isExport()
            && $this->getStatus() === self::STATUS_COMPLETED
            && $this->getExportFile() !== null
            && $this->getTokenExpiresAt() !== null
            && $this->getTokenExpiresAt() > Mage_Core_Model_Locale::nowUtc();
    }

    /**
     * Add $note to the admin notes, with the UTC time.
     */
    public function appendAdminNote(string $note): static
    {
        $line = '[' . Mage_Core_Model_Locale::nowUtc() . ' UTC] ' . $note;
        $notes = (string) $this->getAdminNote();
        return $this->setAdminNote($notes === '' ? $line : $notes . "\n" . $line);
    }

    #[\Override]
    protected function _beforeSave()
    {
        $errors = new Mage_Core_Exception_Input();
        $helper = Mage::helper('privacycenter');
        if (!array_key_exists((string) $this->getType(), Mage::getModel('privacycenter/source_type')->toOptionHash())) {
            $errors->addError('type', $helper->__('The request type is not valid.'));
        }
        if (!array_key_exists((string) $this->getStatus(), Mage::getModel('privacycenter/source_status')->toOptionHash())) {
            $errors->addError('status', $helper->__('The request status is not valid.'));
        }
        if (!Mage::helper('core')->isValidEmail((string) $this->getEmail())) {
            $errors->addError('email', $helper->__('Please enter a valid email address.'));
        }
        $errors->throwIfErrors();

        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if ($this->isObjectNew() && !$this->getCreatedAt()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);

        return parent::_beforeSave();
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

    /**
     * NULL for a guest, and after the erasure of the customer.
     */
    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getEmail(): ?string
    {
        $value = $this->getData('email');
        return $value === null ? null : (string) $value;
    }

    public function setEmail(?string $value): static
    {
        return $this->setData('email', $value);
    }

    /**
     * A TYPE_* value.
     */
    public function getType(): ?string
    {
        $value = $this->getData('type');
        return $value === null ? null : (string) $value;
    }

    public function setType(?string $value): static
    {
        return $this->setData('type', $value);
    }

    /**
     * A STATUS_* value.
     */
    public function getStatus(): ?string
    {
        $value = $this->getData('status');
        return $value === null ? null : (string) $value;
    }

    public function setStatus(?string $value): static
    {
        return $this->setData('status', $value);
    }

    /**
     * The SHA-256 hash of the token of the current link. The plain token is never saved.
     */
    public function getTokenHash(): ?string
    {
        $value = $this->getData('token_hash');
        return $value === null ? null : (string) $value;
    }

    public function setTokenHash(?string $value): static
    {
        return $this->setData('token_hash', $value);
    }

    /**
     * UTC, as Y-m-d H:i:s.
     */
    public function getTokenExpiresAt(): ?string
    {
        $value = $this->getData('token_expires_at');
        return $value === null ? null : (string) $value;
    }

    public function setTokenExpiresAt(?string $value): static
    {
        return $this->setData('token_expires_at', $value);
    }

    /**
     * UTC, as Y-m-d H:i:s.
     */
    public function getConfirmedAt(): ?string
    {
        $value = $this->getData('confirmed_at');
        return $value === null ? null : (string) $value;
    }

    public function setConfirmedAt(?string $value): static
    {
        return $this->setData('confirmed_at', $value);
    }

    /**
     * The end of the cooling-off period of an erasure. UTC, as Y-m-d H:i:s.
     */
    public function getScheduledAt(): ?string
    {
        $value = $this->getData('scheduled_at');
        return $value === null ? null : (string) $value;
    }

    public function setScheduledAt(?string $value): static
    {
        return $this->setData('scheduled_at', $value);
    }

    /**
     * UTC, as Y-m-d H:i:s.
     */
    public function getApprovedAt(): ?string
    {
        $value = $this->getData('approved_at');
        return $value === null ? null : (string) $value;
    }

    public function setApprovedAt(?string $value): static
    {
        return $this->setData('approved_at', $value);
    }

    /**
     * UTC, as Y-m-d H:i:s.
     */
    public function getProcessedAt(): ?string
    {
        $value = $this->getData('processed_at');
        return $value === null ? null : (string) $value;
    }

    public function setProcessedAt(?string $value): static
    {
        return $this->setData('processed_at', $value);
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

    /**
     * The path of the export file in the privacy_exports storage mount.
     */
    public function getExportFile(): ?string
    {
        $value = $this->getData('export_file');
        return $value === null ? null : (string) $value;
    }

    public function setExportFile(?string $value): static
    {
        return $this->setData('export_file', $value);
    }

    public function getAdminNote(): ?string
    {
        $value = $this->getData('admin_note');
        return $value === null ? null : (string) $value;
    }

    public function setAdminNote(?string $value): static
    {
        return $this->setData('admin_note', $value);
    }

    public function getRemoteIp(): ?string
    {
        $value = $this->getData('remote_ip');
        return $value === null ? null : (string) $value;
    }

    public function setRemoteIp(?string $value): static
    {
        return $this->setData('remote_ip', $value);
    }

    public function getUserAgent(): ?string
    {
        $value = $this->getData('user_agent');
        return $value === null ? null : (string) $value;
    }

    public function setUserAgent(?string $value): static
    {
        return $this->setData('user_agent', $value);
    }

    /**
     * UTC, as Y-m-d H:i:s.
     */
    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }
}
