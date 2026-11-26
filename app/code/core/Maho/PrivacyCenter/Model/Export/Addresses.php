<?php

/**
 * The addresses of the address book of the customer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_Addresses extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Address Book');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        if ($subject->isGuest()) {
            return [];
        }
        $addresses = Mage::getResourceModel('customer/address_collection')
            ->addAttributeToFilter('parent_id', $subject->customerId)
            ->addAttributeToSelect('*');
        $data = [];
        foreach ($addresses as $address) {
            $data[] = $this->clean($address->getData());
        }
        return $data;
    }
}
