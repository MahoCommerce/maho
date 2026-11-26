<?php

/**
 * Unsubscribes the email from the newsletter and deletes the subscription.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Eraser_Newsletter extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Newsletter subscription');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $subscribers = Mage::getResourceModel('newsletter/subscriber_collection');
        $adapter = $subscribers->getConnection();
        $guestRow = 'main_table.customer_id = 0 AND ' . $adapter->getCaseInsensitiveEqualsSql('main_table.subscriber_email', '?');
        $subscribers->getSelect()->where(
            $subject->isGuest() ? $guestRow : 'main_table.customer_id = ' . (int) $subject->customerId . " OR ({$guestRow})",
            $subject->email,
        );
        if (!$subscribers->getSize()) {
            return Mage::helper('privacycenter')->__('No newsletter subscription');
        }
        foreach ($subscribers as $subscriber) {
            $subscriber->delete();
        }
        return Mage::helper('privacycenter')->__('Newsletter subscription deleted');
    }
}
