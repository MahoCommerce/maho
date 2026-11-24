<?php

/**
 * Email the report of a subscription, or nothing when an alert did not trigger.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Queue_SubscriptionHandler
{
    #[Maho\Config\MessageHandler]
    public function __invoke(Mage_Reports_Model_Queue_Subscription $message): void
    {
        $subscription = Mage::getModel('reports/subscription')->load($message->subscriptionId);
        // The administrator stopped the email after the check was queued
        if ($subscription->getId() === null) {
            return;
        }
        Mage::getService('reports/subscription')->send($subscription);
    }
}
