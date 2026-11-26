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
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($subject->email);
        if (!$subscriber->getId()) {
            return Mage::helper('privacycenter')->__('No newsletter subscription');
        }
        $subscriber->delete();
        return Mage::helper('privacycenter')->__('Newsletter subscription deleted');
    }
}
