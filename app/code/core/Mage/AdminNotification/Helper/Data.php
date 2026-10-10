<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_AdminNotification
 */

class Mage_AdminNotification_Helper_Data extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_AdminNotification';

    /**
     * Last Notice object
     *
     * @var Mage_AdminNotification_Model_Inbox|null
     */
    protected $_latestNotice;

    /**
     * count of unread notes by type
     *
     * @var array|null
     */
    protected $_unreadNoticeCounts;

    /**
     * Tell whether a notice URL leads outside this admin. A link to such a URL opens in a new tab.
     */
    public function isExternalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $adminHost = (string) parse_url(Mage::helper('adminhtml')->getUrl('adminhtml'), PHP_URL_HOST);
        return is_string($host) && strcasecmp($host, $adminHost) !== 0;
    }

    /**
     * Retrieve latest notice model
     *
     * @return Mage_AdminNotification_Model_Inbox
     */
    public function getLatestNotice()
    {
        $this->_latestNotice ??= Mage::getModel('adminnotification/inbox')->loadLatestNotice();
        return $this->_latestNotice;
    }

    /**
     * Retrieve count of unread notes by type
     *
     * @param int $severity
     * @return int
     */
    public function getUnreadNoticeCount($severity)
    {
        $this->_unreadNoticeCounts ??= Mage::getModel('adminnotification/inbox')->getNoticeStatus();
        return $this->_unreadNoticeCounts[$severity] ?? 0;
    }
}
