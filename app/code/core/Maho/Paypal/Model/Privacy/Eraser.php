<?php

/**
 * Removes the payload of the stored PayPal webhook events that name the email of the subject. The payload holds the
 * name, the email and the address of the buyer. The event row stays, so a repeated event is still recognized.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_Paypal_Model_Privacy_Eraser extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('paypal')->__('Copies of PayPal payment notifications');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $adapter = $this->getConnection();
        // The payload is JSON: the quotes keep the email from a match inside a longer address
        $like = Mage::getResourceHelper('core')->addLikeEscape('"' . mb_strtolower($subject->email) . '"', ['position' => 'any']);
        // An event that waits for processing still needs its payload
        $count = $adapter->update(
            $this->getTable('paypal/webhook_event'),
            ['payload' => null],
            ['LOWER(payload) LIKE ?' => $like, 'status <> ?' => 'received'],
        );
        return Mage::helper('paypal')->__('%d PayPal notifications cleared', $count);
    }
}
