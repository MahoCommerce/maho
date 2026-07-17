<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Block_Success extends Mage_Core_Block_Template
{
    /**
     * @return array<string, mixed>
     */
    public function getSuccessData(): array
    {
        $data = Mage::registry('revocation_success');
        return is_array($data) ? $data : [];
    }

    public function getRequestId(): ?int
    {
        $id = $this->getSuccessData()['request_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public function getReceivedAtUtc(): ?string
    {
        return $this->getSuccessData()['received_at'] ?? null;
    }

    /**
     * Receipt timestamp in the store's timezone, for display.
     */
    public function getReceivedAtStore(): ?string
    {
        $utc = $this->getReceivedAtUtc();
        if (!$utc) {
            return null;
        }
        $store = Mage::app()->getStore();
        $dt = Mage::app()->getLocale()->utcToStore($store, $utc);
        return $dt->format(Mage_Core_Model_Locale::DATETIME_FORMAT) . ' (' . $dt->getTimezone()->getName() . ')';
    }

    /**
     * Contact address for the "no confirmation email received" fallback line. Covers
     * spam-folder, bounce and rate-limit-suppression cases without revealing which.
     */
    public function getMerchantContactEmail(): string
    {
        $email = trim((string) Mage::getStoreConfig(Maho_Revocation_Helper_Data::XML_PATH_NOTIFY_EMAIL));
        if ($email === '') {
            $email = (string) Mage::getStoreConfig('trans_email/ident_general/email');
        }
        return $email;
    }
}
