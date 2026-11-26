<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const string XML_PATH_ENABLED = 'revocation/general/enabled';
    public const string XML_PATH_BUTTON_LABEL = 'revocation/general/button_label';
    public const string XML_PATH_NOTIFY_EMAIL = 'revocation/general/notify_email';
    public const string XML_PATH_COOLING_OFF_DAYS = 'revocation/general/cooling_off_days';
    public const string XML_PATH_EMAIL_IDENTITY = 'revocation/email/identity';
    public const string XML_PATH_RECEIVED_TEMPLATE = 'revocation/email/received_template';
    public const string XML_PATH_MERCHANT_TEMPLATE = 'revocation/email/merchant_template';
    public const string XML_PATH_HONEYPOT_ENABLED = 'revocation/abuse/honeypot_enabled';
    public const string XML_PATH_MIN_SUBMIT_SECONDS = 'revocation/abuse/min_submit_seconds';
    public const string XML_PATH_IP_RATE_LIMIT = 'system/rate_limit/revocation_submit';
    public const string XML_PATH_RECIPIENT_RATE_LIMIT = 'system/rate_limit/revocation_receipt';
    public const string XML_PATH_MERCHANT_RATE_LIMIT = 'system/rate_limit/revocation_merchant_notification';

    #[\Override]
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

        $deadline = new DateTimeImmutable($referenceDate, new DateTimeZone('UTC'))
            ->modify("+{$days} days");

        return new DateTimeImmutable('now', new DateTimeZone('UTC')) <= $deadline;
    }

    #[\Deprecated('Use Mage_Core_Helper_Data::normalizeEmail()')]
    public function normalizeEmail(#[\SensitiveParameter]
        string $email): string
    {
        return Mage::helper('core')->normalizeEmail($email);
    }

    public function isIpRateLimited(mixed $store = null): bool
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_IP_RATE_LIMIT, $store);
        return !Mage::helper('core')
            ->rateLimiter('revocation_ip', $limit, 3600, \Maho\Security\RateLimitScope::Ip)
            ->attempt();
    }

    public function isRecipientRateLimited(#[\SensitiveParameter]
        string $email, mixed $store = null): bool
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_RECIPIENT_RATE_LIMIT, $store);
        return !Mage::helper('core')
            ->rateLimiterBy('revocation_recipient', Mage::helper('core')->normalizeEmail($email), $limit, 86400)
            ->attempt();
    }

    public function isMerchantNotificationRateLimited(?int $storeId, mixed $store = null): bool
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_MERCHANT_RATE_LIMIT, $store);
        return !Mage::helper('core')
            ->rateLimiterBy('revocation_merchant', (string) (int) $storeId, $limit, 3600)
            ->attempt();
    }
}
