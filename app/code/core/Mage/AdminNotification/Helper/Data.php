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

    /**
     * The ID of the administrator who sends the current request, or null outside the admin.
     */
    public function getCurrentAdminUserId(): ?int
    {
        $id = Mage::getSingleton('admin/session')->getUser()?->getId();
        return $id === null ? null : (int) $id;
    }

    /**
     * Put a message in the admin inbox, with a link to the admin page of $route.
     *
     * When $adminUserId is set, only that administrator sees the message. When $aclResource is set,
     * only the administrators allowed that resource see it. With both null, every administrator does.
     * The link has no secret key, because the key of the sender is not valid in the session of the reader.
     */
    public function notifyAdmin(
        int $severity,
        string $title,
        string $text,
        string $route = '',
        array $routeParams = [],
        ?int $adminUserId = null,
        ?string $aclResource = null,
    ): void {
        Mage::getModel('adminnotification/inbox')->add(
            $severity,
            mb_substr(trim($title), 0, 255),
            trim($text),
            $route === '' ? '' : $this->getAdminUrl($route, $routeParams + ['_nosecret' => true]),
            true,
            $adminUserId,
            $aclResource,
        );
    }

    /**
     * Run $job in a queue handler and tell the administrator the result. $job returns the text of
     * the notice. When $job fails, the administrator gets a major message with the error.
     * A Mage_Core_Exception is an error in the data. A retry does not fix it, so this method does not
     * throw it again. This method throws every other error again, so the queue tries the message again.
     * Without $adminUserId, the administrators allowed $aclResource get the messages.
     * $doneRoute and $doneParams set the link of the notice when $job ends. $job can fill $doneParams.
     *
     * @param callable(): string $job
     * @throws Throwable the error of $job, unless it is a Mage_Core_Exception
     */
    public function runAndNotify(
        callable $job,
        string $doneTitle,
        string $failedTitle,
        string $route,
        ?int $adminUserId,
        ?string $aclResource = null,
        ?string $doneRoute = null,
        array &$doneParams = [],
    ): void {
        // The admin request ran the observers of the adminhtml area, so the worker runs them too
        Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_ADMINHTML, Mage_Core_Model_App_Area::PART_EVENTS);
        try {
            $text = $job();
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->notifyAdmin(
                Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR,
                $failedTitle,
                $this->getFailureText($e),
                $route,
                [],
                $adminUserId,
                $adminUserId === null ? $aclResource : null,
            );
            if ($e instanceof Mage_Core_Exception) {
                return;
            }
            throw $e;
        }

        $this->notifyAdmin(
            Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE,
            $doneTitle,
            $text,
            $doneRoute ?? $route,
            $doneParams,
            $adminUserId,
            $adminUserId === null ? $aclResource : null,
        );
    }

    /**
     * The text of the major message about the failed job of a queue handler.
     */
    protected function getFailureText(Throwable $e): string
    {
        return $e instanceof Mage_Core_Exception
            ? $this->__('%s Correct the data, then start the action again.', $e->getMessage())
            : $this->__('%s The message queue tries again.', $e->getMessage());
    }

    /**
     * An admin URL without a store code. The API and the queue workers run in a frontend store,
     * whose code would end up in the path, so the URL is built in the admin store.
     *
     * @param array<string, mixed> $params
     */
    public function getAdminUrl(string $route, array $params = []): string
    {
        $storeCode = Mage::app()->getStore()->getCode();
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
        try {
            return Mage::helper('adminhtml')->getUrl($route, $params);
        } finally {
            Mage::app()->setCurrentStore($storeCode);
        }
    }
}
