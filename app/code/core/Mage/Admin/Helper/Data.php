<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

class Mage_Admin_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Admin';

    /**
     * Configuration path to expiration period of reset password link
     */
    public const XML_PATH_ADMIN_RESET_PASSWORD_LINK_EXPIRATION_PERIOD
        = 'default/admin/emails/password_reset_link_expiration_period';

    public const XML_PATH_TWOFA_REQUIRED = 'admin/security/twofa_required';
    public const XML_PATH_TWOFA_REQUIRED_FROM = 'admin/security/twofa_required_from';

    public const TWOFA_STATE_OFF = 'off';
    public const TWOFA_STATE_COMPLIANT = 'compliant';
    public const TWOFA_STATE_WARN = 'warn';
    public const TWOFA_STATE_BLOCK = 'block';

    /**
     * Generate unique token for reset password confirmation link
     *
     * @return string
     */
    public function generateResetPasswordLinkToken()
    {
        return Mage::helper('core')->uniqHash();
    }

    /**
     * Retrieve customer reset password link expiration period in days
     *
     * @return int
     */
    public function getResetPasswordLinkExpirationPeriod()
    {
        return (int) Mage::getConfig()->getNode(self::XML_PATH_ADMIN_RESET_PASSWORD_LINK_EXPIRATION_PERIOD);
    }

    /**
     * Tell how the mandatory 2FA rule applies to the given admin user.
     *
     * @return self::TWOFA_STATE_*
     */
    public function getTwofaEnforcementState(Mage_Admin_Model_User $user): string
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_TWOFA_REQUIRED)) {
            return self::TWOFA_STATE_OFF;
        }
        if ($user->getTwofaEnabled() || $user->isPasskeyEnabled()) {
            return self::TWOFA_STATE_COMPLIANT;
        }

        $days = $this->getTwofaDaysUntilEnforcement();
        return $days > 0 ? self::TWOFA_STATE_WARN : self::TWOFA_STATE_BLOCK;
    }

    /**
     * Count the days left before mandatory 2FA blocks a user who did not enroll.
     * Returns null when no valid cutover date is configured, which blocks at once.
     */
    public function getTwofaDaysUntilEnforcement(): ?int
    {
        $from = trim((string) Mage::getStoreConfig(self::XML_PATH_TWOFA_REQUIRED_FROM));
        if ($from === '' || !Mage::helper('core')->isValidDate($from)) {
            return null;
        }

        $utc = new DateTimeZone('UTC');
        $today = new DateTimeImmutable(Mage_Core_Model_Locale::todayUtc(), $utc);
        $cutover = new DateTimeImmutable($from, $utc);

        return max(0, (int) $today->diff($cutover)->format('%r%a'));
    }
}
