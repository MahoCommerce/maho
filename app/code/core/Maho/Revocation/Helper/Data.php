<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'revocation/general/enabled';
    public const XML_PATH_BUTTON_LABEL = 'revocation/general/button_label';
    public const XML_PATH_NOTIFY_EMAIL = 'revocation/general/notify_email';
    public const XML_PATH_COOLING_OFF_DAYS = 'revocation/general/cooling_off_days';
    public const XML_PATH_EMAIL_IDENTITY = 'revocation/email/identity';
    public const XML_PATH_RECEIVED_TEMPLATE = 'revocation/email/received_template';
    public const XML_PATH_MERCHANT_TEMPLATE = 'revocation/email/merchant_template';
    public const XML_PATH_MIN_SUBMIT_SECONDS = 'revocation/abuse/min_submit_seconds';
    public const XML_PATH_IP_RATE_LIMIT = 'revocation/abuse/ip_rate_limit_per_hour';
    public const XML_PATH_RECIPIENT_RATE_LIMIT = 'revocation/abuse/recipient_rate_limit_per_day';
    public const XML_PATH_MERCHANT_RATE_LIMIT = 'revocation/abuse/merchant_notification_rate_limit_per_hour';

    public const CACHE_TAG = 'revocation_ratelimit';

    protected $_moduleName = 'Maho_Revocation';

    public function isEnabled(mixed $store = null): bool
    {
        return $this->isModuleEnabled()
            && $this->isModuleOutputEnabled()
            && Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    public function getButtonLabel(mixed $store = null): string
    {
        $label = trim((string) Mage::getStoreConfig(self::XML_PATH_BUTTON_LABEL, $store));
        return $label !== '' ? $label : $this->__('Revoke contract');
    }

    public function getCoolingOffDays(mixed $store = null): int
    {
        return max(0, (int) Mage::getStoreConfig(self::XML_PATH_COOLING_OFF_DAYS, $store));
    }

    /**
     * The withdrawal window starts when the goods are delivered; the latest shipment
     * date is used as a proxy when available, the order date otherwise. Used only to
     * gate the my-account convenience link, never the public form.
     */
    public function isOrderWithinCoolingOffWindow(Mage_Sales_Model_Order $order): bool
    {
        $days = $this->getCoolingOffDays($order->getStore());
        if ($days === 0) {
            return true;
        }

        $referenceDate = $order->getCreatedAt();
        if ($order->getId()) {
            foreach ($order->getShipmentsCollection() ?: [] as $shipment) {
                $shipmentDate = $shipment->getCreatedAt();
                if ($shipmentDate && $shipmentDate > $referenceDate) {
                    $referenceDate = $shipmentDate;
                }
            }
        }
        if (!$referenceDate) {
            return true;
        }

        $deadline = (new DateTimeImmutable($referenceDate, new DateTimeZone('UTC')))
            ->modify("+{$days} days");

        return new DateTimeImmutable('now', new DateTimeZone('UTC')) <= $deadline;
    }

    /**
     * Collapse aliases that deliver to the same inbox so the per-recipient rate limit
     * cannot be bypassed with subaddressing (local+tag@) or gmail dot-variants.
     */
    public function normalizeEmail(#[\SensitiveParameter]
        string $email): string
    {
        $email = strtolower(trim($email));
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return $email;
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);

        $plusPos = strpos($local, '+');
        if ($plusPos !== false) {
            $local = substr($local, 0, $plusPos);
        }
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
        }

        return $local . '@' . $domain;
    }

    public function isIpRateLimited(string $ip, mixed $store = null): bool
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_IP_RATE_LIMIT, $store);
        return $this->_isRateLimited('revocation_ratelimit_ip_' . sha1($ip), $limit, 3600);
    }

    public function isRecipientRateLimited(#[\SensitiveParameter]
        string $email, mixed $store = null): bool
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_RECIPIENT_RATE_LIMIT, $store);
        return $this->_isRateLimited('revocation_ratelimit_email_' . sha1($this->normalizeEmail($email)), $limit, 86400);
    }

    public function isMerchantNotificationRateLimited(?int $storeId, mixed $store = null): bool
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_MERCHANT_RATE_LIMIT, $store);
        return $this->_isRateLimited('revocation_ratelimit_merchant_' . (int) $storeId, $limit, 3600);
    }

    /**
     * Rolling-window limiter on the cache backend. Records the hit unless the limit
     * is already exceeded. A limit of 0 disables the check.
     *
     * The read-modify-write is not atomic, so concurrent submissions can race and
     * slightly under-count hits. That is acceptable for abuse mitigation: this is a
     * soft throttle, not a hard guarantee.
     */
    protected function _isRateLimited(string $cacheKey, int $limit, int $windowSeconds): bool
    {
        if ($limit <= 0) {
            return false;
        }

        $now = time();
        $hits = [];
        $cached = Mage::app()->loadCache($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                $hits = $decoded;
            }
        }

        $hits = array_values(array_filter($hits, fn($timestamp) => ($now - (int) $timestamp) < $windowSeconds));
        if (count($hits) >= $limit) {
            return true;
        }

        $hits[] = $now;
        Mage::app()->saveCache(json_encode($hits), $cacheKey, [self::CACHE_TAG], $windowSeconds);

        return false;
    }
}
