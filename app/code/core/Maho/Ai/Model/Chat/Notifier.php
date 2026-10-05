<?php

/**
 * Puts a message about an assistant run in the admin notification inbox.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Chat_Notifier
{
    /** The severity names the model uses, as inbox severities. */
    public const SEVERITIES = [
        'critical' => Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL,
        'major' => Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR,
        'minor' => Mage_AdminNotification_Model_Inbox::SEVERITY_MINOR,
        'notice' => Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE,
    ];

    /**
     * The audience is the one of the schedule. Without a schedule, or with $creatorOnly, only
     * the administrator who owns the conversation sees the message: a confirmation or a failure
     * is for the one who can act on it. The link opens the conversation for its owner.
     */
    public static function send(
        int $severity,
        string $title,
        string $text,
        Maho_Ai_Model_Conversation $conversation,
        ?Maho_Ai_Model_Task_Schedule $schedule = null,
        bool $creatorOnly = false,
    ): void {
        $toCreator = $creatorOnly || $schedule === null;
        Mage::getModel('adminnotification/inbox')->add(
            $severity,
            mb_substr(trim($title), 0, 255),
            trim($text),
            self::conversationUrl((int) $conversation->getId()),
            true,
            $toCreator ? $conversation->getAdminUserId() : $schedule->notifyAdminUserId(),
            $toCreator ? null : $schedule->notifyAclResource(),
        );
    }

    /** A queue worker runs in a frontend store, whose code would end up in the admin URL. */
    private static function conversationUrl(int $conversationId): string
    {
        $code = Mage::app()->getStore()->getCode();
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
        try {
            return Mage::helper('adminhtml')->getUrl('adminhtml/ai_chat/open', ['id' => $conversationId, '_nosecret' => true]);
        } finally {
            Mage::app()->setCurrentStore($code);
        }
    }
}
