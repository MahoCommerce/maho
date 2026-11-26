<?php

/**
 * The newsletter subscriptions of the customer, or of the email for a guest.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_Newsletter extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Newsletter');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        $statuses = [
            Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED => 'subscribed',
            Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE => 'not_active',
            Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED => 'unsubscribed',
            Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED => 'unconfirmed',
        ];
        $data = [];
        $subscribers = Mage::getResourceModel('newsletter/subscriber_collection');
        if ($subject->isGuest()) {
            $adapter = $subscribers->getConnection();
            $subscribers->addFieldToFilter('main_table.customer_id', 0)->getSelect()
                ->where((string) $adapter->getCaseInsensitiveEqualsSql('main_table.subscriber_email', '?'), $subject->email);
        } else {
            $subscribers->addFieldToFilter('main_table.customer_id', $subject->customerId);
        }
        foreach ($subscribers as $subscriber) {
            $data[] = $this->clean([
                'email' => $subscriber->getSubscriberEmail(),
                'status' => $statuses[(int) $subscriber->getSubscriberStatus()] ?? (string) $subscriber->getSubscriberStatus(),
                'changed_at' => $subscriber->getChangeStatusAt(),
            ]);
        }
        return $data;
    }
}
