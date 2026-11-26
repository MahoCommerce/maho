<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const string XML_PATH_ENABLED = 'privacy/general/enabled';
    public const string XML_PATH_ALLOW_GUESTS = 'privacy/general/allow_guests';
    public const string XML_PATH_CONFIRM_HOURS = 'privacy/requests/confirm_hours';
    public const string XML_PATH_EXPORT_HOURS = 'privacy/requests/export_hours';
    public const string XML_PATH_COOLING_OFF_DAYS = 'privacy/requests/cooling_off_days';
    public const string XML_PATH_ERASURE_APPROVAL = 'privacy/requests/erasure_approval';
    public const string XML_PATH_REVIEW_ACTION = 'privacy/requests/review_action';
    public const string XML_PATH_EMAIL_IDENTITY = 'privacy/email/identity';
    public const string XML_PATH_CONFIRM_TEMPLATE = 'privacy/email/confirm_template';
    public const string XML_PATH_EXPORT_TEMPLATE = 'privacy/email/export_template';
    public const string XML_PATH_ERASURE_SCHEDULED_TEMPLATE = 'privacy/email/erasure_scheduled_template';
    public const string XML_PATH_ERASURE_COMPLETED_TEMPLATE = 'privacy/email/erasure_completed_template';
    public const string XML_PATH_HONEYPOT_ENABLED = 'privacy/abuse/honeypot_enabled';
    public const string XML_PATH_IP_RATE_LIMIT = 'system/rate_limit/privacy_request';
    public const string XML_PATH_RECIPIENT_RATE_LIMIT = 'system/rate_limit/privacy_request_recipient';

    public const string REVIEW_ACTION_ANONYMIZE = 'anonymize';
    public const string REVIEW_ACTION_DELETE = 'delete';

    /** The GDPR gives the store one month to answer a request. */
    public const int RESPONSE_DAYS = 30;

    public const string STORAGE_MOUNT = 'privacy_exports';

    #[\Override]
    protected $_moduleName = 'Maho_PrivacyCenter';

    public function isEnabled(mixed $store = null): bool
    {
        return $this->isModuleEnabled()
            && $this->isModuleOutputEnabled()
            && Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    public function isGuestRequestAllowed(mixed $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ALLOW_GUESTS, $store);
    }

    public function getConfirmHours(mixed $store = null): int
    {
        return max(1, (int) Mage::getStoreConfig(self::XML_PATH_CONFIRM_HOURS, $store));
    }

    public function getExportHours(mixed $store = null): int
    {
        return max(1, (int) Mage::getStoreConfig(self::XML_PATH_EXPORT_HOURS, $store));
    }

    public function getCoolingOffDays(mixed $store = null): int
    {
        return max(0, (int) Mage::getStoreConfig(self::XML_PATH_COOLING_OFF_DAYS, $store));
    }

    public function isErasureApprovalRequired(mixed $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ERASURE_APPROVAL, $store);
    }

    public function getReviewAction(mixed $store = null): string
    {
        return Mage::getStoreConfig(self::XML_PATH_REVIEW_ACTION, $store) === self::REVIEW_ACTION_DELETE
            ? self::REVIEW_ACTION_DELETE
            : self::REVIEW_ACTION_ANONYMIZE;
    }

    /**
     * The page of the request form for a guest. It sends a logged-in customer to the Privacy Center.
     */
    public function getRequestUrl(): string
    {
        return $this->_getUrl('privacy/request', ['_secure' => true]);
    }

    public function getStorage(): \Maho\Storage\Mount
    {
        return Mage::getStorage(self::STORAGE_MOUNT);
    }

    /**
     * A new random token for a link. Save only its hash, from hashToken().
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hashToken(#[\SensitiveParameter]
        string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Count one email to $email against the limit of each day, so that a request cannot fill the inbox of another
     * person.
     *
     * @return bool true when the recipient is over the limit
     */
    public function isRecipientRateLimited(#[\SensitiveParameter]
        string $email, mixed $store = null): bool
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_RECIPIENT_RATE_LIMIT, $store);
        return !Mage::helper('core')
            ->rateLimiterBy('privacy_request_recipient', Mage::helper('core')->normalizeEmail($email), $limit, 86400)
            ->attempt();
    }
}
