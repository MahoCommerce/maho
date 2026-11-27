<?php

/**
 * The fields of the customer account.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_Profile extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Account');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        if ($subject->isGuest()) {
            return [];
        }
        $customer = Mage::getModel('customer/customer')->load($subject->customerId);
        if (!$customer->getId()) {
            return [];
        }
        return $this->clean($customer->getData());
    }
}
