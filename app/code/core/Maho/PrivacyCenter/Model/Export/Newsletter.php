<?php

/**
 * The newsletter subscription of the email.
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
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($subject->email);
        if (!$subscriber->getId()) {
            return [];
        }
        $statuses = [
            Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED => 'subscribed',
            Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE => 'not_active',
            Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED => 'unsubscribed',
            Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED => 'unconfirmed',
        ];
        return $this->clean([
            'email' => $subscriber->getSubscriberEmail(),
            'status' => $statuses[(int) $subscriber->getSubscriberStatus()] ?? (string) $subscriber->getSubscriberStatus(),
            'changed_at' => $subscriber->getChangeStatusAt(),
        ]);
    }
}
