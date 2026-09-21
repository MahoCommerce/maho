<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Email_Log extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('core/email_log');
    }

    /**
     * Cron job: clean old log entries
     */
    #[Maho\Config\CronJob('core_email_log_clean', schedule: '0 2 * * *')]
    public function cleanOldLogs(): void
    {
        $days = (int) Mage::getStoreConfig('system/smtp/log_clean_after_days');
        if ($days <= 0) {
            $days = 30;
        }

        $cutoff = new \DateTimeImmutable("-{$days} days", new \DateTimeZone('UTC'));

        $resource = $this->getResource();
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $connection->delete(
            $resource->getMainTable(),
            ['created_at < ?' => $cutoff->format('Y-m-d H:i:s')],
        );
    }

    public function getSubject(): ?string
    {
        return $this->getData('subject');
    }

    public function setSubject(?string $value): static
    {
        return $this->setData('subject', $value);
    }

    public function getEmailTo(): ?string
    {
        return $this->getData('email_to');
    }

    public function setEmailTo(?string $value): static
    {
        return $this->setData('email_to', $value);
    }

    public function getEmailFrom(): ?string
    {
        return $this->getData('email_from');
    }

    public function setEmailFrom(?string $value): static
    {
        return $this->setData('email_from', $value);
    }

    public function getEmailCc(): ?string
    {
        return $this->getData('email_cc');
    }

    public function setEmailCc(?string $value): static
    {
        return $this->setData('email_cc', $value);
    }

    public function getEmailBcc(): ?string
    {
        return $this->getData('email_bcc');
    }

    public function setEmailBcc(?string $value): static
    {
        return $this->setData('email_bcc', $value);
    }

    public function getTemplate(): ?string
    {
        return $this->getData('template');
    }

    public function setTemplate(?string $value): static
    {
        return $this->setData('template', $value);
    }

    public function getContentType(): ?string
    {
        return $this->getData('content_type');
    }

    public function setContentType(?string $value): static
    {
        return $this->setData('content_type', $value);
    }

    public function getEmailBody(): ?string
    {
        return $this->getData('email_body');
    }

    public function setEmailBody(?string $value): static
    {
        return $this->setData('email_body', $value);
    }

    public function getStatus(): ?string
    {
        return $this->getData('status');
    }

    public function setStatus(?string $value): static
    {
        return $this->setData('status', $value);
    }

    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    public function setErrorMessage(?string $value): static
    {
        return $this->setData('error_message', $value);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData('created_at');
    }

    public function setCreatedAt(?string $value): static
    {
        return $this->setData('created_at', $value);
    }
}
